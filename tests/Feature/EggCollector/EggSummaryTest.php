<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\EggCollector\Services\EggSummaryService;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));
    $this->owner = registerTenant('pengepul_telur', phone: '6281233334444', name: 'Pengepul Telur Jaya');
    $this->tenant = $this->owner->tenant;
    $this->p = fn (string $name) => asTenant($this->tenant, fn () => Product::where('name', $name)->first());

    $this->buyFromFarmer = function (string $kg = '100', int $paid = 1_000_000, ?string $due = null) {
        $supplier = asTenant($this->tenant, fn () => Supplier::firstOrCreate(['name' => 'Pak Wayan']));
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now('Asia/Jakarta')->toDateString(), 'paid_amount' => $paid,
            'due_date' => $due ?? now('Asia/Jakarta')->addWeek()->toDateString(),
            'items' => [['product_id' => ($this->p)('Telur Campur')->id, 'qty' => $kg, 'unit_cost' => 24000]],
        ])->assertSessionHasNoErrors();
    };

    $this->sortAndSell = function () {
        $this->actingAs($this->owner)->post(route('egg.sortings.store'), [
            'input_qty' => '40',
            'outputs' => [
                ['product_id' => ($this->p)('Telur Besar')->id, 'qty' => '30'],
                ['product_id' => ($this->p)('Telur Kecil')->id, 'qty' => '8'],
                ['product_id' => ($this->p)('Telur Retak')->id, 'qty' => '1,5'],
            ],
        ])->assertSessionHasNoErrors();

        $shift = openShift($this, $this->owner);
        $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [
            ['product_id' => ($this->p)('Telur Besar')->id, 'qty' => 10, 'unit_price' => 28000],
        ], [['payment_method_id' => paymentMethod($this->owner)->id, 'amount' => 280000]]))->assertCreated();

        $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => 5000, 'spent_on' => now('Asia/Jakarta')->toDateString(), 'note' => 'bensin'])->assertSessionHasNoErrors();
    };
});

afterEach(fn () => Carbon::setTestNow());

it('menghitung untung bersih setelah modal telur, pecah saat sortir, dan pengeluaran', function () {
    ($this->buyFromFarmer)();
    ($this->sortAndSell)();

    $overview = asTenant($this->tenant, fn () => app(EggSummaryService::class)->overview('Asia/Jakarta'));
    $today = $overview['periods']['today'];

    // Modal Telur Besar hasil sortir 24.807/kg → 10 kg = 248.070; untung kotor 31.930; pecah 0,5 kg = 12.000
    expect($today['sales'])->toBe(280_000)
        ->and($today['cogs'])->toBe(248_070)
        ->and($today['gross_profit'])->toBe(31_930)
        ->and($today['sorting_loss'])->toBe(12_000)
        ->and($today['expenses'])->toBe(5_000)
        ->and($today['net_profit'])->toBe(14_930)
        ->and($today['cash_in'])->toBe(280_000)
        ->and($today['cash_out'])->toBe(1_005_000)
        ->and($today['paid_farmers'])->toBe(1_000_000)
        ->and($today['kg_sold'])->toBe('10')
        ->and($today['kg_bought'])->toBe('100')
        ->and($today['sorting'])->toMatchArray(['count' => 1, 'input_kg' => '40', 'shrinkage_kg' => '0,5', 'shrinkage_percent' => 1.3])
        ->and($today['eggs'][0])->toMatchArray(['name' => 'Telur Besar', 'qty' => '10', 'revenue' => 280_000, 'profit' => 31_930])
        ->and($overview['periods']['week']['net_profit'])->toBe(14_930)
        ->and($overview['periods']['month']['net_profit'])->toBe(14_930)
        ->and($today['previous']['label'])->toBe('Kemarin')
        ->and($today['change']['net_profit'])->toBeNull();
});

