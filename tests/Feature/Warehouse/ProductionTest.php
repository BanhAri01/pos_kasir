<?php

use App\Models\Outlet;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Warehouse\Models\ProductionFormula;
use App\Modules\Warehouse\Models\ProductionOrder;

beforeEach(function () {
    $this->owner = registerTenant('gudang_pakan', name: 'Gudang Pakan Sejahtera');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->p = fn (string $name) => asTenant($this->tenant, fn () => Product::where('name', $name)->first());
    $this->formula = fn (string $name) => asTenant($this->tenant, fn () => ProductionFormula::where('name', $name)->first());
});

function warehouseStock($test, string $name, ?int $outletId = null): ?Stock
{
    $product = ($test->p)($name);

    return asTenant($test->tenant, fn () => Stock::where('outlet_id', $outletId ?? $test->outletId)->where('product_id', $product->id)->first());
}

it('menyiapkan resep contoh, bahan kemas, dan barang yang dicatat batch-nya', function () {
    expect(asTenant($this->tenant, fn () => ProductionFormula::orderBy('name')->pluck('name')->all()))
        ->toBe(['Giling Jagung', 'Kemas Jagung Karung 25 kg', 'Racik Pakan Layer'])
        ->and(($this->p)('Karung Kosong 25 kg')->is_packaging)->toBeTrue()
        ->and(($this->p)('Karung Kosong 25 kg')->type)->toBe('ingredient')
        ->and(($this->p)('Konsentrat Layer')->track_batch)->toBeTrue()
        ->and(($this->p)('Jagung Pipil')->track_batch)->toBeFalse();
});

