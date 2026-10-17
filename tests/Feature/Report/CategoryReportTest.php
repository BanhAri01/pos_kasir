<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Models\Outlet;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\SaleService;
use App\Modules\Report\Services\ReportFilter;
use App\Modules\Report\Services\ReportService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function reportFor($owner, string $method): array
{
    $today = now('Asia/Jakarta')->toDateString();

    return asTenant($owner->tenant, fn () => app(ReportService::class)->{$method}(ReportFilter::make($today, $today, 'Asia/Jakarta', [outletsOf($owner)->first()->id])));
}

function categoriesFor($owner): array
{
    return collect(reportFor($owner, 'categories'))->keyBy('name')->all();
}

beforeEach(function () {
    $this->owner = registerTenant('warung_makan');
    $this->cash = paymentMethod($this->owner);
    $this->shift = openShift($this, $this->owner);
    $this->nasgor = productNamed($this->owner, 'Nasi Goreng'); // Makanan 15.000, modal 7.000
    $this->teh = productNamed($this->owner, 'Es Teh Manis');   // Minuman 5.000, modal 1.200
});

it('membagi diskon nota ke tiap kategori sesuai nilainya', function () {
    sell($this, $this->owner, $this->shift,
        [['product_id' => $this->nasgor->id, 'qty' => 2], ['product_id' => $this->teh->id, 'qty' => 2]],
        [['payment_method_id' => $this->cash->id, 'amount' => 36000]],
        ['discount_type' => 'amount', 'discount_value' => 4000],
    )->assertCreated();

    $c = categoriesFor($this->owner);
    expect($c['Makanan'])->toMatchArray(['revenue' => 27000, 'cost' => 14000, 'profit' => 13000, 'margin' => 48.1, 'share' => 75.0, 'transactions' => 1])
        ->and($c['Minuman'])->toMatchArray(['revenue' => 9000, 'cost' => 2400, 'profit' => 6600, 'margin' => 73.3, 'share' => 25.0])
        ->and(array_key_first($c))->toBe('Makanan');
});

it('jumlah semua kategori selalu sama dengan penjualan bersih dan modal di Ringkasan', function () {
    asTenant($this->owner->tenant, fn () => Outlet::query()->first()->update(['tax_rate_bp' => 1100, 'service_charge_bp' => 500]));

    sell($this, $this->owner, $this->shift,
        [['product_id' => $this->nasgor->id, 'qty' => 3], ['product_id' => $this->teh->id, 'qty' => 1]],
        [['payment_method_id' => $this->cash->id, 'amount' => 100000]],
        ['discount_type' => 'percent', 'discount_value' => 700],
    )->assertCreated();
    $uuid = sell($this, $this->owner, $this->shift,
        [['product_id' => $this->nasgor->id, 'qty' => 1], ['product_id' => $this->teh->id, 'qty' => 3, 'discount_amount' => 1000]],
        [['payment_method_id' => $this->cash->id, 'amount' => 100000]],
        ['discount_type' => 'amount', 'discount_value' => 2333],
    )->assertCreated()->json('data.uuid');

    $item = asTenant($this->owner->tenant, fn () => Sale::where('uuid', $uuid)->first()->items()->where('product_id', $this->teh->id)->first());
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.refund', $uuid), [
        'uuid' => (string) Str::uuid(), 'reason' => 'tumpah', 'items' => [['sale_item_id' => $item->id, 'qty' => 1]], 'restock' => false,
    ])->assertSuccessful();

    $c = categoriesFor($this->owner);
    $s = reportFor($this->owner, 'summary');

    expect(array_sum(array_column($c, 'revenue')))->toBe($s['net_sales'])
        ->and(array_sum(array_column($c, 'cost')))->toBe($s['cogs'])
        ->and(array_sum(array_column($c, 'profit')))->toBe($s['gross_profit'])
        ->and(round(array_sum(array_column($c, 'share'))))->toEqual(100.0);
});

it('tidak ikut menghitung penjualan usaha lain', function () {
    $other = registerTenant('warung_makan', name: 'Warung Lain');
    $otherNasgor = productNamed($other, 'Nasi Goreng');
    $otherCash = paymentMethod($other);
    asTenant($other->tenant, function () use ($other, $otherNasgor, $otherCash) {
        $shift = Shift::create(['outlet_id' => outletsOf($other)->first()->id, 'uuid' => (string) Str::uuid(), 'user_id' => $other->id, 'opened_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        app(SaleService::class)->record(salePayload($shift->uuid, [['product_id' => $otherNasgor->id, 'qty' => 5]], [['payment_method_id' => $otherCash->id, 'amount' => 75000]]), $other);
    });
    sell($this, $this->owner, $this->shift, [['product_id' => $this->teh->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 5000]])->assertCreated();

    expect(categoriesFor($this->owner))->toHaveCount(1)
        ->and(categoriesFor($this->owner)['Minuman']['revenue'])->toBe(5000);
});

it('menampilkan tab Kategori dan sheet Per Kategori di Excel', function () {
    sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 1], ['product_id' => $this->teh->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 20000]])->assertCreated();

    $this->actingAs($this->owner)->get(route('reports.index', ['tab' => 'kategori']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Reports/Index')->where('tab', 'kategori')
            ->has('categories', 2)->where('categories.0.name', 'Makanan')->where('categories.0.revenue', 15000)->where('categories.0.share', 75));

    $files = xlsxEntries($this->actingAs($this->owner)->get(route('reports.export'))->assertOk()->getContent());
    expect($files['xl/workbook.xml'])->toContain('name="Per Kategori"')
        ->and($files['xl/worksheets/sheet4.xml'])->toContain('Makanan')->toContain('Porsi omzet');
});

it('kasir tidak bisa membuka laporan per kategori', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)->get(route('reports.index', ['tab' => 'kategori']))->assertForbidden();
});

function xlsxEntries(string $zip): array
{
    $files = [];
    $offset = 0;
    while (substr($zip, $offset, 4) === "PK\x03\x04") {
        $h = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr($zip, $offset + 4, 26));
        $name = substr($zip, $offset + 30, $h['nlen']);
        $data = substr($zip, $offset + 30 + $h['nlen'] + $h['elen'], $h['csize']);
        $files[$name] = $h['method'] === 8 ? gzinflate($data) : $data;
        $offset += 30 + $h['nlen'] + $h['elen'] + $h['csize'];
    }

    return $files;
}
