<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/ModuleTestHelpers.php';

use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Operations\Models\Booking;
use App\Modules\Operations\Models\Delivery;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Operations\Models\KitchenTicket;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\QueueTicket;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\WhatsApp\Drivers\CloudApiDriver;
use App\Modules\WhatsApp\Drivers\FonnteDriver;
use App\Modules\WhatsApp\Drivers\WablasDriver;
use App\Modules\WhatsApp\Models\MessageTemplate;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

describe('halaman fitur usaha', function () {
    it('membuka semua halaman fitur sesuai modul jenis usaha', function (string $type, array $routes) {
        $owner = registerTenant($type);

        foreach ($routes as $name) {
            $this->actingAs($owner)->get(route($name))->assertOk();
        }
    })->with([
        'kedai kopi' => ['coffee_shop', ['kitchen.index', 'queue.index', 'queue.display', 'tables.index', 'modifiers.index']],
        'tempat cukur' => ['barbershop', ['bookings.index', 'commissions.index', 'settings.whatsapp']],
        'gym' => ['gym', ['members.index']],
        'laundry' => ['laundry', ['orders.index']],
        'toko kelontong' => ['toko_kelontong', ['receivables.index', 'purchases.index', 'purchases.create', 'suppliers.index', 'price-levels.index']],
        'toko bangunan' => ['toko_bangunan', ['deliveries.index']],
    ]);

    it('menutup halaman bila modulnya tidak aktif', function () {
        $owner = registerTenant('warung_makan');

        $this->actingAs($owner)->get(route('kitchen.index'))->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->actingAs($owner)->get(route('receivables.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($owner)->get(route('purchases.index'))->assertRedirect(route('dashboard'));
    });

    it('menampilkan menu fitur usaha hanya untuk modul aktif', function () {
        $owner = registerTenant('laundry');

        $this->actingAs($owner)->get(route('more'))->assertInertia(fn (Assert $page) => $page
            ->where('tenant.modules', fn ($modules) => collect($modules)->contains('order_status') && ! collect($modules)->contains('kitchen_display')));
    });

    it('kasir tidak bisa membuka halaman pemilik (meja, belanja)', function () {
        $owner = registerTenant('coffee_shop');
        $kasir = addStaff($owner, 'kasir');

        $this->actingAs($kasir)->get(route('kitchen.index'))->assertOk();
        $this->actingAs($kasir)->get(route('tables.index'))->assertForbidden();
    });
});

describe('meja & layar dapur', function () {
    it('membuat meja sekaligus banyak dan tidak bisa mengubah meja usaha lain', function () {
        $owner = registerTenant('coffee_shop');
        $other = registerTenant('coffee_shop', '081299998888', 'Kopi Tetangga');

        $this->actingAs($owner)->post(route('tables.store'), ['count' => 5, 'area' => 'Teras'])->assertRedirect();
        expect(asTenant($owner->tenant, fn () => DiningTable::pluck('name')->all()))->toBe(['Meja 1', 'Meja 2', 'Meja 3', 'Meja 4', 'Meja 5']);

        $foreign = asTenant($other->tenant, fn () => DiningTable::create(['outlet_id' => outletsOf($other)->first()->id, 'name' => 'VIP']));
        $this->actingAs($owner)->put(route('tables.update', $foreign->id), ['name' => 'Dicuri'])->assertNotFound();
    });

    it('memajukan tiket dapur: baru → dibuat → siap → diantar', function () {
        $owner = registerTenant('coffee_shop');
        $ticket = asTenant($owner->tenant, fn () => KitchenTicket::create([
            'uuid' => (string) Str::uuid(), 'outlet_id' => outletsOf($owner)->first()->id, 'label' => 'Meja 2',
            'items' => [['name' => 'Americano', 'qty' => 1]], 'status' => 'new',
        ]));

        foreach (['preparing', 'ready', 'served'] as $expected) {
            $this->actingAs($owner)->post(route('kitchen.advance', $ticket->id))->assertRedirect();
            expect($ticket->fresh()->status)->toBe($expected);
        }

        $this->actingAs($owner)->get(route('kitchen.index'))->assertInertia(fn (Assert $page) => $page->component('Operations/Kitchen')->has('tickets', 1));
    });
});

describe('antrean & janji temu', function () {
    it('mengambil nomor dan memanggil berikutnya berurutan', function () {
        $owner = registerTenant('barbershop');

        $this->actingAs($owner)->post(route('queue.store'), ['customer_name' => 'Andi']);
        $this->actingAs($owner)->post(route('queue.store'), ['customer_name' => 'Budi']);
        $this->actingAs($owner)->post(route('queue.next'))->assertSessionHas('success', 'Memanggil nomor 1 (Andi).');

        $tickets = asTenant($owner->tenant, fn () => QueueTicket::orderBy('number')->get());
        expect($tickets->pluck('status')->all())->toBe(['called', 'waiting']);
    });

    it('menolak janji yang bentrok dengan jadwal karyawan yang sama', function () {
        $owner = registerTenant('barbershop');
        $kapster = addStaff($owner, 'karyawan', '2222', ['name' => 'Mas Dodi']);
        $potong = productNamed($owner, 'Potong Rambut Dewasa'); // 30 menit
        $date = now('Asia/Jakarta')->addDay()->toDateString();

        $payload = ['customer_name' => 'Andi', 'customer_phone' => '081211112222', 'staff_id' => $kapster->id, 'date' => $date, 'time' => '10:00', 'product_ids' => [$potong->id]];
        $this->actingAs($owner)->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('bookings.store'), [...$payload, 'customer_name' => 'Budi', 'time' => '10:15'])->assertSessionHasErrors();
        $this->actingAs($owner)->post(route('bookings.store'), [...$payload, 'customer_name' => 'Cici', 'time' => '10:30'])->assertSessionHasNoErrors();

        expect(asTenant($owner->tenant, fn () => Booking::count()))->toBe(2);
    });

    it('mengirim pengingat janji lewat penjadwal', function () {
        $owner = registerTenant('barbershop');
        $potong = productNamed($owner, 'Potong Rambut Dewasa');
        $soon = now('Asia/Jakarta')->addHour();

        $this->actingAs($owner)->post(route('bookings.store'), [
            'customer_name' => 'Andi', 'customer_phone' => '081211112222', 'date' => $soon->toDateString(), 'time' => $soon->format('H:i'), 'product_ids' => [$potong->id],
        ])->assertSessionHasNoErrors();

        $this->artisan('hermes:send-reminders')->assertSuccessful();

        $message = asTenant($owner->tenant, fn () => WhatsappMessage::where('template_code', 'booking_reminder')->first());
        expect($message)->not->toBeNull()->and($message->body)->toContain('Andi');
    });
});

describe('member gym', function () {
    it('membuat paket member yang sekaligus menjadi barang di kasir', function () {
        $owner = registerTenant('gym');

        $this->actingAs($owner)->post(route('members.plans.store'), [
            'name' => 'Member 1 Bulan', 'price' => 150000, 'kind' => 'gym', 'duration_value' => 1, 'duration_unit' => 'month',
        ])->assertSessionHasNoErrors();

        $product = asTenant($owner->tenant, fn () => Product::where('name', 'Member 1 Bulan')->first());
        expect($product->type)->toBe('membership')->and($product->price)->toBe(150000);
    });

    it('mencatat absen masuk dan menolak member yang sudah habis', function () {
        $owner = registerTenant('gym');
        $customer = makeCustomer($owner);
        $this->actingAs($owner)->post(route('members.plans.store'), ['name' => 'Harian', 'price' => 20000, 'kind' => 'gym', 'duration_value' => 1, 'duration_unit' => 'day']);
        $plan = asTenant($owner->tenant, fn () => App\Modules\Operations\Models\MembershipPlan::first());
        $membership = asTenant($owner->tenant, fn () => app(App\Modules\Operations\Services\MembershipService::class)->activate($customer, $plan, null, 'Asia/Jakarta'));

        $this->actingAs($owner)->post(route('members.checkin'), ['customer_id' => $customer->id])->assertSessionHasNoErrors()->assertSessionHas('success');

        asTenant($owner->tenant, fn () => $membership->update(['starts_on' => now()->subDays(5), 'ends_on' => now()->subDays(2)]));
        $this->actingAs($owner)->post(route('members.checkin'), ['customer_id' => $customer->id])->assertSessionHasErrors();
    });

    it('mengingatkan member yang paketnya habis 3 hari lagi (sekali saja)', function () {
        $owner = registerTenant('gym');
        $customer = makeCustomer($owner);
        $this->actingAs($owner)->post(route('members.plans.store'), ['name' => 'Bulanan', 'price' => 150000, 'kind' => 'gym', 'duration_value' => 1, 'duration_unit' => 'month']);
        asTenant($owner->tenant, function () use ($customer) {
            Membership::create(['customer_id' => $customer->id, 'membership_plan_id' => App\Modules\Operations\Models\MembershipPlan::first()->id, 'starts_on' => now()->subMonth(), 'ends_on' => now('Asia/Jakarta')->addDays(2), 'status' => 'active']);
        });

        $this->artisan('hermes:send-reminders')->assertSuccessful();
        $this->artisan('hermes:send-reminders')->assertSuccessful();

        expect(asTenant($owner->tenant, fn () => WhatsappMessage::where('template_code', 'membership_expiring')->count()))->toBe(1);
    });
});

describe('laundry: papan status', function () {
    it('memindahkan pesanan ke tahap berikutnya lalu diambil sambil melunasi', function () {
        $owner = registerTenant('laundry');
        enableModule($owner, 'credit_sales');
        $cash = paymentMethod($owner);
        $shift = openShift($this, $owner);
        $kiloan = productNamed($owner, 'Cuci Setrika (per kg)');
        $customer = makeCustomer($owner);

        sell($this, $owner, $shift, [['product_id' => $kiloan->id, 'qty' => '3']], [['payment_method_id' => $cash->id, 'amount' => 10000]], ['customer_uuid' => $customer->uuid, 'pay_later' => true])->assertCreated();
        $sale = asTenant($owner->tenant, fn () => Sale::first());
        expect($sale->due_amount)->toBe(11000);

        $this->actingAs($owner)->post(route('orders.move', $sale->uuid))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('orders.pickup', $sale->uuid), ['payment_method_id' => $cash->id])->assertSessionHas('success');

        $sale = asTenant($owner->tenant, fn () => $sale->fresh());
        expect($sale->due_amount)->toBe(0)->and($sale->picked_up_at)->not->toBeNull();
    });
});

