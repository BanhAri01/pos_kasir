<?php

use App\Models\Outlet;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;

beforeEach(function () {
    $this->owner = registerTenant('gudang_pakan', name: 'Gudang Pakan Makmur');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->jagung = asTenant($this->tenant, fn () => Product::where('name', 'Jagung Pipil')->first());
    $this->dedak = asTenant($this->tenant, fn () => Product::where('name', 'Dedak Padi')->first());
    $this->supplier = asTenant($this->tenant, fn () => Supplier::create(['name' => 'UD Tani Jaya', 'default_pack_weight' => '50']));
});

function warehouseStockRow($test, Product $product, ?int $outletId = null): Stock
{
    return asTenant($test->tenant, fn () => Stock::where('outlet_id', $outletId ?? $test->outletId)->where('product_id', $product->id)->first());
}

it('menyiapkan usaha gudang pakan: modul gudang menyala, barang curah per kg, tipe harga peternak & agen', function () {
    expect($this->tenant->hasModule('weighed_receiving'))->toBeTrue()
        ->and($this->tenant->hasModule('landed_cost'))->toBeTrue()
        ->and($this->tenant->hasModule('multi_warehouse'))->toBeTrue()
        ->and($this->tenant->hasModule('shrinkage'))->toBeTrue()
        ->and($this->tenant->hasModule('base_unit_stock'))->toBeTrue()
        ->and($this->tenant->hasModule('repack'))->toBeTrue()
        ->and($this->tenant->hasModule('variant_matrix'))->toBeFalse(); // khusus toko baju

    expect($this->jagung->pricing_mode)->toBe('per_weight')
        ->and($this->jagung->pack_size)->toBe('50.000')
        ->and($this->jagung->pack_name)->toBe('karung')
        ->and(asTenant($this->tenant, fn () => PriceLevel::orderBy('sort_order')->pluck('name')->all()))->toBe(['Peternak', 'Agen']);
});

it('menampilkan stok dalam kg dan setara karung', function () {
    $this->actingAs($this->owner)
        ->get(route('stock.index', ['q' => 'Jagung Pipil']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products.0.stock', '2500')
            ->where('products.0.pack_equivalent', '50')
            ->where('products.0.pack_name', 'karung'));
});

it('terima barang pakai timbangan: pemasok ditagih sesuai nota, stok bertambah sesuai timbangan, biaya angkut masuk modal', function () {
    $this->actingAs($this->owner)
        ->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchased_on' => now()->toDateString(),
            'paid_amount' => 0,
            'freight_cost' => 100000,
            'unloading_cost' => 50000,
            'items' => [[
                'product_id' => $this->jagung->id,
                'qty' => '1000',
                'unit_cost' => 5000,
                'pack_count' => '20',
                'pack_weight' => '50',
                'received_qty' => '985,5', // diketik dengan koma
            ]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $purchase = asTenant($this->tenant, fn () => Purchase::with('items')->latest('id')->first());
    $item = $purchase->items->first();

    // Utang ke pemasok = nota saja (20 karung x 50 kg x Rp5.000), biaya angkut tidak ikut.
    expect($purchase->total)->toBe(5_000_000)
        ->and($purchase->payment_status)->toBe('unpaid')
        ->and($purchase->freight_cost)->toBe(100000)
        ->and($item->qty)->toBe('1000.000')
        ->and($item->received_qty)->toBe('985.500')
        ->and($item->weight_diff)->toBe('-14.500')
        ->and($item->extra_cost)->toBe(150000)
        // (5.000.000 + 150.000) / 985,5 kg = Rp5.225,77 -> Rp5.226 per kg
        ->and($item->landed_unit_cost)->toBe(5226);

    // Halaman rincian belanja menampilkan berat nota, timbangan, selisih, dan modal.
    $this->actingAs($this->owner)
        ->get(route('purchases.show', $purchase->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Purchasing/PurchaseShow')
            ->where('purchase.items.0.base_unit', 'Kg')
            ->where('purchase.items.0.received_qty', '985,5')
            ->where('purchase.items.0.weight_diff', '-14,5')
            ->where('purchase.items.0.landed_unit_cost', 5226)
            ->where('purchase.extra_costs', ['Ongkos angkut' => 100000, 'Bongkar muat' => 50000]));

    $stock = warehouseStockRow($this, $this->jagung);
    // Stok 2.500 kg (modal 5.200) + 985,5 kg (modal 5.226) -> rata-rata bergerak Rp5.207
    expect($stock->qty)->toBe('3485.500')
        ->and($stock->avg_cost)->toBe(5207);
});

it('membagi biaya tambahan ke beberapa barang sebanding nilainya tanpa selisih pembulatan', function () {
    $this->actingAs($this->owner)
        ->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchased_on' => now()->toDateString(),
            'paid_amount' => 0,
            'freight_cost' => 100001,
            'items' => [
                ['product_id' => $this->jagung->id, 'qty' => '100', 'unit_cost' => 5000],  // Rp500.000
                ['product_id' => $this->dedak->id, 'qty' => '100', 'unit_cost' => 2500],   // Rp250.000
            ],
        ])
        ->assertSessionHasNoErrors();

    $items = asTenant($this->tenant, fn () => Purchase::latest('id')->first()->items()->orderBy('id')->get());

    expect($items->sum('extra_cost'))->toBe(100001)
        ->and($items[0]->extra_cost)->toBe(66667)
        ->and($items[0]->landed_unit_cost)->toBe(5667) // (500.000 + 66.667) / 100
        ->and($items[0]->received_qty)->toBeNull();     // tidak ditimbang
});

it('menerima jumlah belanja dengan koma desimal (1,5)', function () {
    $this->actingAs($this->owner)
        ->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'paid_amount' => 7500,
            'items' => [['product_id' => $this->dedak->id, 'qty' => '1,5', 'unit_cost' => 5000]],
        ])
        ->assertSessionHasNoErrors();

    expect(warehouseStockRow($this, $this->dedak)->qty)->toBe('1501.500');
});

