<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/ModuleTestHelpers.php';

use App\Core\Modules\ModuleManager;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\RecipeItem;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Operations\Models\CommissionRule;
use App\Modules\Operations\Models\KitchenTicket;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\MembershipPlan;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\StaffCommission;
use App\Modules\Operations\Services\MembershipService;
use App\Modules\Operations\Services\OrderStatusService;
use App\Modules\Pos\Models\Sale;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

describe('kedai kopi: pilihan, resep, dapur, antrean', function () {
    beforeEach(function () {
        $this->owner = registerTenant('coffee_shop');
        $this->cash = paymentMethod($this->owner);
        $this->kopi = productNamed($this->owner, 'Kopi Susu Gula Aren'); // 18.000
        $this->biji = productNamed($this->owner, 'Biji Kopi Arabika');   // bahan, 2000 gram, modal 250/gram
        $this->susu = productNamed($this->owner, 'Susu Segar');          // bahan, 20 liter, modal 20/liter (contoh)
        $this->shift = openShift($this, $this->owner);

        asTenant($this->owner->tenant, function () {
            $this->size = ModifierGroup::create(['name' => 'Ukuran', 'selection' => 'single', 'is_required' => true]);
            $this->kecil = $this->size->options()->create(['name' => 'Kecil', 'price_delta' => 0]);
            $this->besar = $this->size->options()->create(['name' => 'Besar', 'price_delta' => 5000]);
            $this->kopi->modifierGroups()->attach($this->size->id);
            RecipeItem::create(['product_id' => $this->kopi->id, 'ingredient_id' => $this->biji->id, 'qty' => 18]);
            RecipeItem::create(['product_id' => $this->kopi->id, 'ingredient_id' => $this->susu->id, 'qty' => '0.15']);
        });
    });

    it('menambah harga pilihan & menyimpan pilihan di nota', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->kopi->id, 'qty' => 2, 'modifiers' => [$this->besar->id]]], [['payment_method_id' => $this->cash->id, 'amount' => 50000]])
            ->assertCreated()
            ->assertJsonPath('data.total', 46000);

        $item = asTenant($this->owner->tenant, fn () => Sale::first()->items()->with('modifiers')->first());
        expect($item->unit_price)->toBe(23000)
            ->and($item->modifiers->first()->name)->toBe('Besar');
    });

    it('menolak bila pilihan wajib belum dipilih', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->kopi->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 20000]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.items.0', 'Pilih Ukuran untuk Kopi Susu Gula Aren.');
    });

    it('memotong stok bahan sesuai resep dan menghitung modal (HPP) dari bahan', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->kopi->id, 'qty' => 2, 'modifiers' => [$this->kecil->id]]], [['payment_method_id' => $this->cash->id, 'amount' => 36000]])
            ->assertCreated();

        expect(qtyOf($this->owner, $this->biji))->toBe('1964.000')   // 2000 - 2 x 18
            ->and(qtyOf($this->owner, $this->susu))->toBe('19.700');   // 20 - 2 x 0,15

        $cost = asTenant($this->owner->tenant, fn () => Sale::first()->items()->value('cost_amount'));
        expect($cost)->toBe(2 * 18 * 250 + 6); // biji 9.000 + susu 0,3 L x 20 = 6
    });

    it('pesanan yang dibayar langsung muncul di layar dapur dengan nomor antrean', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->kopi->id, 'qty' => 1, 'modifiers' => [$this->besar->id], 'note' => 'Es sedikit']], [['payment_method_id' => $this->cash->id, 'amount' => 23000]], ['queue_number' => '12'])
            ->assertCreated();

        $ticket = asTenant($this->owner->tenant, fn () => KitchenTicket::first());
        expect($ticket->label)->toBe('Antrean 12')
            ->and($ticket->items[0])->toMatchArray(['name' => 'Kopi Susu Gula Aren', 'note' => 'Es sedikit', 'modifiers' => ['Besar']]);
    });

    it('tidak mengirim ulang ke dapur bila pesanan meja sudah dikirim', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->kopi->id, 'qty' => 1, 'modifiers' => [$this->kecil->id]]], [['payment_method_id' => $this->cash->id, 'amount' => 18000]], ['send_to_kitchen' => false]);

        expect(asTenant($this->owner->tenant, fn () => KitchenTicket::count()))->toBe(0);
    });

    it('tiket dapur dari meja (offline) aman dikirim ulang', function () {
        $item = ['id' => 1, 'type' => 'kitchen.ticket', 'payload' => [
            'uuid' => (string) Str::uuid(), 'outlet_id' => outletsOf($this->owner)->first()->id, 'label' => 'Meja 3',
            'items' => [['name' => 'Kopi', 'qty' => '2', 'note' => null, 'modifiers' => []]],
        ]];

        $this->actingAs($this->owner)->postJson(route('pos.api.sync'), ['items' => [$item]])->assertJsonPath('results.0.status', 'ok');
        $this->actingAs($this->owner)->postJson(route('pos.api.sync'), ['items' => [$item]]);

        expect(asTenant($this->owner->tenant, fn () => KitchenTicket::count()))->toBe(1);
    });
});