describe('kasbon', function () {
    it('mencatat utang manual, menerima cicilan, dan mengirim pengingat', function () {
        $owner = registerTenant('toko_kelontong');
        $customer = makeCustomer($owner);

        $this->actingAs($owner)->post(route('receivables.store'), ['customer_id' => $customer->id, 'amount' => 50000, 'note' => 'utang lama'])
            ->assertRedirect(route('receivables.show', $customer));
        $this->actingAs($owner)->post(route('receivables.pay', $customer), ['amount' => 20000])->assertSessionHas('success', 'Pembayaran dicatat. Sisa utang Rp30.000.');
        $this->actingAs($owner)->post(route('receivables.remind', $customer))->assertSessionHas('success');

        $this->actingAs($owner)->get(route('receivables.index'))->assertInertia(fn (Assert $page) => $page->where('total', 30000)->where('customers.0.balance', 30000));
        expect(asTenant($owner->tenant, fn () => WhatsappMessage::where('template_code', 'kasbon_reminder')->first()->body))->toContain('Rp30.000');
    });

    it('menyimpan batas kasbon & tipe harga di data pelanggan', function () {
        $owner = registerTenant('toko_kelontong');
        $customer = makeCustomer($owner);
        $level = asTenant($owner->tenant, fn () => PriceLevel::create(['name' => 'Grosir']));

        $this->actingAs($owner)->put(route('customers.update', $customer), ['name' => 'Pak Joko', 'phone' => '081311112222', 'price_level_id' => $level->id, 'credit_limit' => 500000])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        expect($customer->price_level_id)->toBe($level->id)->and($customer->credit_limit)->toBe(500000);
    });
});

