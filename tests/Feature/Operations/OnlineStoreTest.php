<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Models\Outlet;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Catalog\Models\Product;
use App\Modules\Operations\Models\SelfOrder;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('coffee_shop', name: 'Kopi Pagi');
    $this->tenant = $this->owner->tenant;
    $this->product = asTenant($this->tenant, fn () => Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Kopi Aren', 'type' => 'goods', 'price' => 20000, 'cost_price' => 7000, 'track_stock' => false]));

    $this->actingAs($this->owner)->get(route('online-orders.edit'))->assertOk();
    $this->actingAs($this->owner)->put(route('online-orders.update'), ['online_open' => true, 'online_pickup' => true, 'online_delivery' => true, 'delivery_fee' => 8000, 'online_min_order' => 30000])->assertSessionHas('success');
    $this->outlet = asTenant($this->tenant, fn () => Outlet::query()->firstOrFail());
    auth()->logout();
});

function storePayload(int $productId, array $extra = []): array
{
    return [
        'customer_name' => 'Ayu',
        'customer_phone' => '0812 7777 1234',
        'order_type' => 'delivery',
        'address' => 'Jl. Merdeka 5, dekat masjid',
        'pay_method' => 'cashier',
        'items' => [['product_id' => $productId, 'qty' => 2]],
        ...$extra,
    ];
}

it('menampilkan toko online untuk tamu', function () {
    $this->get(route('online-store.show', $this->outlet->order_token))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Order/Menu')->where('mode', 'store')->where('open', true)->where('store.delivery_fee', 8000));
});

it('pesanan antar menambahkan ongkir dan menyimpan alamat', function () {
    $this->post(route('online-store.order', $this->outlet->order_token), storePayload($this->product->id))->assertRedirect();

    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());
    expect($order->order_type)->toBe('delivery')
        ->and($order->delivery_fee)->toBe(8000)
        ->and($order->total)->toBe(48000)
        ->and($order->customer_phone)->toBe('6281277771234')
        ->and($order->table_id)->toBeNull();
});

it('menolak pesanan di bawah minimal atau tanpa alamat', function () {
    $this->post(route('online-store.order', $this->outlet->order_token), storePayload($this->product->id, ['items' => [['product_id' => $this->product->id, 'qty' => 1]]]))
        ->assertSessionHasErrors('items');
    $this->post(route('online-store.order', $this->outlet->order_token), storePayload($this->product->id, ['address' => '']))
        ->assertSessionHasErrors('address');
});

it('menolak pesanan saat toko tutup', function () {
    $this->outlet->forceFill(['online_open' => false])->save();

    $this->get(route('online-store.show', $this->outlet->order_token))->assertInertia(fn ($page) => $page->where('open', false));
    $this->post(route('online-store.order', $this->outlet->order_token), storePayload($this->product->id))->assertNotFound();
});

it('kasir melihat pesanan antar dengan id barang ongkir', function () {
    $this->post(route('online-store.order', $this->outlet->order_token), storePayload($this->product->id));

    $response = $this->actingAs($this->owner)->getJson(route('self-orders.pending'))->assertOk()
        ->assertJsonPath('data.0.order_type', 'delivery')
        ->assertJsonPath('data.0.address', 'Jl. Merdeka 5, dekat masjid');
    $ongkir = $response->json('data.0.delivery_product_id');
    expect(asTenant($this->tenant, fn () => Product::find($ongkir)->code))->toBe('ONGKIR');
});

it('pesanan antar yang lunas QRIS tercatat dengan baris ongkir', function () {
    config(['hermes.payment.driver' => 'fake']);
    $this->tenant->forceFill(['qr_payment' => 'online'])->save();

    $this->post(route('online-store.order', $this->outlet->order_token), storePayload($this->product->id, ['pay_method' => 'online']))->assertRedirect();
    $order = asTenant($this->tenant, fn () => SelfOrder::firstOrFail());
    $gross = $order->total + $order->fee_amount;
    $this->postJson(route('self-order.webhook', $this->tenant->uuid), [
        'order_id' => $order->payment_reference, 'gross_amount' => $gross, 'transaction_status' => 'settlement',
        'signature_key' => app(Gateways::class)->fake()->sign($order->payment_reference, $gross),
    ])->assertOk();

    $shift = openShift($this, $this->owner);
    $this->actingAs($this->owner)->postJson(route('self-orders.accept', $order->uuid), ['shift_uuid' => $shift])->assertOk();

    $sale = asTenant($this->tenant, fn () => Sale::with('items')->firstOrFail());
    expect($sale->total)->toBe(48000)
        ->and($sale->order_type)->toBe('delivery')
        ->and($sale->items->pluck('name')->all())->toContain('Ongkos Kirim')
        ->and($sale->note)->toContain('Jl. Merdeka 5');
});

it('modul toko online terkunci untuk paket Standar', function () {
    $this->tenant->forceFill(['plan' => 'standar'])->save();
    $this->tenant->flushModuleCache();

    $this->get(route('online-store.show', $this->outlet->order_token))->assertInertia(fn ($page) => $page->where('open', false));
});

it('pemilik bisa mencetak poster dan mengganti link toko', function () {
    $old = $this->outlet->order_token;
    $this->actingAs($this->owner)->get(route('online-orders.poster'))->assertOk()->assertSee('PESAN DARI HP')->assertSee('<svg', false);
    $this->actingAs($this->owner)->post(route('online-orders.regenerate'))->assertSessionHas('success');

    $this->get(route('online-store.show', $old))->assertNotFound();
});