it('membandingkan dengan periode sebelumnya dan menghitung telur rusak sebagai rugi', function () {
    ($this->buyFromFarmer)();
    ($this->sortAndSell)();

    Carbon::setTestNow(Carbon::parse('2026-10-17 10:00:00', 'Asia/Jakarta'));
    $this->actingAs($this->owner)->post(route('stock.adjust.store'), [
        'type' => 'out', 'reason' => 'rusak', 'items' => [['product_id' => ($this->p)('Telur Campur')->id, 'qty' => '2']],
    ])->assertSessionHasNoErrors();

    $today = asTenant($this->tenant, fn () => app(EggSummaryService::class)->overview('Asia/Jakarta'))['periods']['today'];

    expect($today['damaged_loss'])->toBe(48_000)
        ->and($today['net_profit'])->toBe(-48_000)
        ->and($today['previous']['net_profit'])->toBe(14_930)
        ->and($today['change']['net_profit'])->toBe(-421.5);
});

it('menampilkan utang ke peternak yang lewat jatuh tempo dan piutang pembeli', function () {
    ($this->buyFromFarmer)('100', 1_000_000, now('Asia/Jakarta')->subDay()->toDateString());
    asTenant($this->tenant, function () {
        $customer = Customer::create(['uuid' => (string) Str::uuid(), 'name' => 'Bu Ketut', 'phone' => '6281277778888']);
        Receivable::create(['customer_id' => $customer->id, 'type' => 'tempo', 'amount' => 300_000, 'paid_amount' => 100_000, 'due_date' => now()->addDays(3)->toDateString()]);
    });

    $this->actingAs($this->owner)->get(route('egg.summary'))->assertInertia(fn (Assert $page) => $page
        ->component('Pengepul/Summary', false)
        ->where('payables.total', 1_400_000)
        ->where('payables.suppliers.0.name', 'Pak Wayan')
        ->where('payables.suppliers.0.overdue', true)
        ->where('receivables.total', 200_000)
        ->where('receivables.customers.0.name', 'Bu Ketut')
        ->where('periods.today.paid_farmers', 1_000_000)
        ->where('outlet', 'semua'));
});

it('ringkasan hanya untuk yang boleh melihat laporan dan tidak bocor ke usaha lain', function () {
    ($this->buyFromFarmer)('100', 500_000);
    ($this->sortAndSell)();

    $kasir = addStaff($this->owner, 'kasir');
    $this->actingAs($kasir)->get(route('egg.summary'))->assertForbidden();

    $other = registerTenant('pengepul_telur', phone: '6281255556666', name: 'Pengepul Lain');
    $this->actingAs($other)->get(route('egg.summary'))->assertInertia(fn (Assert $page) => $page
        ->where('periods.today.sales', 0)
        ->where('periods.today.sorting_loss', 0)
        ->where('periods.today.paid_farmers', 0)
        ->where('payables.total', 0)
        ->where('receivables.total', 0));

    $this->actingAs(registerTenant('coffee_shop', phone: '6281299990000'))->get(route('egg.summary'))->assertRedirect();
});

it('laporan WA malam pengepul memakai untung setelah telur pecah dan menyebut utang peternak', function () {
    ($this->buyFromFarmer)('100', 1_000_000, now('Asia/Jakarta')->subDay()->toDateString());
    ($this->sortAndSell)();

    Carbon::setTestNow(Carbon::parse('2026-10-16 21:05:00', 'Asia/Jakarta'));
    Artisan::call('hermes:owner-reports');

    $body = asTenant($this->tenant, fn () => WhatsappMessage::where('template_code', 'daily_report')->sole()->body);
    expect($body)->toContain('Telur terjual: *10 kg*')
        ->toContain('Sortir: 40 kg, pecah 0,5 kg (Rp12.000)')
        ->toContain('Untung bersih hari ini: *Rp14.930*')
        ->toContain('Telur Besar: 10 kg, untung Rp31.930')
        ->toContain('Utang ke peternak: *Rp1.400.000*')
        ->toContain('Pak Wayan Rp1.400.000 sudah lewat jatuh tempo');
});