describe('toko bangunan: banyak satuan, harga khusus, tempo', function () {
    beforeEach(function () {
        $this->owner = registerTenant('toko_bangunan');
        $this->cash = paymentMethod($this->owner);
        $this->paku = productNamed($this->owner, 'Paku 5 cm'); // per kg, stok 25, harga 22.000
        $this->semen = productNamed($this->owner, 'Semen 50 kg'); // per sak, stok 40, harga 68.000
        $this->shift = openShift($this, $this->owner);

        asTenant($this->owner->tenant, function () {
            $this->dus = ProductUnit::create(['product_id' => $this->paku->id, 'unit_id' => Unit::where('name', 'Dus')->value('id'), 'conversion_qty' => 10, 'price' => 200000, 'barcode' => '899000111']);
            $this->tukang = PriceLevel::create(['name' => 'Tukang']);
            ProductPrice::create(['product_id' => $this->semen->id, 'price_level_id' => $this->tukang->id, 'min_qty' => 1, 'price' => 65000]);
            ProductPrice::create(['product_id' => $this->semen->id, 'price_level_id' => null, 'min_qty' => 10, 'price' => 66000]); // grosir 10 sak
        });
    });

    it('menjual per dus memotong stok dalam satuan dasar', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->paku->id, 'qty' => 2, 'unit_id' => $this->dus->id]], [['payment_method_id' => $this->cash->id, 'amount' => 400000]])
            ->assertCreated()
            ->assertJsonPath('data.total', 400000)
            ->assertJsonPath('data.items.0.unit', 'Dus');

        expect(qtyOf($this->owner, $this->paku))->toBe('5.000'); // 25 - 2 x 10 kg
    });

    it('memakai harga tukang untuk pelanggan bertipe tukang', function () {
        $tukang = makeCustomer($this->owner, ['price_level_id' => $this->tukang->id]);

        sell($this, $this->owner, $this->shift, [['product_id' => $this->semen->id, 'qty' => 2]], [['payment_method_id' => $this->cash->id, 'amount' => 130000]], ['customer_uuid' => $tukang->uuid])
            ->assertJsonPath('data.total', 130000);
    });

    it('memakai harga grosir mulai jumlah tertentu untuk semua pembeli', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->semen->id, 'qty' => 10]], [['payment_method_id' => $this->cash->id, 'amount' => 660000]])
            ->assertJsonPath('data.total', 660000);
        sell($this, $this->owner, $this->shift, [['product_id' => $this->semen->id, 'qty' => 9]], [['payment_method_id' => $this->cash->id, 'amount' => 612000]])
            ->assertJsonPath('data.total', 612000); // 9 sak belum grosir
    });

    it('jual tempo dengan uang muka mencatat piutang dengan jatuh tempo', function () {
        $kontraktor = makeCustomer($this->owner, ['name' => 'CV Maju']);

        sell($this, $this->owner, $this->shift, [['product_id' => $this->semen->id, 'qty' => 5]], [['payment_method_id' => $this->cash->id, 'amount' => 100000]], [
            'customer_uuid' => $kontraktor->uuid, 'pay_later' => true, 'due_date' => now()->addDays(30)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.due_amount', 240000)->assertJsonPath('data.payment_status', 'partial');

        $receivable = asTenant($this->owner->tenant, fn () => Receivable::first());
        expect($receivable->type)->toBe('tempo')
            ->and($receivable->amount)->toBe(240000)
            ->and($receivable->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());
    });
});

