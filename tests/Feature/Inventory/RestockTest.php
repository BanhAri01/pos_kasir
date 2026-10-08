<?php

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->outlet = outletsOf($this->owner)->first();
    [$this->gula, $this->beras, $this->sabun] = asTenant($this->tenant, fn () => [
        Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Gula 1 kg', 'type' => 'goods', 'price' => 17000, 'cost_price' => 15000, 'track_stock' => true, 'min_stock' => '10']),
        Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Beras 5 kg', 'type' => 'goods', 'price' => 75000, 'cost_price' => 68000, 'track_stock' => true, 'min_stock' => '5']),
        Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Sabun', 'type' => 'goods', 'price' => 4000, 'cost_price' => 3000, 'track_stock' => true, 'min_stock' => '3']),
    ]);

    DB::table('stocks')->insert([
        ['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'product_id' => $this->gula->id, 'qty' => 4, 'avg_cost' => 15000, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'product_id' => $this->beras->id, 'qty' => 2, 'avg_cost' => 68000, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'product_id' => $this->sabun->id, 'qty' => 50, 'avg_cost' => 3000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $supplierId = DB::table('suppliers')->insertGetId(['tenant_id' => $this->tenant->id, 'name' => 'UD Sumber Rejeki', 'phone' => '6281355556666', 'created_at' => now(), 'updated_at' => now()]);
    $purchaseId = DB::table('purchases')->insertGetId([
        'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'uuid' => (string) Str::uuid(), 'number' => 'B-1', 'supplier_id' => $supplierId,
        'purchased_on' => now()->toDateString(), 'total' => 150000, 'paid_amount' => 150000, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_items')->insert(['purchase_id' => $purchaseId, 'product_id' => $this->gula->id, 'qty' => 10, 'conversion_qty' => 1, 'unit_cost' => 15000, 'subtotal' => 150000]);
});

it('mengelompokkan barang yang stoknya menipis per pemasok terakhir', function () {
    $this->actingAs($this->owner)->get(route('restock.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Stock/Restock')
            ->has('groups', 2)
            ->where('groups.0.supplier', 'UD Sumber Rejeki')
            ->where('groups.0.phone', '6281355556666')
            ->where('groups.0.items.0.name', 'Gula 1 kg')
            ->where('groups.0.items.0.suggest', 16)
            ->where('groups.1.supplier', 'Belum ada pemasok')
            ->where('groups.1.items.0.name', 'Beras 5 kg'));
});

it('tidak menampilkan barang yang stoknya masih aman', function () {
    $this->actingAs($this->owner)->get(route('restock.index'))
        ->assertInertia(fn ($page) => $page->where('groups', fn ($groups) => ! collect($groups)->flatMap(fn ($g) => $g['items'])->contains('name', 'Sabun')));
});

it('kasir tanpa izin stok tidak bisa membuka daftar belanja', function () {
    $this->actingAs(addStaff($this->owner))->get(route('restock.index'))->assertForbidden();
});