describe('belanja ke pemasok', function () {
    it('menambah stok, menghitung modal per satuan dasar, dan mencatat utang', function () {
        $owner = registerTenant('toko_kelontong');
        $mi = productNamed($owner, 'Mi Instan Goreng'); // stok 80
        $dus = asTenant($owner->tenant, fn () => Unit::firstOrCreate(['name' => 'Dus'], ['symbol' => 'dus']));
        $perDus = asTenant($owner->tenant, fn () => $mi->units()->create(['unit_id' => $dus->id, 'conversion_qty' => 40, 'price' => 130000]));
        $supplier = asTenant($owner->tenant, fn () => Supplier::create(['name' => 'Agen Sumber Rejeki']));

        $this->actingAs($owner)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'paid_amount' => 100000, 'due_date' => now()->addWeek()->toDateString(),
            'items' => [['product_id' => $mi->id, 'unit_id' => $perDus->id, 'qty' => 2, 'unit_cost' => 120000]],
        ])->assertSessionHasNoErrors();

        $purchase = asTenant($owner->tenant, fn () => Purchase::first());
        expect($purchase->total)->toBe(240000)
            ->and($purchase->payment_status)->toBe('partial')
            ->and(qtyOf($owner, $mi))->toBe('160.000');

        $this->actingAs($owner)->post(route('purchases.pay', $purchase->uuid), ['amount' => 140000])->assertSessionHas('success', 'Utang ke pemasok sudah lunas.');
        $this->actingAs($owner)->post(route('purchases.pay', $purchase->uuid), ['amount' => 1])->assertSessionHasErrors('amount');
    });
});