describe('warung kelontong: kasbon', function () {
    beforeEach(function () {
        $this->owner = registerTenant('toko_kelontong');
        $this->cash = paymentMethod($this->owner);
        $this->beras = productNamed($this->owner, 'Beras Premium 5 kg');
        $this->shift = openShift($this, $this->owner);
        $this->joko = makeCustomer($this->owner, ['credit_limit' => 200000]);
    });

    it('bayar nanti butuh nama pelanggan', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->beras->id, 'qty' => 1]], [], ['pay_later' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer');
    });

    it('mencatat kasbon, menolak bila melewati batas, lalu menerima cicilan hingga lunas', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->beras->id, 'qty' => 2]], [], ['pay_later' => true, 'customer_uuid' => $this->joko->uuid])
            ->assertCreated()->assertJsonPath('data.payment_status', 'unpaid');

        expect(asTenant($this->owner->tenant, fn () => $this->joko->fresh()->outstandingBalance()))->toBe(144000);

        sell($this, $this->owner, $this->shift, [['product_id' => $this->beras->id, 'qty' => 1]], [], ['pay_later' => true, 'customer_uuid' => $this->joko->uuid])
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer.0', fn ($m) => str_contains($m, 'Batas kasbon Pak Joko Rp200.000 terlewati'));

        // Cicilan dari kasir (offline-ready), dikirim dua kali tetap tercatat sekali.
        $payment = ['id' => 1, 'type' => 'receivable.payment', 'payload' => [
            'uuid' => (string) Str::uuid(), 'customer_uuid' => $this->joko->uuid, 'amount' => 44000, 'payment_method_id' => $this->cash->id,
        ]];
        $this->actingAs($this->owner)->postJson(route('pos.api.sync'), ['items' => [$payment]])->assertJsonPath('results.0.data.remaining', 100000);
        $this->actingAs($this->owner)->postJson(route('pos.api.sync'), ['items' => [$payment]]);
        expect(asTenant($this->owner->tenant, fn () => $this->joko->fresh()->outstandingBalance()))->toBe(100000);

        $payment['payload']['uuid'] = (string) Str::uuid();
        $payment['payload']['amount'] = 100000;
        $this->actingAs($this->owner)->postJson(route('pos.api.sync'), ['items' => [$payment]])->assertJsonPath('results.0.data.remaining', 0);

        $sale = asTenant($this->owner->tenant, fn () => Sale::first());
        expect($sale->payment_status)->toBe('paid')->and($sale->due_amount)->toBe(0);
    });

    it('membatalkan transaksi kasbon ikut membatalkan utangnya', function () {
        $sale = sell($this, $this->owner, $this->shift, [['product_id' => $this->beras->id, 'qty' => 1]], [], ['pay_later' => true, 'customer_uuid' => $this->joko->uuid])->json('data');
        $this->actingAs($this->owner)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'Batal']);

        expect(asTenant($this->owner->tenant, fn () => $this->joko->fresh()->outstandingBalance()))->toBe(0);
    });
});

describe('tempat cukur: komisi kapster', function () {
    it('menghitung komisi sesuai aturan yang paling khusus, dan batal bila transaksi dibatalkan', function () {
        $owner = registerTenant('barbershop');
        $kapster = addStaff($owner, 'karyawan', '2222', ['job_title' => 'Kapster']);
        $cash = paymentMethod($owner);
        $potong = productNamed($owner, 'Potong Rambut Dewasa'); // 25.000
        $shift = openShift($this, $owner);

        asTenant($owner->tenant, function () use ($kapster, $potong) {
            CommissionRule::create(['type' => 'percent', 'value' => 3000]);                                     // umum 30%
            CommissionRule::create(['user_id' => $kapster->id, 'product_id' => $potong->id, 'type' => 'fixed', 'value' => 12000]); // khusus
        });

        $sale = sell($this, $owner, $shift, [
            ['product_id' => $potong->id, 'qty' => 1, 'staff_id' => $kapster->id],
            ['product_id' => productNamed($owner, 'Cukur Jenggot')->id, 'qty' => 1, 'staff_id' => $kapster->id], // 10.000 x 30%
        ], [['payment_method_id' => $cash->id, 'amount' => 35000]])->assertCreated()->json('data');

        expect(asTenant($owner->tenant, fn () => StaffCommission::where('user_id', $kapster->id)->sum('amount')))->toBe(15000);

        $this->actingAs($owner)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'Salah']);
        expect(asTenant($owner->tenant, fn () => StaffCommission::where('status', 'pending')->count()))->toBe(0);
    });
});

