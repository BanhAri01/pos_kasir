<?php

use App\Models\Tenant;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Services\PaymentFee;
use App\Modules\Billing\Services\Plans;
use App\Modules\Billing\Services\SubscriptionBilling;
use Illuminate\Support\Carbon;

function fakePayments(): void
{
    config(['hermes.payment.driver' => 'fake']);
}

function signedFakeNotification(string $reference, int $amount): array
{
    return [
        'order_id' => $reference,
        'gross_amount' => $amount,
        'transaction_status' => 'settlement',
        'signature_key' => app(Gateways::class)->fake()->sign($reference, $amount),
    ];
}

it('menghitung biaya layanan sehingga pemilik POS menerima harga paket utuh', function () {
    $fee = PaymentFee::feeFor(199000, 'qris');
    $gross = 199000 + $fee;
    $midtransTakes = ($gross * 0.007) * 1.11;

    expect($gross - $midtransTakes)->toBeGreaterThanOrEqual(199000)
        ->and($gross - $midtransTakes)->toBeLessThan(199002)
        ->and(PaymentFee::feeFor(100000, 'va'))->toBe(4440);
});

it('memberi diskon untuk langganan panjang', function () {
    expect(Plans::price('pro', 1))->toBe(199000)
        ->and(Plans::price('pro', 12))->toBe(1990000)
        ->and(Plans::price('standar', 3))->toBe(282000);
});

it('usaha baru mendapat masa coba paket Bisnis', function () {
    $owner = registerTenant();

    expect($owner->tenant->planKey())->toBe('bisnis')
        ->and($owner->tenant->hasAccess())->toBeTrue();
});

it('mengarahkan ke halaman langganan setelah masa coba dan masa tenggang habis', function () {
    $owner = registerTenant();
    $owner->tenant->forceFill(['trial_ends_at' => now()->subDays(4)])->save();

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('billing.index'));
    $this->actingAs($owner)->get(route('billing.index'))->assertOk();
    $this->actingAs($owner)->getJson(route('pos.api.bootstrap'))->assertStatus(402);
});

it('masih bisa dipakai selama masa tenggang', function () {
    $owner = registerTenant();
    $owner->tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    expect($owner->tenant->fresh()->isInGrace())->toBeTrue();
});

it('membatasi jumlah karyawan sesuai paket', function () {
    $owner = registerTenant();
    $owner->tenant->forceFill(['plan' => 'standar'])->save();
    foreach (range(1, 3) as $i) {
        addStaff($owner, 'kasir', (string) (1000 + $i));
    }

    $outletId = outletsOf($owner)->first()->id;
    $this->actingAs($owner)->post(route('staff.store'), [
        'name' => 'Kasir Keempat',
        'role' => 'kasir',
        'pin' => '4444',
        'outlet_ids' => [$outletId],
    ])->assertSessionHasErrors('name');
});

it('mengunci fitur yang butuh paket lebih tinggi', function () {
    $owner = registerTenant('coffee_shop');
    $tenant = $owner->tenant;
    expect($tenant->hasModule('kitchen_display'))->toBeTrue();

    $tenant->forceFill(['plan' => 'standar'])->save();
    $tenant->flushModuleCache();

    expect($tenant->fresh()->hasModule('kitchen_display'))->toBeFalse();
    $this->actingAs($owner)->put(route('modules.update', 'qr_order'), ['enabled' => true])
        ->assertSessionHas('error');
});

it('membuat tagihan dengan biaya layanan dan memperpanjang setelah dibayar', function () {
    fakePayments();
    Carbon::setTestNow('2026-10-09 10:00:00');
    $owner = registerTenant();
    $tenant = $owner->tenant;

    $this->actingAs($owner)->post(route('billing.checkout'), ['plan' => 'pro', 'months' => 3, 'channel' => 'qris'])
        ->assertRedirect();

    $payment = SubscriptionPayment::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($payment->base_amount)->toBe(Plans::price('pro', 3))
        ->and($payment->fee_amount)->toBe(PaymentFee::feeFor($payment->base_amount, 'qris'))
        ->and($payment->amount)->toBe($payment->base_amount + $payment->fee_amount);

    $this->postJson(route('billing.webhook'), signedFakeNotification($payment->reference, $payment->amount))->assertOk();
    $this->postJson(route('billing.webhook'), signedFakeNotification($payment->reference, $payment->amount))->assertOk();

    $tenant->refresh();
    expect($payment->fresh()->status)->toBe('paid')
        ->and($tenant->status)->toBe('active')
        ->and($tenant->planKey())->toBe('pro')
        ->and($tenant->paid_until->toDateString())->toBe('2027-01-22');
    Carbon::setTestNow();
});

it('menolak notifikasi dengan tanda tangan palsu atau nominal berbeda', function () {
    fakePayments();
    $owner = registerTenant();
    $payment = app(SubscriptionBilling::class)->start($owner->tenant, $owner, 'standar', 1, 'va');

    $this->postJson(route('billing.webhook'), ['order_id' => $payment->reference, 'gross_amount' => $payment->amount, 'transaction_status' => 'settlement', 'signature_key' => 'palsu'])
        ->assertStatus(403);
    $this->postJson(route('billing.webhook'), signedFakeNotification($payment->reference, $payment->amount - 1000))->assertOk();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($owner->tenant->fresh()->status)->toBe('trial');
});

it('hanya pemilik yang bisa membayar langganan', function () {
    fakePayments();
    $owner = registerTenant();
    $cashier = addStaff($owner);

    $this->actingAs($cashier)->post(route('billing.checkout'), ['plan' => 'pro', 'months' => 1, 'channel' => 'qris'])->assertForbidden();
});

it('mengonversi sisa hari saat pindah paket', function () {
    Carbon::setTestNow('2026-10-09 10:00:00');
    $owner = registerTenant();
    $tenant = $owner->tenant;
    $tenant->forceFill(['status' => 'active', 'plan' => 'bisnis', 'paid_until' => '2026-11-08'])->save();

    [$from] = $tenant->extendSubscription(1, 'standar');

    expect($from->toDateString())->toBe(Carbon::today()->addDays((int) floor(30 * 349000 / 99000))->toDateString());
    Carbon::setTestNow();
});