describe('pengiriman & faktur', function () {
    it('membuat surat jalan sebagian dan mencetak faktur A4', function () {
        $owner = registerTenant('toko_bangunan');
        $cash = paymentMethod($owner);
        $shift = openShift($this, $owner);
        $semen = productNamed($owner, 'Semen 50 kg');

        sell($this, $owner, $shift, [['product_id' => $semen->id, 'qty' => 10]], [['payment_method_id' => $cash->id, 'amount' => 680000]])->assertCreated();
        $sale = asTenant($owner->tenant, fn () => Sale::with('items')->first());

        $this->actingAs($owner)->post(route('deliveries.store', $sale->uuid), [
            'type' => 'delivery', 'recipient_name' => 'Pak Mandor', 'address' => 'Jl. Melati 5',
            'items' => [['sale_item_id' => $sale->items->first()->id, 'qty' => 4]],
        ])->assertRedirect(route('deliveries.index'));

        $delivery = asTenant($owner->tenant, fn () => Delivery::with('items')->first());
        expect($delivery->number)->toStartWith('SJ')->and($delivery->items->first()->qty)->toBe('4.000');

        $this->actingAs($owner)->get(route('deliveries.create', $sale->uuid))->assertInertia(fn (Assert $page) => $page->where('items.0.remaining', '6'));
        $this->actingAs($owner)->get(route('deliveries.print', $delivery->uuid))->assertOk()->assertSee('SURAT JALAN')->assertSee('Pak Mandor');
        $this->actingAs($owner)->get(route('sales.invoice', $sale->uuid))->assertOk()->assertSee('FAKTUR')->assertSee('Enam ratus delapan puluh ribu');
    });
});