it('mengisi berat per karung bawaan pemasok dan bisa diubah', function () {
    $this->actingAs($this->owner)
        ->get(route('purchases.create'))
        ->assertInertia(fn ($page) => $page
            ->where('suppliers.0.pack_weight', '50')
            ->where('products', fn ($products) => collect($products)->firstWhere('name', 'Jagung Pipil')['pack_size'] === '50'));

    $this->actingAs($this->owner)
        ->put(route('suppliers.update', $this->supplier->id), ['name' => 'UD Tani Jaya', 'pack_weight' => '49,5'])
        ->assertSessionHasNoErrors();

    expect($this->supplier->fresh()->default_pack_weight)->toBe('49.500');
});

it('membuat gudang kedua dan kirim stok membawa modal ke gudang tujuan', function () {
    $this->actingAs($this->owner)
        ->post(route('outlets.store'), ['name' => 'Gudang Timur', 'type' => 'warehouse'])
        ->assertSessionHasNoErrors();

    $gudang = asTenant($this->tenant, fn () => Outlet::where('name', 'Gudang Timur')->first());
    expect($gudang->isWarehouse())->toBeTrue();

    $this->actingAs($this->owner)
        ->post(route('stock.transfers.store'), [
            'to_outlet_id' => $gudang->id,
            'items' => [['product_id' => $this->jagung->id, 'qty' => '100']],
        ])
        ->assertSessionHasNoErrors();

    $transfer = asTenant($this->tenant, fn () => StockTransfer::with('items')->latest('id')->first());
    expect($transfer->items->first()->unit_cost)->toBe(5200);

    $this->actingAs($this->owner)->post(route('stock.transfers.receive', $transfer->id))->assertSessionHasNoErrors();

    $destination = warehouseStockRow($this, $this->jagung, $gudang->id);
    expect($destination->qty)->toBe('100.000')
        ->and($destination->avg_cost)->toBe(5200);
});

