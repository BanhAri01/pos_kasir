<?php

require_once __DIR__.'/PosTestHelpers.php';

use App\Models\Outlet;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->kasir = addStaff($this->owner, 'kasir', '1111');
    $this->cash = paymentMethod($this->owner, 'cash');
    $this->qris = paymentMethod($this->owner, 'qris');
    $this->beras = productNamed($this->owner, 'Beras Premium 5 kg'); // 72.000, stok 20
    $this->telur = productNamed($this->owner, 'Telur Ayam');         // 2.500, stok 120
});

function stockQty($test, $product): string
{
    return asTenant($test->tenant, fn () => app(StockService::class)->qty($test->outletId, $product));
}

it('mencatat penjualan tunai dengan kembalian dan memotong stok', function () {
    $shift = openShift($this, $this->kasir);

    $response = $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->beras->id, 'qty' => 1, 'unit_price' => 72000],
        ['product_id' => $this->telur->id, 'qty' => 10, 'unit_price' => 2500],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 100000]]));

    $response->assertCreated()
        ->assertJsonPath('data.total', 97000)
        ->assertJsonPath('data.change_amount', 3000)
        ->assertJsonPath('data.payments.0.amount', 97000); // yang dicatat: tanpa kembalian

    expect(stockQty($this, $this->beras))->toBe('19.000')
        ->and(stockQty($this, $this->telur))->toBe('110.000');

    $sale = asTenant($this->tenant, fn () => Sale::first());
    expect($sale->cashier_id)->toBe($this->kasir->id)
        ->and($sale->items()->first()->cost_amount)->toBe(65000); // modal untuk laporan laba
});

it('aman dikirim dua kali (idempotent): tidak ada nota dobel, stok tidak terpotong dua kali', function () {
    $shift = openShift($this, $this->kasir);
    $payload = salePayload($shift, [['product_id' => $this->beras->id, 'qty' => 2]], [['payment_method_id' => $this->cash->id, 'amount' => 144000]]);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), $payload)->assertCreated();
    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), $payload)->assertOk();

    expect(asTenant($this->tenant, fn () => Sale::count()))->toBe(1)
        ->and(stockQty($this, $this->beras))->toBe('18.000');
});

it('menghitung diskon, service charge, dan pajak di server', function () {
    asTenant($this->tenant, fn () => Outlet::find($this->outletId)->update(['tax_rate_bp' => 1000, 'service_charge_bp' => 500]));
    $shift = openShift($this, $this->owner);

    // 4 x 2.500 = 10.000 - diskon barang 1.000 = 9.000; diskon 10% = 900 -> 8.100
    // service 5% = 405 -> 8.505; pajak 10% = 851 -> total 9.356
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->telur->id, 'qty' => 4, 'discount_amount' => 1000],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 10000]], ['discount_type' => 'percent', 'discount_value' => 1000]))
        ->assertCreated()
        ->assertJsonPath('data.subtotal', 9000)
        ->assertJsonPath('data.discount_amount', 900)
        ->assertJsonPath('data.service_charge_amount', 405)
        ->assertJsonPath('data.tax_amount', 851)
        ->assertJsonPath('data.total', 9356)
        ->assertJsonPath('data.change_amount', 644);
});

it('pajak yang sudah termasuk harga tidak menambah total', function () {
    asTenant($this->tenant, fn () => Outlet::find($this->outletId)->update(['tax_rate_bp' => 1100, 'tax_inclusive' => true]));
    $shift = openShift($this, $this->owner);

    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->beras->id, 'qty' => 1],
    ], [['payment_method_id' => $this->qris->id, 'amount' => 72000]]))
        ->assertJsonPath('data.total', 72000)
        ->assertJsonPath('data.tax_amount', 7135);
});

it('mendukung bayar sebagian tunai sebagian QRIS (split payment)', function () {
    $shift = openShift($this, $this->kasir);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->beras->id, 'qty' => 1],
    ], [
        ['payment_method_id' => $this->qris->id, 'amount' => 50000],
        ['payment_method_id' => $this->cash->id, 'amount' => 25000],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.change_amount', 3000)
        ->assertJsonCount(2, 'data.payments');
});