describe('gym: member', function () {
    beforeEach(function () {
        $this->owner = registerTenant('gym');
        $this->cash = paymentMethod($this->owner);
        $this->shift = openShift($this, $this->owner);
        $this->paket = asTenant($this->owner->tenant, fn () => Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Member Bulanan', 'type' => 'membership', 'price' => 150000, 'track_stock' => false]));
        $this->plan = asTenant($this->owner->tenant, fn () => MembershipPlan::create(['product_id' => $this->paket->id, 'kind' => 'gym', 'duration_value' => 1, 'duration_unit' => 'month']));
        $this->budi = makeCustomer($this->owner, ['name' => 'Budi']);
    });

    it('menjual paket member wajib dengan nama pelanggan', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->paket->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 150000]])
            ->assertJsonPath('errors.customer.0', 'Pilih pelanggan dulu untuk menjual paket member.');
    });

    it('mengaktifkan member, perpanjangan disambung, dan check-in hanya untuk yang aktif', function () {
        foreach (range(1, 2) as $i) {
            sell($this, $this->owner, $this->shift, [['product_id' => $this->paket->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 150000]], ['customer_uuid' => $this->budi->uuid])
                ->assertCreated();
        }

        $memberships = asTenant($this->owner->tenant, fn () => Membership::orderBy('id')->get());
        expect($memberships)->toHaveCount(2)
            ->and($memberships[1]->starts_on->toDateString())->toBe($memberships[0]->ends_on->copy()->addDay()->toDateString());

        $tz = $this->owner->tenant->timezone;
        $checkin = asTenant($this->owner->tenant, fn () => app(MembershipService::class)->checkIn($this->budi, outletsOf($this->owner)->first()->id, $this->owner, $tz));
        expect($checkin->customer_id)->toBe($this->budi->id);

        $siti = makeCustomer($this->owner, ['name' => 'Siti']);
        expect(fn () => asTenant($this->owner->tenant, fn () => app(MembershipService::class)->checkIn($siti, outletsOf($this->owner)->first()->id, $this->owner, $tz)))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('laundry: status pesanan & WhatsApp', function () {
    it('pesanan masuk status pertama dengan perkiraan selesai, lalu kabar WhatsApp saat siap diambil', function () {
        Queue::fake();
        $owner = registerTenant('laundry');
        $cash = paymentMethod($owner);
        $kiloan = productNamed($owner, 'Cuci Setrika (per kg)'); // 7.000/kg, 2 hari
        $shift = openShift($this, $owner);
        $ani = makeCustomer($owner, ['name' => 'Bu Ani']);

        // Bayar saat ambil: belum dibayar sama sekali.
        $sale = sell($this, $owner, $shift, [['product_id' => $kiloan->id, 'qty' => '3.5']], [], ['customer_uuid' => $ani->uuid, 'pay_later' => true])
            ->assertCreated()->assertJsonPath('data.total', 24500)->json('data');

        $model = asTenant($owner->tenant, fn () => Sale::with('customer')->where('uuid', $sale['uuid'])->first());
        expect($model->order_status_id)->not->toBeNull()
            ->and($model->estimated_ready_at->diffInHours($model->completed_at, true))->toBe(48.0);

        asTenant($owner->tenant, function () use ($model, $owner) {
            $service = app(OrderStatusService::class);
            $ready = $service->statuses()->firstWhere('name', 'Siap Diambil');
            $service->moveTo($model, $ready, $owner);
        });

        $message = asTenant($owner->tenant, fn () => WhatsappMessage::first());
        expect($message->to_phone)->toBe('6281311112222')
            ->and($message->body)->toContain('Siap Diambil')
            ->and($message->body)->toContain('Sisa pembayaran: Rp24.500');
        Queue::assertPushed(SendWhatsAppMessage::class);
    });
});