it('menyimpan blok gudang dan tempat simpan barang', function () {
    $this->actingAs($this->owner)
        ->post(route('warehouse.locations.store'), ['name' => 'Blok A'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->post(route('warehouse.locations.store'), ['name' => 'Blok A'])
        ->assertSessionHasErrors(['name' => 'Nama blok ini sudah ada di gudang ini.']);

    $blok = asTenant($this->tenant, fn () => WarehouseLocation::first());

    $this->actingAs($this->owner)
        ->put(route('warehouse.locations.assign', $this->jagung->id), ['location_id' => $blok->id])
        ->assertSessionHasNoErrors();

    expect(warehouseStockRow($this, $this->jagung)->location_id)->toBe($blok->id);

    $this->actingAs($this->owner)
        ->get(route('stock.index', ['q' => 'Jagung Pipil']))
        ->assertInertia(fn ($page) => $page->where('products.0.location', 'Blok A'));

    $this->actingAs($this->owner)
        ->get(route('warehouse.locations'))
        ->assertInertia(fn ($page) => $page->component('Warehouse/Locations')->where('locations.0.product_count', 1));
});

it('tidak bisa memakai blok milik usaha lain', function () {
    $other = registerTenant('gudang_pakan', name: 'Gudang Lain');
    $otherOutlet = outletsOf($other)->first();
    $foreign = asTenant($other->tenant, fn () => WarehouseLocation::create(['outlet_id' => $otherOutlet->id, 'name' => 'Blok Z']));

    $this->actingAs($this->owner)
        ->put(route('warehouse.locations.assign', $this->jagung->id), ['location_id' => $foreign->id])
        ->assertSessionHasErrors('location_id');

    $this->actingAs($this->owner)
        ->delete(route('warehouse.locations.destroy', $foreign->id))
        ->assertNotFound();
});

it('mencatat susut kadar air dan kurang saat hitung stok di laporan susut', function () {
    $this->actingAs($this->owner)
        ->post(route('stock.adjust.store'), [
            'type' => 'out', 'reason' => 'susut_air', 'items' => [['product_id' => $this->jagung->id, 'qty' => '10']],
        ])
        ->assertSessionHasNoErrors();

    // Ditimbang ulang: tinggal 2.485 kg (catatan 2.490 kg) -> kurang 5 kg.
    $this->actingAs($this->owner)
        ->post(route('stock.opname.store'), ['items' => [['product_id' => $this->jagung->id, 'counted_qty' => '2485']]])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->get(route('warehouse.reports', ['tab' => 'susut']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/Reports')
            ->where('shrinkage.total_value', 15 * 5200)
            ->where('shrinkage.products.0.name', 'Jagung Pipil')
            ->where('shrinkage.products.0.qty', '15')
            ->where('shrinkage.reasons', fn ($reasons) => collect($reasons)->pluck('label')->sort()->values()->all() === ['Kurang saat hitung stok', 'Susut kadar air']));

    $this->actingAs($this->owner)
        ->get(route('warehouse.reports', ['tab' => 'hitung']))
        ->assertInertia(fn ($page) => $page
            ->where('opnames.0.items.0.diff', '-5')
            ->where('opnames.0.value', -5 * 5200));
});

it('melaporkan selisih timbang per pemasok', function () {
    $this->actingAs($this->owner)->post(route('purchases.store'), [
        'supplier_id' => $this->supplier->id,
        'purchased_on' => now()->toDateString(),
        'paid_amount' => 0,
        'items' => [['product_id' => $this->jagung->id, 'qty' => '500', 'unit_cost' => 5000, 'pack_count' => '10', 'pack_weight' => '50', 'received_qty' => '490']],
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->get(route('warehouse.reports', ['tab' => 'timbang']))
        ->assertInertia(fn ($page) => $page
            ->where('weighing.suppliers.0.name', 'UD Tani Jaya')
            ->where('weighing.suppliers.0.diff', '-10')
            ->where('weighing.suppliers.0.short', true)
            ->where('weighing.total_value', -50000)
            ->where('weighing.items.0.product', 'Jagung Pipil'));
});

it('alasan susut gudang hanya muncul kalau modul Catat Susut menyala', function () {
    $toko = registerTenant('toko_kelontong');

    $this->actingAs($toko)
        ->get(route('stock.adjust', 'keluar'))
        ->assertInertia(fn ($page) => $page->where('reasons', fn ($reasons) => ! collect($reasons)->pluck('value')->contains('susut_air')));

    $this->actingAs($this->owner)
        ->get(route('stock.adjust', 'keluar'))
        ->assertInertia(fn ($page) => $page->where('reasons', fn ($reasons) => collect($reasons)->pluck('value')->contains('susut_air')));

    // Toko tanpa modul gudang tidak bisa membuka halaman gudang.
    $this->actingAs($toko)->get(route('warehouse.reports'))->assertNotFound();
});

it('menyimpan isi 1 karung di form barang', function () {
    $this->actingAs($this->owner)
        ->put(route('products.update', $this->dedak->id), [
            'name' => 'Dedak Padi', 'type' => 'goods', 'price' => 4000, 'base_unit_id' => $this->dedak->base_unit_id,
            'pricing_mode' => 'per_weight', 'track_stock' => true, 'pack_size' => '40', 'pack_name' => 'sak', 'pack_weight_fixed' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($this->dedak->fresh())
        ->pack_size->toBe('40.000')
        ->pack_name->toBe('sak')
        ->pack_weight_fixed->toBeTrue();

    // Karung pemasok (beda-beda) tidak boleh dihitung per karung; karung kemasan sendiri boleh.
    expect($this->jagung->pack_weight_fixed)->toBeFalse()
        ->and(asTenant($this->tenant, fn () => Product::where('name', 'Pakan Racik Layer')->first()->pack_weight_fixed))->toBeTrue();

    $this->actingAs($this->owner)
        ->get(route('stock.opname'))
        ->assertInertia(fn ($page) => $page->where('products', fn ($products) => collect($products)->firstWhere('name', 'Jagung Pipil')['pack_weight_fixed'] === false));
});

it('tetap menghitung modal benar untuk usaha yang tidak memakai timbangan', function () {
    $toko = registerTenant('toko_kelontong');
    $beras = asTenant($toko->tenant, fn () => Product::where('name', 'Beras Premium 5 kg')->first());
    $outletId = outletsOf($toko)->first()->id;

    $this->actingAs($toko)->post(route('purchases.store'), [
        'purchased_on' => now()->toDateString(),
        'paid_amount' => 660000,
        'items' => [['product_id' => $beras->id, 'qty' => '10', 'unit_cost' => 66000]],
    ])->assertSessionHasNoErrors();

    expect(asTenant($toko->tenant, fn () => app(StockService::class)->qty($outletId, $beras)))->toBe('30.000');
    $item = asTenant($toko->tenant, fn () => Purchase::latest('id')->first()->items()->first());
    expect($item->landed_unit_cost)->toBe(66000)->and($item->received_qty)->toBeNull();
});
