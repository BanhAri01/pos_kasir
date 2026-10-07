<?php

require_once __DIR__.'/PosTestHelpers.php';

use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->owner->setPin('9999');
    $this->owner->save();
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->kasir = addStaff($this->owner, 'kasir', '1111');
    $this->cash = paymentMethod($this->owner, 'cash');
    $this->beras = productNamed($this->owner, 'Beras Premium 5 kg');
    $this->telur = productNamed($this->owner, 'Telur Ayam');
    $this->shift = openShift($this, $this->kasir, 100000);
});

function sellBeras($test, int $qty = 2): array
{
    return $test->actingAs($test->kasir)->postJson(route('pos.api.sales.store'), salePayload($test->shift, [
        ['product_id' => $test->beras->id, 'qty' => $qty],
    ], [['payment_method_id' => $test->cash->id, 'amount' => 72000 * $qty]]))->assertCreated()->json('data');
}

it('membuka kasir sekali saja per orang per outlet', function () {
    $again = $this->actingAs($this->kasir)->postJson(route('pos.api.shifts.open'), ['opening_cash' => 50000])->json('data.uuid');

    expect($again)->toBe($this->shift)
        ->and(asTenant($this->tenant, fn () => Shift::count()))->toBe(1);
});

it('menghitung uang seharusnya di laci lalu menutup kasir dengan selisih', function () {
    sellBeras($this, 1); // tunai 72.000

    $this->actingAs($this->kasir)->postJson(route('pos.api.shifts.cash', $this->shift), [
        'type' => 'out', 'amount' => 10000, 'reason' => 'Beli es batu',
    ])->assertOk()->assertJsonPath('message', 'Uang keluar Rp10.000 sudah dicatat.');

    $this->actingAs($this->kasir)->getJson(route('pos.api.shifts.summary', $this->shift))
        ->assertJsonPath('data.cash_sales', 72000)
        ->assertJsonPath('data.cash_out', 10000)
        ->assertJsonPath('data.expected_cash', 162000)
        ->assertJsonPath('data.sales_count', 1);

    $this->actingAs($this->kasir)->postJson(route('pos.api.shifts.close', $this->shift), ['counted_cash' => 160000])
        ->assertOk()
        ->assertJsonPath('data.cash_difference', -2000)
        ->assertJsonPath('message', 'Kasir sudah ditutup. Uang kurang Rp2.000.');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'kasir', 'description' => 'Tutup kasir: uang kurang']);
});

it('tidak bisa jualan setelah kasir ditutup', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.shifts.close', $this->shift), ['counted_cash' => 100000]);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($this->shift, [
        ['product_id' => $this->telur->id, 'qty' => 1],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 2500]]))->assertJsonValidationErrors('shift');
});

it('kasir butuh PIN atasan untuk membatalkan transaksi', function () {
    $sale = sellBeras($this);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'Salah input'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.approval.0', 'Butuh persetujuan manajer atau pemilik. Minta mereka memasukkan PIN.');

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.void', $sale['uuid']), [
        'reason' => 'Salah input', 'approver_id' => $this->owner->id, 'approver_pin' => '0000',
    ])->assertJsonPath('errors.approval.0', 'PIN persetujuan salah. Coba lagi.');

    $this->actingAs($this->kasir)->postJson(route('pos.api.sales.void', $sale['uuid']), [
        'reason' => 'Salah input', 'approver_id' => $this->owner->id, 'approver_pin' => '9999',
    ])->assertOk();

    $voided = asTenant($this->tenant, fn () => Sale::where('uuid', $sale['uuid'])->first());
    expect($voided->status)->toBe('void')
        ->and($voided->authorized_by)->toBe($this->owner->id)
        ->and(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('20.000');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'penjualan', 'subject_id' => $voided->id, 'causer_id' => $this->kasir->id]);
});

it('transaksi yang dibatalkan tidak dihitung di uang laci', function () {
    $sale = sellBeras($this, 1);
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'Batal']);

    $this->actingAs($this->kasir)->getJson(route('pos.api.shifts.summary', $this->shift))
        ->assertJsonPath('data.cash_sales', 0)
        ->assertJsonPath('data.expected_cash', 100000);
});

it('pengembalian sebagian: uang kembali sebanding, stok kembali', function () {
    $sale = sellBeras($this, 2); // 144.000

    $item = $sale['items'][0];
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.refund', $sale['uuid']), [
        'reason' => 'Beras rusak', 'restock' => true, 'shift_uuid' => $this->shift,
        'items' => [['sale_item_id' => $item['id'], 'qty' => 1]],
    ])->assertOk()->assertJsonPath('amount', 72000);

    $fresh = asTenant($this->tenant, fn () => Sale::where('uuid', $sale['uuid'])->first());
    expect($fresh->payment_status)->toBe('partially_refunded')
        ->and($fresh->refunded_amount)->toBe(72000)
        ->and(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('19.000');

    // Uang laci berkurang karena pengembalian tunai.
    $this->actingAs($this->kasir)->getJson(route('pos.api.shifts.summary', $this->shift))
        ->assertJsonPath('data.cash_refunds', 72000)
        ->assertJsonPath('data.expected_cash', 172000);

    // Tidak bisa mengembalikan melebihi yang dibeli.
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.refund', $sale['uuid']), [
        'reason' => 'x', 'items' => [['sale_item_id' => $item['id'], 'qty' => 2]],
    ])->assertJsonValidationErrors('items');

    // Transaksi yang sudah dikembalikan sebagian tidak bisa di-void.
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'x'])
        ->assertJsonValidationErrors('sale');
});

it('pengembalian proporsional mengikuti diskon transaksi', function () {
    $sale = $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($this->shift === null ? '' : openShift($this, $this->owner), [
        ['product_id' => $this->telur->id, 'qty' => 10], // 25.000
    ], [['payment_method_id' => $this->cash->id, 'amount' => 20000]], ['discount_type' => 'amount', 'discount_value' => 5000]))
        ->assertCreated()->json('data'); // total 20.000

    $this->actingAs($this->owner)->postJson(route('pos.api.sales.refund', $sale['uuid']), [
        'reason' => 'Pecah', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 5]],
    ])->assertJsonPath('amount', 10000); // setengah dari yang dibayar, bukan 12.500
});

it('struk digital bisa dibuka tanpa login lewat link bertanda tangan, tapi tidak tanpa tanda tangan', function () {
    $sale = sellBeras($this, 1);

    auth()->logout();
    $this->get($sale['receipt_url'])
        ->assertOk()
        ->assertSee('Beras Premium 5 kg')
        ->assertSee('Rp72.000')
        ->assertSee($sale['number']);

    $this->get(route('receipt.show', $sale['uuid']))->assertForbidden();
});

it('pelanggan dari kasir aman dikirim ulang (offline)', function () {
    $uuid = (string) Str::uuid();

    $this->actingAs($this->kasir)->postJson(route('pos.api.customers.store'), ['uuid' => $uuid, 'name' => 'Pak Joko', 'phone' => '0813 1111 2222'])->assertCreated();
    $this->actingAs($this->kasir)->postJson(route('pos.api.customers.store'), ['uuid' => $uuid, 'name' => 'Pak Joko', 'phone' => '0813 1111 2222'])->assertCreated();

    $this->actingAs($this->kasir)->getJson(route('pos.api.customers.index', ['q' => '081311112222']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Pak Joko');
});
