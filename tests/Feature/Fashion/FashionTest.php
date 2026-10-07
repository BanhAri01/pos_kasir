<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Promotion;
use App\Modules\Catalog\Services\VariantService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\Sale;
use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('toko_baju', name: 'Butik Melati');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->variant = fn (string $name) => productNamed($this->owner, $name);
    $this->stock = fn (Product $p) => asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $p));
});

/** Jual lewat API kasir, kembalikan Sale. */
function sellAt($test, string $shift, array $items, int $paid): Sale
{
    $payload = salePayload($shift, $items, [['payment_method_id' => paymentMethod($test->owner)->id, 'amount' => $paid]]);
    $test->actingAs($test->owner)->postJson(route('pos.api.sales.store'), $payload)->assertCreated();

    return asTenant($test->tenant, fn () => Sale::with('items')->where('uuid', $payload['uuid'])->first());
}

it('menyiapkan toko baju: model dengan varian ukuran x warna, SKU & barcode otomatis', function () {
    expect($this->tenant->hasModule('variant_matrix'))->toBeTrue()
        ->and($this->tenant->hasModule('promotions'))->toBeTrue()
        ->and($this->tenant->hasModule('returns_exchange'))->toBeTrue()
        ->and($this->tenant->hasModule('consignment'))->toBeFalse(); // opsional

    $model = productNamed($this->owner, 'Kaos Polos Cotton');
    $variants = asTenant($this->tenant, fn () => $model->variants()->get());

    expect($model->has_variants)->toBeTrue()
        ->and($model->tracksStock())->toBeFalse()
        ->and($variants)->toHaveCount(12)
        ->and($variants->pluck('name'))->toContain('Kaos Polos Cotton - M / Hitam')
        ->and($variants->every(fn ($v) => strlen($v->barcode) === 13 && str_starts_with($v->barcode, '20')))->toBeTrue()
        ->and($variants->firstWhere('name', 'Kaos Polos Cotton - M / Hitam')->code)->toEndWith('-M-HIT')
        ->and(($this->stock)(($this->variant)('Kaos Polos Cotton - M / Hitam')))->toBe('5.000')
        ->and(asTenant($this->tenant, fn () => PriceLevel::pluck('name')->all()))->toBe(['Reseller']);
});

it('membuat semua kombinasi dari tabel, menyembunyikan kombinasi yang dihapus, dan menolak barcode ganda', function () {
    $model = asTenant($this->tenant, fn () => Product::create([
        'uuid' => (string) Str::uuid(), 'name' => 'Hijab Segi Empat', 'type' => 'goods', 'price' => 35000, 'cost_price' => 20000, 'track_stock' => true,
    ]));

    $options = [['name' => 'Warna', 'values' => ['Hitam', 'Mocca']], ['name' => 'Bahan', 'values' => ['Voal', 'Paris']]];
    $rows = array_map(fn ($v) => ['values' => $v, 'price' => 35000, 'initial_stock' => '4', 'min_stock' => '1'], VariantService::combinations($options));

    $this->actingAs($this->owner)
        ->put(route('products.variants.update', $model->id), ['options' => $options, 'rows' => $rows, 'auto_barcode' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('products.index'));

    expect(asTenant($this->tenant, fn () => $model->variants()->where('is_active', true)->count()))->toBe(4)
        ->and(($this->stock)(($this->variant)('Hijab Segi Empat - Mocca / Paris')))->toBe('4.000');

    // Bahan "Paris" dihapus: 2 varian disembunyikan, stok tidak dibuat ulang.
    $options[1]['values'] = ['Voal'];
    $rows = array_map(fn ($v) => ['values' => $v, 'price' => 37000], VariantService::combinations($options));
    $this->actingAs($this->owner)->put(route('products.variants.update', $model->id), ['options' => $options, 'rows' => $rows])->assertSessionHasNoErrors();

    expect(asTenant($this->tenant, fn () => $model->variants()->where('is_active', true)->count()))->toBe(2)
        ->and(($this->variant)('Hijab Segi Empat - Hitam / Voal')->price)->toBe(37000)
        ->and(($this->variant)('Hijab Segi Empat - Mocca / Paris')->is_active)->toBeFalse();

    // Barcode yang sudah dipakai barang lain ditolak.
    $taken = ($this->variant)('Kaos Polos Cotton - S / Putih')->barcode;
    $rows[0]['barcode'] = $taken;
    $this->actingAs($this->owner)->put(route('products.variants.update', $model->id), ['options' => $options, 'rows' => $rows])
        ->assertSessionHasErrors('rows');
});

it('daftar barang menampilkan model dengan ringkasan varian, varian muncul saat dicari', function () {
    $this->actingAs($this->owner)
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page
            ->where('products', fn ($products) => collect($products)->whereNotNull('parent_id')->isEmpty())
            ->where('variantSummary', fn ($summary) => collect($summary)->contains(fn ($s) => $s['count'] === 12 && $s['stock'] === '60')));

    $this->actingAs($this->owner)
        ->get(route('products.index', ['q' => 'M / Hitam']))
        ->assertInertia(fn ($page) => $page->where('products.0.name', 'Kaos Polos Cotton - M / Hitam'));
});