it('kemas ulang: curah + karung kosong + benang berkurang, karungan bertambah dengan modal lengkap', function () {
    $this->actingAs($this->owner)
        ->post(route('warehouse.repack.store'), ['formula_id' => ($this->formula)('Kemas Jagung Karung 25 kg')->id, 'batches' => '10'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(warehouseStock($this, 'Jagung Pipil')->qty)->toBe('2250.000')
        ->and(warehouseStock($this, 'Karung Kosong 25 kg')->qty)->toBe('190.000')
        ->and(warehouseStock($this, 'Benang Jahit Karung')->qty)->toBe('9.800')
        ->and(warehouseStock($this, 'Jagung Karung 25 kg')->qty)->toBe('30.000');

    $order = asTenant($this->tenant, fn () => ProductionOrder::latest('id')->first());
    // Jagung 250 kg x 5.200 + 10 karung x 1.500 + 0,2 roll x 15.000 = 1.318.000 -> 131.800 per karung
    expect($order->kind)->toBe('repack')
        ->and($order->materials_cost)->toBe(1_300_000)
        ->and($order->packaging_cost)->toBe(18_000)
        ->and($order->unit_cost)->toBe(131_800)
        ->and($order->shrinkage_qty)->toBe('0.000');

    // Modal rata-rata karungan: 20 karung @135.000 + 10 karung @131.800
    expect(warehouseStock($this, 'Jagung Karung 25 kg')->avg_cost)->toBe(133_933);
});

it('kemas ulang dengan jumlah terpakai berbeda mencatat susut', function () {
    $formula = ($this->formula)('Kemas Jagung Karung 25 kg');
    $jagung = ($this->p)('Jagung Pipil');

    $this->actingAs($this->owner)
        ->post(route('warehouse.repack.store'), [
            'formula_id' => $formula->id,
            'batches' => '10',
            'inputs' => [['product_id' => $jagung->id, 'actual_qty' => '252,5']],
        ])
        ->assertSessionHasNoErrors();

    $order = asTenant($this->tenant, fn () => ProductionOrder::latest('id')->first());
    expect($order->shrinkage_qty)->toBe('2.500')
        ->and(warehouseStock($this, 'Jagung Pipil')->qty)->toBe('2247.500');
});

it('olah / racik pakan: bahan aktual, hasil aktual, susut, biaya olah, dan batch hasil', function () {
    $formula = ($this->formula)('Racik Pakan Layer');
    $ids = fn (string $name) => ($this->p)($name)->id;

    $this->actingAs($this->owner)
        ->post(route('warehouse.production.store'), [
            'formula_id' => $formula->id,
            'batches' => '2',
            'inputs' => [
                ['product_id' => $ids('Jagung Pipil'), 'actual_qty' => '100'],
                ['product_id' => $ids('Konsentrat Layer'), 'actual_qty' => '70'],
                ['product_id' => $ids('Dedak Padi'), 'actual_qty' => '30'],
            ],
            'output_qty' => '196',
            'labor_cost' => 50000,
            'utility_cost' => 20000,
            'batch_no' => 'PR-01',
            'expires_at' => now()->addMonths(3)->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $order = asTenant($this->tenant, fn () => ProductionOrder::with('items')->latest('id')->first());
    // Bahan: 100 x 5.200 + 70 x 8.200 + 30 x 3.000 = 1.184.000; + biaya olah 70.000 = 1.254.000 / 196 kg
    expect($order->kind)->toBe('production')
        ->and($order->planned_output_qty)->toBe('200.000')
        ->and($order->output_qty)->toBe('196.000')
        ->and($order->shrinkage_qty)->toBe('4.000')
        ->and($order->materials_cost)->toBe(1_184_000)
        ->and($order->processingCost())->toBe(70_000)
        ->and($order->unit_cost)->toBe(6398)
        ->and($order->items->where('role', 'output')->first()->actual_qty)->toBe('196.000');

    $hasil = warehouseStock($this, 'Pakan Racik Layer');
    expect($hasil->qty)->toBe('196.000')
        ->and($hasil->avg_cost)->toBe(6398)
        ->and(warehouseStock($this, 'Konsentrat Layer')->qty)->toBe('430.000');

    $batch = asTenant($this->tenant, fn () => StockBatch::where('batch_no', 'PR-01')->first());
    expect($batch->qty)->toBe('196.000')->and($batch->expires_at)->not->toBeNull();

    $this->actingAs($this->owner)
        ->get(route('warehouse.orders.show', $order->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Warehouse/OrderShow')->where('order.shrinkage', '4')->where('order.unit_cost', 6398));
});

it('bongkar karung jadi curah, karung yang masih bagus kembali ke stok', function () {
    $this->actingAs($this->owner)
        ->post(route('warehouse.unpack.store'), [
            'formula_id' => ($this->formula)('Kemas Jagung Karung 25 kg')->id,
            'qty' => '2',
            'received_qty' => '49,5',
            'reuse_packaging' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(warehouseStock($this, 'Jagung Karung 25 kg')->qty)->toBe('18.000')
        ->and(warehouseStock($this, 'Jagung Pipil')->qty)->toBe('2549.500')
        ->and(warehouseStock($this, 'Karung Kosong 25 kg')->qty)->toBe('202.000')
        ->and(warehouseStock($this, 'Benang Jahit Karung')->qty)->toBe('10.000'); // benang tidak kembali

    $order = asTenant($this->tenant, fn () => ProductionOrder::latest('id')->first());
    // (2 x 135.000 - 2 karung x 1.500) / 49,5 kg
    expect($order->kind)->toBe('unpack')
        ->and($order->shrinkage_qty)->toBe('0.500')
        ->and($order->unit_cost)->toBe(5394);
});

it('membuat resep dan menolak hasil yang juga jadi bahan', function () {
    $jagung = ($this->p)('Jagung Pipil');
    $giling = ($this->p)('Jagung Giling');

    $this->actingAs($this->owner)
        ->post(route('warehouse.formulas.store'), [
            'kind' => 'production', 'name' => 'Salah', 'output_product_id' => $giling->id, 'output_qty' => '100',
            'items' => [['product_id' => $giling->id, 'qty' => '100']],
        ])
        ->assertSessionHasErrors(['items' => 'Barang hasil tidak boleh sekaligus jadi bahannya.']);

    $this->actingAs($this->owner)
        ->post(route('warehouse.formulas.store'), [
            'kind' => 'production', 'name' => 'Giling Halus', 'output_product_id' => $giling->id, 'output_qty' => '97,5',
            'items' => [['product_id' => $jagung->id, 'qty' => '100']],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('warehouse.formulas'));

    $formula = ($this->formula)('Giling Halus');
    expect($formula->output_qty)->toBe('97.500')->and($formula->items()->count())->toBe(1);
});

it('batch keluar sesuai kedaluwarsa terdekat (FEFO) dan ikut pindah saat kirim stok', function () {
    $konsentrat = ($this->p)('Konsentrat Layer');

    $this->actingAs($this->owner)->post(route('purchases.store'), [
        'purchased_on' => now()->toDateString(),
        'paid_amount' => 0,
        'items' => [
            ['product_id' => $konsentrat->id, 'qty' => '100', 'unit_cost' => 8000, 'batch_no' => 'K-LAMA', 'expires_at' => now()->addDays(60)->toDateString(), 'moisture' => '12,5'],
        ],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('purchases.store'), [
        'purchased_on' => now()->toDateString(),
        'paid_amount' => 0,
        'items' => [
            ['product_id' => $konsentrat->id, 'qty' => '100', 'unit_cost' => 8000, 'batch_no' => 'K-BARU', 'expires_at' => now()->addDays(20)->toDateString()],
        ],
    ])->assertSessionHasNoErrors();

    $batch = fn (string $no, ?int $outletId = null) => asTenant($this->tenant, fn () => StockBatch::where('batch_no', $no)->where('outlet_id', $outletId ?? $this->outletId)->first());
    expect($batch('K-LAMA')->moisture)->toBe('12.50');

    // Keluar 150 kg: K-BARU (kedaluwarsa 20 hari) habis duluan, lalu K-LAMA.
    $this->actingAs($this->owner)->post(route('stock.adjust.store'), [
        'type' => 'out', 'reason' => 'rusak', 'items' => [['product_id' => $konsentrat->id, 'qty' => '150']],
    ])->assertSessionHasNoErrors();

    expect($batch('K-BARU')->qty)->toBe('0.000')
        ->and($batch('K-LAMA')->qty)->toBe('50.000');

    // Stok awal contoh (tanpa nomor batch, tanpa kedaluwarsa) belum tersentuh.
    $tanpa = asTenant($this->tenant, fn () => StockBatch::where('product_id', $konsentrat->id)->where('batch_no', 'like', 'TANPA-%')->first());
    expect($tanpa->qty)->toBe('500.000');

    // Kirim 30 kg ke gudang lain: nomor batch & kedaluwarsa ikut pindah.
    $this->actingAs($this->owner)->post(route('outlets.store'), ['name' => 'Gudang Dua', 'type' => 'warehouse'])->assertSessionHasNoErrors();
    $gudang = asTenant($this->tenant, fn () => Outlet::where('name', 'Gudang Dua')->first());

    $this->actingAs($this->owner)->post(route('stock.transfers.store'), [
        'to_outlet_id' => $gudang->id, 'items' => [['product_id' => $konsentrat->id, 'qty' => '30']],
    ])->assertSessionHasNoErrors();
    $transfer = asTenant($this->tenant, fn () => StockTransfer::latest('id')->first());
    $this->actingAs($this->owner)->post(route('stock.transfers.receive', $transfer->id))->assertSessionHasNoErrors();

    expect($batch('K-LAMA')->qty)->toBe('20.000')
        ->and($batch('K-LAMA', $gudang->id)->qty)->toBe('30.000')
        ->and($batch('K-LAMA', $gudang->id)->expires_at->toDateString())->toBe($batch('K-LAMA')->expires_at->toDateString());

    $this->actingAs($this->owner)
        ->get(route('warehouse.batches'))
        ->assertInertia(fn ($page) => $page->component('Warehouse/Batches')
            ->where('groups', fn ($groups) => collect($groups)->firstWhere('product', 'Konsentrat Layer')['batches'][0]['batch_no'] === 'K-LAMA'));
});

it('menampilkan beranda gudang: stok per gudang, hampir habis, kedaluwarsa, susut, dan HPP', function () {
    $this->actingAs($this->owner)
        ->get(route('warehouse.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/Dashboard')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('bulk', fn ($rows) => collect($rows)->firstWhere('name', 'Jagung Pipil')['total']['packs'] === '50 karung')
                ->has('low')
                ->where('expiring', [])
                ->where('shrinkage.value', 0)
                ->where('costs', fn ($costs) => collect($costs)->firstWhere('name', 'Jagung Karung 25 kg')['margin'] === 165000 - 135000)));
});

it('menyembunyikan fitur gudang dari usaha yang tidak memakainya', function () {
    $toko = registerTenant('toko_kelontong');

    $this->actingAs($toko)->get(route('warehouse.dashboard'))->assertNotFound();
    $this->actingAs($toko)->get(route('warehouse.repack'))->assertRedirect(route('dashboard'));
    $this->actingAs($toko)->get(route('warehouse.formulas'))->assertNotFound();
});

it('kasir tanpa izin stok tidak bisa mengolah', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)
        ->post(route('warehouse.repack.store'), ['formula_id' => ($this->formula)('Kemas Jagung Karung 25 kg')->id, 'batches' => '1'])
        ->assertForbidden();
});
