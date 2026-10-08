<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Services\SubscriptionBilling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Carbon::setTestNow('2026-10-09 10:00:00');
    $this->referrer = registerTenant(name: 'Warung Pengajak');
    $this->referrer->tenant->forceFill(['status' => 'active', 'paid_until' => '2026-11-30'])->save();
    $this->code = $this->referrer->tenant->fresh()->referral_code;
});

afterEach(fn () => Carbon::setTestNow());

function registerWithRef($test, string $ref, string $phone = '081300001111'): Tenant
{
    $test->post(route('register.store'), [
        'business_type' => 'warung_makan', 'business_name' => 'Kedai Baru', 'owner_name' => 'Nyoman',
        'phone' => $phone, 'password' => 'rahasia123', 'ref' => $ref,
    ])->assertRedirect(route('dashboard'));
    auth()->logout();

    return Tenant::query()->where('name', 'Kedai Baru')->latest('id')->firstOrFail();
}

it('setiap usaha baru punya kode referral', function () {
    expect($this->code)->toMatch('/^[A-Z2-9]{6}$/');
});

it('halaman daftar menampilkan siapa yang mengajak', function () {
    $this->get(route('register', ['ref' => strtolower($this->code)]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('referral.name', 'Warung Pengajak')->where('referral.bonus_days', 7));
});

it('usaha yang diajak dapat bonus masa coba dan tercatat pengajaknya', function () {
    $new = registerWithRef($this, $this->code);

    expect($new->referred_by_id)->toBe($this->referrer->tenant_id)
        ->and($new->trial_ends_at->toDateString())->toBe(now()->addDays(14 + 7)->toDateString());
});

it('kode salah tidak memberi bonus', function () {
    $new = registerWithRef($this, 'SALAH9');

    expect($new->referred_by_id)->toBeNull()
        ->and($new->trial_ends_at->toDateString())->toBe(now()->addDays(14)->toDateString());
});

it('pengajak dapat 30 hari sekali saja setelah usaha yang diajak membayar', function () {
    config(['hermes.payment.driver' => 'fake']);
    $new = registerWithRef($this, $this->code);
    $owner = User::query()->findOrFail($new->owner_id);

    $billing = app(SubscriptionBilling::class);
    foreach (range(1, 2) as $i) {
        $payment = $billing->start($new, $owner, 'standar', 1, 'qris');
        $this->postJson(route('billing.webhook'), [
            'order_id' => $payment->reference, 'gross_amount' => $payment->amount, 'transaction_status' => 'settlement',
            'signature_key' => app(Gateways::class)->fake()->sign($payment->reference, $payment->amount),
        ])->assertOk();
    }

    expect($this->referrer->tenant->fresh()->paid_until->toDateString())->toBe('2026-12-30')
        ->and(DB::table('referral_rewards')->count())->toBe(1)
        ->and(SubscriptionPayment::query()->where('tenant_id', $new->id)->where('status', 'paid')->count())->toBe(2);
});

it('perpanjangan manual oleh admin juga memberi bonus ke pengajak yang masih coba', function () {
    $this->referrer->tenant->forceFill(['status' => 'trial', 'paid_until' => null, 'trial_ends_at' => now()->addDays(3)])->save();
    $new = registerWithRef($this, $this->code);
    $admin = new User;
    $admin->forceFill(['name' => 'Admin', 'phone' => '6289911112222', 'password' => Hash::make('x'), 'is_super_admin' => true, 'is_active' => true])->save();

    app(SubscriptionBilling::class)->extendManually($new, $admin->fresh(), 'pro', 1, 199000, 'transfer');

    expect($this->referrer->tenant->fresh()->trial_ends_at->toDateString())->toBe(now()->addDays(33)->toDateString());
});

it('halaman langganan menampilkan link referral', function () {
    $this->actingAs($this->referrer)->get(route('billing.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('referral.code', $this->code)->where('referral.url', fn ($url) => str_contains($url, 'ref='.$this->code)));
});