it('kasir menjual varian: stok varian berkurang, data varian ikut ke HP kasir', function () {
    $shift = openShift($this, $this->owner);
    $m = ($this->variant)('Kaos Polos Cotton - M / Hitam');

    $this->actingAs($this->owner)->getJson(route('pos.api.bootstrap'))
        ->assertOk()
        ->assertJsonPath('products', fn ($products) => collect($products)->firstWhere('id', $m->id)['parent_id'] === $m->parent_id
            && collect($products)->firstWhere('id', $m->parent_id)['has_variants'] === true);

    sellAt($this, $shift, [['product_id' => $m->id, 'qty' => 2]], 130000);

    expect(($this->stock)($m))->toBe('3.000');
});

it('promo musiman otomatis memotong harga sesuai tanggal, kategori, atau model', function () {
    $shift = openShift($this, $this->owner);
    $kaos = asTenant($this->tenant, fn () => Category::where('name', 'Kaos')->first());
    $chino = productNamed($this->owner, 'Celana Chino');

    $this->actingAs($this->owner)->post(route('promotions.store'), [
        'name' => 'Diskon Kaos', 'type' => 'percent', 'value' => '10', 'scope' => 'categories', 'category_ids' => [$kaos->id],
        'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('promotions.store'), [
        'name' => 'Chino Hemat', 'type' => 'amount', 'value' => '25.000', 'scope' => 'products', 'product_ids' => [$chino->id],
        'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('promotions.store'), [
        'name' => 'Sudah Lewat', 'type' => 'percent', 'value' => '50', 'scope' => 'all',
        'starts_on' => now()->subDays(10)->toDateString(), 'ends_on' => now()->subDays(5)->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(asTenant($this->tenant, fn () => Promotion::where('name', 'Diskon Kaos')->first()->value))->toBe(1000);

    $kaosM = ($this->variant)('Kaos Polos Cotton - M / Hitam');
    $chino30 = ($this->variant)('Celana Chino - 30 / Krem');
    $sale = sellAt($this, $shift, [['product_id' => $kaosM->id, 'qty' => 1], ['product_id' => $chino30->id, 'qty' => 1]], 300000);

    $kaosLine = $sale->items->firstWhere('product_id', $kaosM->id);
    $chinoLine = $sale->items->firstWhere('product_id', $chino30->id);

    expect($kaosLine->unit_price)->toBe(58500)          // 65.000 - 10%
        ->and($kaosLine->meta['promo']['name'])->toBe('Diskon Kaos')
        ->and($chinoLine->unit_price)->toBe(150000)     // 175.000 - 25.000 (promo model berlaku untuk variannya)
        ->and($sale->total)->toBe(208500);

    // Data promo ikut ke HP kasir supaya harga sama saat offline.
    $this->actingAs($this->owner)->getJson(route('pos.api.bootstrap'))
        ->assertJsonCount(2, 'promotions');
});

it('tukar ukuran: stok lama kembali, stok baru berkurang, selisih harga dihitung', function () {
    $shift = openShift($this, $this->owner);
    $m = ($this->variant)('Kaos Polos Cotton - M / Hitam');
    $l = ($this->variant)('Kaos Polos Cotton - L / Hitam');
    $sale = sellAt($this, $shift, [['product_id' => $m->id, 'qty' => 1]], 65000);

    $this->actingAs($this->owner)
        ->get(route('sales.exchange', $sale->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sales/Exchange')->where('sale.items.0.unit_value', 65000));

    $this->actingAs($this->owner)
        ->post(route('sales.exchange.store', $sale->uuid), [
            'returns' => [['sale_item_id' => $sale->items->first()->id, 'qty' => '1']],
            'items' => [['product_id' => $l->id, 'qty' => '1']],
            'reason' => 'Kekecilan',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Harga sama'));

    expect(($this->stock)($m))->toBe('5.000')
        ->and(($this->stock)($l))->toBe('4.000')
        ->and($sale->fresh()->payment_status)->toBe('refunded');

    $newSale = asTenant($this->tenant, fn () => Sale::latest('id')->first());
    expect($newSale->total)->toBe(65000)->and($newSale->note)->toContain($sale->number);
});

it('tukar dengan barang lebih mahal: pembeli menambah kekurangannya', function () {
    $shift = openShift($this, $this->owner);
    $kaos = ($this->variant)('Kaos Polos Cotton - M / Putih');
    $kemeja = ($this->variant)('Kemeja Flanel - L / Merah');
    $sale = sellAt($this, $shift, [['product_id' => $kaos->id, 'qty' => 1]], 65000);

    $this->actingAs($this->owner)
        ->post(route('sales.exchange.store', $sale->uuid), [
            'returns' => [['sale_item_id' => $sale->items->first()->id, 'qty' => '1']],
            'items' => [['product_id' => $kemeja->id, 'qty' => '1']],
            'payment_method_id' => paymentMethod($this->owner, 'qris')->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Pembeli menambah Rp80.000'));

    $newSale = asTenant($this->tenant, fn () => Sale::with('payments')->latest('id')->first());
    expect($newSale->payments->pluck('amount', 'method_type')->sortKeys()->all())->toBe(['cash' => 65000, 'qris' => 80000]);
});

it('tukar barang butuh kasir yang sedang buka', function () {
    $shift = openShift($this, $this->owner);
    $m = ($this->variant)('Kaos Polos Cotton - S / Navy');
    $sale = sellAt($this, $shift, [['product_id' => $m->id, 'qty' => 1]], 65000);
    $this->actingAs($this->owner)->postJson(route('pos.api.shifts.close', $shift), ['counted_cash' => 165000])->assertOk();

    $this->actingAs($this->owner)
        ->post(route('sales.exchange.store', $sale->uuid), [
            'returns' => [['sale_item_id' => $sale->items->first()->id, 'qty' => '1']],
            'items' => [['product_id' => $m->id, 'qty' => '1']],
        ])
        ->assertSessionHasErrors('shift');
});

it('halaman cetak label & barcode otomatis untuk barang yang belum punya', function () {
    $topi = productNamed($this->owner, 'Topi Baseball');
    expect($topi->barcode)->toBeNull();

    $this->actingAs($this->owner)
        ->get(route('labels.index', ['ids' => [$topi->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Products/Labels')->where('preselect', [$topi->id]));

    $this->actingAs($this->owner)->post(route('labels.generate'), ['product_ids' => [$topi->id]])->assertSessionHasNoErrors();

    expect($topi->fresh()->barcode)->toBe(VariantService::internalBarcode($topi->id))
        ->and(strlen($topi->fresh()->barcode))->toBe(13);
});

it('laporan varian: terlaris, per ukuran & warna, dan yang menumpuk', function () {
    $shift = openShift($this, $this->owner);
    sellAt($this, $shift, [
        ['product_id' => ($this->variant)('Kaos Polos Cotton - M / Hitam')->id, 'qty' => 3],
        ['product_id' => ($this->variant)('Kaos Polos Cotton - L / Putih')->id, 'qty' => 1],
    ], 260000);

    $this->actingAs($this->owner)
        ->get(route('reports.variants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Variants')
            ->where('best.0.name', 'Kaos Polos Cotton - M / Hitam')
            ->where('best.0.qty', '3')
            ->where('dimensions', fn ($dims) => collect($dims)->firstWhere('name', 'Ukuran')['rows'][0]['value'] === 'M')
            ->where('slow.0.sold', '0'));
});

it('barang titipan: laporan bagi hasil ke pemilik barang', function () {
    $this->actingAs($this->owner)->put(route('modules.update', 'consignment'), ['enabled' => true])->assertSessionHasNoErrors();
    $this->tenant->refresh()->flushModuleCache();
    expect($this->tenant->hasModule('consignment'))->toBeTrue();

    $owner = asTenant($this->tenant, fn () => Supplier::create(['name' => 'Bu Rina (titip)']));
    $topi = productNamed($this->owner, 'Topi Baseball');

    $this->actingAs($this->owner)->put(route('products.update', $topi->id), [
        'name' => 'Topi Baseball', 'type' => 'goods', 'price' => 45000, 'base_unit_id' => $topi->base_unit_id, 'track_stock' => true,
        'consignor_id' => $owner->id, 'consignment_share' => '20',
    ])->assertSessionHasNoErrors();
    expect($topi->fresh()->consignment_share_bp)->toBe(2000);

    $shift = openShift($this, $this->owner);
    sellAt($this, $shift, [['product_id' => $topi->id, 'qty' => 2]], 90000);

    $this->actingAs($this->owner)
        ->get(route('reports.consignment'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('consignors.0.name', 'Bu Rina (titip)')
            ->where('consignors.0.revenue', 90000)
            ->where('consignors.0.shop_share', 18000)
            ->where('total_payable', 72000));
});
