<?php

use App\Modules\Catalog\Models\Product;

beforeEach(function () {
    $this->owner = registerTenant('pengepul_telur', name: 'Pengepul Telur Jaya');
    $this->tenant = $this->owner->tenant;
    $this->p = fn (string $name) => asTenant($this->tenant, fn () => Product::where('name', $name)->first());
});

it('data awal kasir pengepul menyebut telur campur dan jenis hasil sortir', function () {
    $eggs = $this->actingAs($this->owner)->getJson(route('pos.api.bootstrap'))->assertOk()->json('eggs');

    expect($eggs['mixed'])->toBe([($this->p)('Telur Campur')->id])
        ->and($eggs['grades'])->toBe([($this->p)('Telur Besar')->id, ($this->p)('Telur Kecil')->id, ($this->p)('Telur Retak')->id]);

    $warung = registerTenant('warung_makan', phone: '6281200001111');
    expect($this->actingAs($warung)->getJson(route('pos.api.bootstrap'))->json('eggs'))->toBeNull();
});

it('kasir bisa sortir dari layar kasir dan menerima stok terbaru tanpa melihat modal', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $response = $this->actingAs($kasir)->postJson(route('egg.sortings.store'), [
        'input_qty' => '12',
        'outputs' => [
            ['product_id' => ($this->p)('Telur Besar')->id, 'qty' => '10'],
            ['product_id' => ($this->p)('Telur Retak')->id, 'qty' => '1,5'],
        ],
        'note' => 'Dari kasir untuk Telur Besar',
    ])->assertCreated();

    $stocks = collect($response->json('stocks'))->keyBy('product_id');
    expect($response->json('message'))->toContain('tersimpan')
        ->and($stocks[($this->p)('Telur Campur')->id])->toMatchArray(['stock_raw' => 88, 'stock' => '88'])
        ->and($stocks[($this->p)('Telur Besar')->id])->toMatchArray(['stock_raw' => 10, 'stock' => '10'])
        ->and($stocks[($this->p)('Telur Retak')->id]['stock'])->toBe('1,5')
        ->and(json_encode($response->json()))->not->toContain('cost');
});

it('sortir dari kasir menolak hasil lebih berat dari telur campur dengan pesan jelas', function () {
    $this->actingAs($this->owner)->postJson(route('egg.sortings.store'), [
        'input_qty' => '5',
        'outputs' => [['product_id' => ($this->p)('Telur Besar')->id, 'qty' => '6']],
    ])->assertUnprocessable()->assertJsonValidationErrors('outputs');
});

it('sortir dari kasir tidak bisa memakai telur usaha lain', function () {
    $other = registerTenant('pengepul_telur', phone: '6281255556666', name: 'Pengepul Lain');
    $foreign = asTenant($other->tenant, fn () => Product::where('name', 'Telur Besar')->first());

    $this->actingAs($this->owner)->postJson(route('egg.sortings.store'), [
        'input_qty' => '5',
        'outputs' => [['product_id' => $foreign->id, 'qty' => '4']],
    ])->assertUnprocessable()->assertJsonValidationErrors('outputs.0.product_id');
});