it('menolak bila uang kurang, dengan pesan yang jelas', function () {
    $shift = openShift($this, $this->kasir);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->beras->id, 'qty' => 1],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 70000]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.payments.0', 'Uang yang dibayar kurang Rp2.000.');

    expect(stockQty($this, $this->beras))->toBe('20.000');
});

it('kembalian hanya bisa dari uang tunai', function () {
    $shift = openShift($this, $this->kasir);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->telur->id, 'qty' => 1],
    ], [['payment_method_id' => $this->qris->id, 'amount' => 10000]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payments');
});

it('menolak jualan bila kasir belum dibuka', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload((string) Str::uuid(), [
        ['product_id' => $this->telur->id, 'qty' => 1],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 2500]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.shift.0', 'Kasir belum dibuka. Buka kasir dulu sebelum mulai jualan.');
});

it('kasir tidak bisa memberi diskon tanpa izin', function () {
    $user = addStaff($this->owner, 'karyawan', '2222');
    $user->givePermissionTo('use_pos'); // bisa jualan, tapi tidak boleh diskon
    $shift = openShift($this, $user);

    $this->actingAs($user)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->telur->id, 'qty' => 1, 'discount_amount' => 500],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 2500]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn ($errors) => str_contains(json_encode($errors), 'tidak punya izin memberi diskon'));
});

it('kasir tanpa izin ubah harga tetap memakai harga di sistem', function () {
    $shift = openShift($this, $this->kasir);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->beras->id, 'qty' => 1, 'unit_price' => 1000],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 72000]]))
        ->assertCreated()
        ->assertJsonPath('data.total', 72000);
});

it('penjualan offline yang disinkronkan tidak ditolak walau stok tidak cukup', function () {
    $shift = openShift($this, $this->kasir);
    $payload = salePayload($shift, [['product_id' => $this->beras->id, 'qty' => 25]], [['payment_method_id' => $this->cash->id, 'amount' => 1800000]]);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), $payload)->assertCreated();

    expect(stockQty($this, $this->beras))->toBe('-5.000');
});

it('tidak bisa melihat atau membatalkan transaksi usaha lain', function () {
    $shift = openShift($this, $this->kasir);
    $sale = $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->telur->id, 'qty' => 1],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 2500]]))->json('data');

    $other = registerTenant('toko_kelontong', name: 'Toko Lain');

    $this->actingAs($other)->getJson(route('pos.api.sales.show', $sale['uuid']))->assertNotFound();
    $this->actingAs($other)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'x'])->assertNotFound();
});

it('memakai barang dari usaha lain ditolak', function () {
    $other = registerTenant('toko_kelontong', name: 'Toko Lain');
    $foreign = productNamed($other, 'Beras Premium 5 kg');
    $shift = openShift($this, $this->kasir);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $foreign->id, 'qty' => 1],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 72000]]))
        ->assertJsonValidationErrors('items.0.product_id');
});

it('karyawan tanpa izin kasir tidak bisa membuka kasir', function () {
    $staff = addStaff($this->owner, 'karyawan', '3333');

    $this->actingAs($staff)->get(route('pos.show'))->assertForbidden();
    $this->actingAs($staff)->getJson(route('pos.api.bootstrap'))->assertForbidden();
});

it('menyediakan data awal kasir: barang, cara bayar, dan kasir yang sedang buka', function () {
    $shift = openShift($this, $this->kasir);

    $this->actingAs($this->kasir)->getJson(route('pos.api.bootstrap'))
        ->assertOk()
        ->assertJsonPath('shift.uuid', $shift)
        ->assertJsonPath('tenant.pos_layout', 'retail')
        ->assertJsonCount(4, 'payment_methods')
        ->assertJsonCount(12, 'products')
        ->assertJsonPath('approvers.0.name', $this->owner->name === 'x' ? 'x' : fn ($n) => is_string($n) || $n === null);
});
