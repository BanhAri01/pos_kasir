<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Core\Modules\ModuleManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyEntry;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('coffee_shop');
    $this->tenant = $this->owner->tenant;
    if (! $this->tenant->hasModule('loyalty')) {
        app(ModuleManager::class)->enable($this->tenant, 'loyalty');
    }
    [$this->product, $this->customer] = asTenant($this->tenant, fn () => [
        Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Kopi Susu', 'type' => 'goods', 'price' => 25000, 'cost_price' => 9000, 'track_stock' => false]),
        Customer::create(['uuid' => (string) Str::uuid(), 'name' => 'Bu Made', 'phone' => '6281234500001']),
    ]);
    $this->cash = paymentMethod($this->owner)->id;
});

function buy($test, string $shift, int $qty, int $pay, array $extra = []): array
{
    return $test->actingAs($test->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $test->product->id, 'qty' => $qty],
    ], [['payment_method_id' => $test->cash, 'amount' => $pay]], ['customer_uuid' => $test->customer->uuid, ...$extra]))->assertCreated()->json('data');
}

it('memberi poin sesuai belanja', function () {
    $shift = openShift($this, $this->owner);
    buy($this, $shift, 2, 50000);

    expect($this->customer->fresh()->loyalty_points)->toBe(5);
});

it('menukar poin menjadi potongan dan mengurangi saldo poin', function () {
    $this->customer->forceFill(['loyalty_points' => 12])->save();
    $shift = openShift($this, $this->owner);

    buy($this, $shift, 1, 15000, ['redeem_points' => 10, 'discount_type' => 'amount', 'discount_value' => 10000]);

    expect($this->customer->fresh()->loyalty_points)->toBe(2 + 1);
});

it('menolak tukar poin kalau poin tidak cukup', function () {
    $this->customer->forceFill(['loyalty_points' => 4])->save();
    $shift = openShift($this, $this->owner);

    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [['product_id' => $this->product->id, 'qty' => 1]],
        [['payment_method_id' => $this->cash, 'amount' => 15000]], ['customer_uuid' => $this->customer->uuid, 'redeem_points' => 10, 'discount_type' => 'amount', 'discount_value' => 10000]))
        ->assertUnprocessable()->assertJsonValidationErrors('redeem_points');

    expect($this->customer->fresh()->loyalty_points)->toBe(4);
});

it('menolak tukar poin tanpa potongan', function () {
    $this->customer->forceFill(['loyalty_points' => 10])->save();
    $shift = openShift($this, $this->owner);

    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [['product_id' => $this->product->id, 'qty' => 1]],
        [['payment_method_id' => $this->cash, 'amount' => 25000]], ['customer_uuid' => $this->customer->uuid, 'redeem_points' => 10]))
        ->assertUnprocessable();
});

it('mengembalikan poin saat transaksi dibatalkan', function () {
    $this->customer->forceFill(['loyalty_points' => 10])->save();
    $shift = openShift($this, $this->owner);
    $sale = buy($this, $shift, 1, 15000, ['redeem_points' => 10, 'discount_type' => 'amount', 'discount_value' => 10000]);
    expect($this->customer->fresh()->loyalty_points)->toBe(1);

    $this->actingAs($this->owner)->postJson(route('pos.api.sales.void', $sale['uuid']), ['reason' => 'Salah input'])->assertOk();

    expect($this->customer->fresh()->loyalty_points)->toBe(10)
        ->and(asTenant($this->tenant, fn () => LoyaltyEntry::where('type', 'reverse')->count()))->toBe(1);
});

it('tidak memberi poin dobel saat penjualan dikirim ulang', function () {
    $shift = openShift($this, $this->owner);
    $payload = salePayload($shift, [['product_id' => $this->product->id, 'qty' => 2]], [['payment_method_id' => $this->cash, 'amount' => 50000]], ['customer_uuid' => $this->customer->uuid]);

    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), $payload)->assertCreated();
    $this->actingAs($this->owner)->postJson(route('pos.api.sales.store'), $payload)->assertOk();

    expect($this->customer->fresh()->loyalty_points)->toBe(5);
});

it('pemilik bisa mengubah aturan dan poin pelanggan', function () {
    $this->actingAs($this->owner)->put(route('loyalty.update'), ['spend_per_point' => 5000, 'points_for_reward' => 20, 'reward_value' => 15000, 'notify' => false])
        ->assertSessionHas('success');
    expect($this->tenant->fresh()->settings['loyalty']['spend_per_point'])->toBe(5000);

    $this->actingAs($this->owner)->post(route('loyalty.adjust', $this->customer->id), ['points' => 7, 'note' => 'Hadiah'])->assertSessionHas('success');
    $this->actingAs($this->owner)->post(route('loyalty.adjust', $this->customer->id), ['points' => -50, 'note' => 'Koreksi'])->assertSessionHasErrors('points');

    expect($this->customer->fresh()->loyalty_points)->toBe(7);
    $this->actingAs($this->owner)->get(route('loyalty.index'))->assertOk();
});

it('kasir menerima aturan poin dan saldo pelanggan di data awal', function () {
    $this->customer->forceFill(['loyalty_points' => 9])->save();

    $this->actingAs($this->owner)->getJson(route('pos.api.bootstrap'))->assertOk()
        ->assertJsonPath('tenant.loyalty.points_for_reward', 10)
        ->assertJsonPath('customers.0.points', 9);
});
