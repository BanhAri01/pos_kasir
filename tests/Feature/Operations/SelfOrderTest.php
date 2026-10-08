<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Services\PaymentFee;
use App\Modules\Catalog\Models\Product;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Operations\Models\KitchenTicket;
use App\Modules\Operations\Models\SelfOrder;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('coffee_shop', name: 'Kedai Senja');
    $this->tenant = $this->owner->tenant;
    $this->outlet = outletsOf($this->owner)->first();
    [$this->table, $this->product] = asTenant($this->tenant, fn () => [
        DiningTable::create(['outlet_id' => $this->outlet->id, 'name' => 'Meja 7']),
        Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Es Kopi Susu', 'type' => 'goods', 'price' => 18000, 'cost_price' => 8000, 'track_stock' => false]),
    ]);
});

function orderPayload(int $productId, array $extra = []): array
{
    return [
        'customer_name' => 'Dewi',
        'pay_method' => 'cashier',
        'items' => [['product_id' => $productId, 'qty' => 2, 'unit_price' => 1, 'note' => 'es dipisah']],
        ...$extra,
    ];
}

it('membuat token QR otomatis untuk setiap meja', function () {
    expect($this->table->qr_token)->toHaveLength(32);
});

it('pelanggan bisa membuka menu tanpa masuk', function () {
    $this->get(route('self-order.show', $this->table->qr_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Order/Menu')->where('open', true)->where('table', 'Meja 7')
            ->where('menu.products', fn ($products) => collect($products)->contains('name', 'Es Kopi Susu')));
});

it('menolak token QR yang salah', function () {
    $this->get('/m/'.Str::random(32))->assertNotFound();
});

it('menghitung harga di server, bukan dari HP pelanggan', function () {
    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id))->assertRedirect();

    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());
    expect($order->total)->toBe(36000)
        ->and($order->items[0]['unit_price'])->toBe(18000)
        ->and($order->table_id)->toBe($this->table->id)
        ->and($order->status)->toBe('new');
});

it('kasir melihat pesanan baru lalu menerimanya ke meja', function () {
    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id));
    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());
    $cashier = addStaff($this->owner);

    $this->actingAs($cashier)->getJson(route('self-orders.pending'))->assertOk()
        ->assertJsonPath('data.0.code', $order->code)->assertJsonPath('data.0.table', 'Meja 7')->assertJsonPath('data.0.paid', false);

    $this->actingAs($cashier)->postJson(route('self-orders.accept', $order->uuid))->assertOk();
    expect($order->fresh()->status)->toBe('accepted');
    $this->actingAs($cashier)->postJson(route('self-orders.accept', $order->uuid))->assertUnprocessable();
});

it('kasir bisa menolak pesanan dengan alasan', function () {
    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id));
    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());

    $this->actingAs($this->owner)->postJson(route('self-orders.reject', $order->uuid), ['reason' => 'Menu habis'])->assertOk();

    $this->get(route('self-order.status', $order->uuid))->assertOk()
        ->assertInertia(fn ($page) => $page->where('order.status', 'rejected')->where('order.reject_reason', 'Menu habis'));
});

it('bayar QRIS dari meja: biaya ditanggung pembeli dan penjualan tercatat lunas saat diterima', function () {
    config(['hermes.payment.driver' => 'fake']);
    $this->tenant->forceFill(['qr_payment' => 'online'])->save();

    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id, ['pay_method' => 'online']))->assertRedirect();
    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());
    expect($order->fee_amount)->toBe(PaymentFee::feeFor(36000, 'qris'))->and($order->payment_status)->toBe('pending');

    $this->actingAs($this->owner)->getJson(route('self-orders.pending'))->assertJsonCount(0, 'data');

    $gross = $order->total + $order->fee_amount;
    $this->postJson(route('self-order.webhook', $this->tenant->uuid), [
        'order_id' => $order->payment_reference, 'gross_amount' => $gross, 'transaction_status' => 'settlement',
        'signature_key' => app(Gateways::class)->fake()->sign($order->payment_reference, $gross),
    ])->assertOk();
    expect($order->fresh()->payment_status)->toBe('paid');

    $shift = openShift($this, $this->owner);
    $this->actingAs($this->owner)->postJson(route('self-orders.accept', $order->uuid), ['shift_uuid' => $shift])->assertOk()->assertJsonPath('sale_uuid', fn ($v) => is_string($v));

    $sale = asTenant($this->tenant, fn () => Sale::with('payments')->firstOrFail());
    expect($sale->total)->toBe(36000)
        ->and($sale->payment_status)->toBe('paid')
        ->and($sale->table_id)->toBe($this->table->id)
        ->and($order->fresh()->status)->toBe('done')
        ->and(asTenant($this->tenant, fn () => KitchenTicket::count()))->toBe(1);
});

it('menolak notifikasi QRIS palsu', function () {
    config(['hermes.payment.driver' => 'fake']);
    $this->tenant->forceFill(['qr_payment' => 'online'])->save();
    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id, ['pay_method' => 'online']));
    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());

    $this->postJson(route('self-order.webhook', $this->tenant->uuid), ['order_id' => $order->payment_reference, 'gross_amount' => 1, 'transaction_status' => 'settlement', 'signature_key' => 'palsu'])
        ->assertForbidden();
    expect($order->fresh()->payment_status)->toBe('pending');
});

it('tidak bisa memilih bayar online kalau usaha belum mengaktifkannya', function () {
    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id, ['pay_method' => 'online']))
        ->assertSessionHasErrors('pay_method');
});

it('menutup pesan QR untuk paket Standar', function () {
    $this->tenant->forceFill(['plan' => 'standar'])->save();
    $this->tenant->flushModuleCache();

    $this->get(route('self-order.show', $this->table->qr_token))->assertInertia(fn ($page) => $page->where('open', false));
    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id))->assertNotFound();
});

it('membatasi pesanan menumpuk dari satu meja', function () {
    foreach (range(1, 4) as $i) {
        $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id));
    }

    $this->post(route('self-order.store', $this->table->qr_token), orderPayload($this->product->id))->assertSessionHasErrors('items');
});

it('pemilik bisa mencetak QR meja dan menyimpan key Midtrans terenkripsi', function () {
    Http::fake(['*' => Http::response(['status_code' => '404'], 404)]);

    $this->actingAs($this->owner)->get(route('self-orders.print'))->assertOk()->assertSee('Meja 7')->assertSee('<svg', false);
    $this->actingAs($this->owner)->put(route('self-orders.payment'), ['mode' => 'online', 'server_key' => 'SB-Mid-server-abc123', 'production' => false])
        ->assertSessionHas('success');

    $raw = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenant->id)->value('midtrans_server_key');
    expect($raw)->not->toContain('abc123')
        ->and($this->tenant->fresh()->midtrans_server_key)->toBe('SB-Mid-server-abc123');
});

it('menolak key Midtrans yang tidak valid', function () {
    Http::fake(['*' => Http::response(['status_code' => '401'], 401)]);

    $this->actingAs($this->owner)->put(route('self-orders.payment'), ['mode' => 'online', 'server_key' => 'Mid-server-salah', 'production' => true])
        ->assertSessionHasErrors('server_key');
});