describe('pilihan, tipe harga, komisi', function () {
    it('membuat pilihan & menempelkannya ke menu', function () {
        $owner = registerTenant('coffee_shop');
        $kopi = productNamed($owner, 'Americano');

        $this->actingAs($owner)->post(route('modifiers.store'), [
            'name' => 'Gula', 'selection' => 'single', 'is_required' => false,
            'options' => [['name' => 'Normal', 'price_delta' => 0], ['name' => 'Tanpa gula', 'price_delta' => 0]],
            'product_ids' => [$kopi->id],
        ])->assertSessionHasNoErrors();

        $group = asTenant($owner->tenant, fn () => ModifierGroup::with('options', 'products')->first());
        expect($group->options)->toHaveCount(2)->and($group->products->pluck('id')->all())->toBe([$kopi->id]);

        // Ubah: hapus satu pilihan.
        $this->actingAs($owner)->put(route('modifiers.update', $group), [
            'name' => 'Gula', 'selection' => 'single', 'options' => [['id' => $group->options[0]->id, 'name' => 'Normal', 'price_delta' => 0]], 'product_ids' => [],
        ])->assertSessionHasNoErrors();
        expect(asTenant($owner->tenant, fn () => $group->options()->count()))->toBe(1);
    });

    it('menyimpan resep, satuan lain, dan harga grosir dari formulir barang', function () {
        $owner = registerTenant('toko_bangunan');
        enableModule($owner, 'recipe');
        $semen = productNamed($owner, 'Semen 50 kg');
        [$sak, $palet] = asTenant($owner->tenant, fn () => [Unit::where('name', 'Sak')->first(), Unit::firstOrCreate(['name' => 'Palet'], ['symbol' => 'plt'])]);
        $level = asTenant($owner->tenant, fn () => PriceLevel::create(['name' => 'Kontraktor']));

        $this->actingAs($owner)->put(route('products.update', $semen), [
            'name' => 'Semen 50 kg', 'type' => 'goods', 'price' => 68000, 'base_unit_id' => $sak->id, 'track_stock' => true,
            'extras' => ['units', 'prices'],
            'units' => [['unit_id' => $palet->id, 'conversion_qty' => '40', 'price' => 2600000]],
            'prices' => [['price_level_id' => null, 'unit_id' => null, 'min_qty' => '10', 'price' => 66000], ['price_level_id' => $level->id, 'unit_id' => $palet->id, 'min_qty' => '1', 'price' => 2500000]],
        ])->assertSessionHasNoErrors();

        $semen = asTenant($owner->tenant, fn () => $semen->fresh(['units', 'prices']));
        expect($semen->units)->toHaveCount(1)
            ->and($semen->prices)->toHaveCount(2)
            ->and($semen->prices->firstWhere('price_level_id', $level->id)->product_unit_id)->toBe($semen->units->first()->id);

        // Bagian yang ditampilkan tapi dikosongkan → dihapus.
        $this->actingAs($owner)->put(route('products.update', $semen), [
            'name' => 'Semen 50 kg', 'type' => 'goods', 'price' => 68000, 'base_unit_id' => $sak->id, 'extras' => ['prices'],
        ])->assertSessionHasNoErrors();
        expect(asTenant($owner->tenant, fn () => [$semen->prices()->count(), $semen->units()->count()]))->toBe([0, 1]);
    });

    it('menghitung rekap komisi dan menandai sudah dibayar', function () {
        $owner = registerTenant('barbershop');
        $kapster = addStaff($owner, 'karyawan', '2222', ['name' => 'Mas Dodi']);
        $cash = paymentMethod($owner);
        $shift = openShift($this, $owner);
        $potong = productNamed($owner, 'Potong Rambut Dewasa');

        $this->actingAs($owner)->post(route('commissions.rules.store'), ['type' => 'percent', 'value' => 40])->assertSessionHasNoErrors();
        sell($this, $owner, $shift, [['product_id' => $potong->id, 'qty' => 1, 'staff_id' => $kapster->id]], [['payment_method_id' => $cash->id, 'amount' => 25000]])->assertCreated();

        $this->actingAs($owner)->get(route('commissions.index'))->assertInertia(fn (Assert $page) => $page->where('summary.0.total', 10000)->where('summary.0.unpaid', 10000));

        $range = ['dari' => now('Asia/Jakarta')->startOfMonth()->toDateString(), 'sampai' => now('Asia/Jakarta')->toDateString()];
        $this->actingAs($owner)->post(route('commissions.pay', $kapster->id), $range)->assertSessionHas('success', 'Komisi sudah ditandai dibayar.');
        $this->actingAs($owner)->get(route('commissions.index'))->assertInertia(fn (Assert $page) => $page->where('summary.0.unpaid', 0));
    });

    it('menyimpan catatan layanan pelanggan (salon)', function () {
        $owner = registerTenant('salon');
        $customer = makeCustomer($owner);

        $this->actingAs($owner)->post(route('service-notes.store', $customer), ['note' => 'Suka potongan bob, cat cokelat'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('customers.show', $customer))->assertInertia(fn (Assert $page) => $page->where('serviceNotes.0.note', 'Suka potongan bob, cat cokelat'));
    });
});

describe('pengaturan whatsapp', function () {
    it('menyimpan nomor usaha sendiri dengan token terenkripsi', function () {
        $owner = registerTenant('barbershop');

        $this->actingAs($owner)->put(route('settings.whatsapp.account'), ['mode' => 'own', 'provider' => 'fonnte', 'token' => 'rahasia-123', 'sender_phone' => '081277778888'])
            ->assertSessionHasNoErrors();

        $account = WhatsappAccount::where('tenant_id', $owner->tenant_id)->first();
        expect($account->credentials['token'])->toBe('rahasia-123')
            ->and(DB::table('whatsapp_accounts')->value('credentials'))->not->toContain('rahasia-123');

        // Kembali ke nomor pusat.
        $this->actingAs($owner)->put(route('settings.whatsapp.account'), ['mode' => 'central']);
        expect($account->fresh()->is_active)->toBeFalse();
    });

    it('mengubah isi pesan dan mengembalikannya ke bawaan', function () {
        $owner = registerTenant('barbershop');

        $this->actingAs($owner)->put(route('settings.whatsapp.template', 'receipt'), ['body' => 'Makasih {nama}!'])->assertSessionHasNoErrors();
        expect(asTenant($owner->tenant, fn () => app(WhatsAppService::class)->template('receipt')))->toBe('Makasih {nama}!');

        $this->actingAs($owner)->put(route('settings.whatsapp.template', 'receipt'), ['reset' => true]);
        expect(asTenant($owner->tenant, fn () => MessageTemplate::count()))->toBe(0);
    });

    it('mengirim lewat Fonnte, Wablas, dan Cloud API', function () {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['111']]),
            'solo.wablas.com/*' => Http::response(['status' => true, 'data' => ['messages' => [['id' => 'w-1']]]]),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        expect((new FonnteDriver('t'))->send('6281211112222', 'Halo')->ok)->toBeTrue()
            ->and((new WablasDriver('t', 'https://solo.wablas.com'))->send('6281211112222', 'Halo')->ok)->toBeTrue()
            ->and((new CloudApiDriver('t', '123'))->send('6281211112222', 'Halo')->ok)->toBeTrue();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/send' && $request['target'] === '6281211112222');
    });

    it('mencatat alasan gagal dari penyedia', function () {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'token invalid'])]);

        $result = (new FonnteDriver('salah'))->send('6281211112222', 'Halo');
        expect($result->ok)->toBeFalse()->and($result->error)->toBe('token invalid');
    });
});
