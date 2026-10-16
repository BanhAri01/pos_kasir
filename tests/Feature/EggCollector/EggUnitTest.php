<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = registerTenant('pengepul_telur', name: 'Pengepul Telur Jaya');
    $this->tenant = $this->owner->tenant;
    $this->p = fn (string $name) => asTenant($this->tenant, fn () => Product::where('name', $name)->first());
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->stockOf = fn (Product $product) => asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $product));
    $this->unitOf = fn (string $product, string $unit) => asTenant($this->tenant, fn () => ProductUnit::where('product_id', ($this->p)($product)->id)->whereHas('unit', fn ($q) => $q->where('name', $unit))->first());
});

it('pemilik mengisi isi peti dan tray sendiri, harganya mengikuti harga jual per kg', function () {
    $this->actingAs($this->owner)->put(route('egg.units.update'), ['units' => [
        ['product_id' => ($this->p)('Telur Campur')->id, 'peti' => '15', 'tray' => '1,8', 'ikat' => ''],
        ['product_id' => ($this->p)('Telur Besar')->id, 'peti' => '15', 'tray' => '', 'ikat' => null],
    ]])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(($this->unitOf)('Telur Campur', 'Peti'))->conversion_qty->toBe('15.000')->price->toBe(390_000)
        ->and(($this->unitOf)('Telur Campur', 'Tray'))->price->toBe(46_800)
        ->and(($this->unitOf)('Telur Campur', 'Ikat'))->toBeNull()
        ->and(($this->unitOf)('Telur Besar', 'Peti')->price)->toBe(420_000)
        ->and(($this->unitOf)('Telur Besar', 'Tray'))->toBeNull();

    $this->actingAs($this->owner)->post(route('egg.prices.store'), ['prices' => [
        ['product_id' => ($this->p)('Telur Besar')->id, 'sell_price' => 29000],
    ]])->assertSessionHasNoErrors();
    expect(($this->unitOf)('Telur Besar', 'Peti')->price)->toBe(435_000);

    $this->actingAs($this->owner)->get(route('egg.types'))->assertInertia(fn (Assert $page) => $page
        ->component('Pengepul/Types', false)
        ->where('units.0.name', 'Telur Campur')
        ->where('units.0.peti', '15')
        ->where('units.0.tray', '1,8')
        ->where('units.0.ikat', null));

    $this->actingAs($this->owner)->put(route('egg.units.update'), ['units' => [
        ['product_id' => ($this->p)('Telur Campur')->id, 'peti' => '', 'tray' => '1,8'],
    ]])->assertSessionHasNoErrors();
    expect(($this->unitOf)('Telur Campur', 'Peti'))->toBeNull();
});

it('kasir menjual per peti dan stok berkurang dalam kg', function () {
    $this->actingAs($this->owner)->put(route('egg.units.update'), ['units' => [['product_id' => ($this->p)('Telur Campur')->id, 'peti' => '15']]])->assertSessionHasNoErrors();
    $peti = ($this->unitOf)('Telur Campur', 'Peti');

    $boot = $this->actingAs($this->owner)->getJson(route('pos.api.bootstrap'))->json('products');
    expect(collect($boot)->firstWhere('name', 'Telur Campur')['units'][0])->toMatchArray(['name' => 'Peti', 'conversion' => 15.0, 'price' => 390_000]);

    $shift = openShift($this, $this->owner);
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => ($this->p)('Telur Campur')->id, 'unit_id' => $peti->id, 'qty' => 2, 'unit_price' => 390_000],
    ], [['payment_method_id' => paymentMethod($this->owner)->id, 'amount' => 780_000]]))->assertCreated()->assertJsonPath('data.total', 780_000);

    expect(($this->stockOf)(($this->p)('Telur Campur')))->toBe('70.000');
});

it('belanja dari peternak per peti menambah stok dalam kg', function () {
    $this->actingAs($this->owner)->put(route('egg.units.update'), ['units' => [['product_id' => ($this->p)('Telur Campur')->id, 'peti' => '15']]])->assertSessionHasNoErrors();
    $supplier = asTenant($this->tenant, fn () => Supplier::create(['name' => 'Pak Wayan']));

    $this->actingAs($this->owner)->post(route('purchases.store'), [
        'supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'paid_amount' => 0, 'due_date' => now()->addWeek()->toDateString(),
        'items' => [['product_id' => ($this->p)('Telur Campur')->id, 'unit_id' => ($this->unitOf)('Telur Campur', 'Peti')->id, 'qty' => 10, 'unit_cost' => 360_000]],
    ])->assertSessionHasNoErrors();

    expect(asTenant($this->tenant, fn () => Purchase::first()->total))->toBe(3_600_000)
        ->and(($this->stockOf)(($this->p)('Telur Campur')))->toBe('250.000');
});

it('isi satuan ditolak untuk barang usaha lain dan berat tidak masuk akal', function () {
    $other = registerTenant('pengepul_telur', phone: '6281255556666', name: 'Pengepul Lain');
    $foreign = asTenant($other->tenant, fn () => Product::where('name', 'Telur Campur')->first());

    $this->actingAs($this->owner)->put(route('egg.units.update'), ['units' => [['product_id' => $foreign->id, 'peti' => '15']]])
        ->assertSessionHasErrors('units.0.product_id');
    $this->actingAs($this->owner)->put(route('egg.units.update'), ['units' => [['product_id' => ($this->p)('Telur Campur')->id, 'peti' => '5000']]])
        ->assertSessionHasErrors('units.0.peti');

    $kasir = addStaff($this->owner, 'kasir');
    $this->actingAs($kasir)->put(route('egg.units.update'), ['units' => [['product_id' => ($this->p)('Telur Campur')->id, 'peti' => '15']]])->assertForbidden();
});
