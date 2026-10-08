<?php

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Modules\Billing\Models\SubscriptionPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function codeFor(Tenant $tenant): string
    {
        if ($tenant->referral_code) {
            return $tenant->referral_code;
        }

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Tenant::query()->where('referral_code', $code)->exists());

        $tenant->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function findReferrer(?string $code): ?Tenant
    {
        $code = Str::upper(trim((string) $code));
        if (! preg_match('/^[A-Z0-9]{6,12}$/', $code)) {
            return null;
        }

        return Tenant::query()->where('referral_code', $code)->where('status', '!=', 'suspended')->first();
    }

    public function attach(Tenant $tenant, ?string $code): bool
    {
        $referrer = $this->findReferrer($code);
        if (! $referrer || $referrer->id === $tenant->id || $tenant->referred_by_id) {
            return false;
        }

        $extra = (int) config('hermes.referral.new_tenant_bonus_days');
        $tenant->forceFill([
            'referred_by_id' => $referrer->id,
            'trial_ends_at' => $extra > 0 && $tenant->trial_ends_at ? $tenant->trial_ends_at->copy()->addDays($extra) : $tenant->trial_ends_at,
        ])->save();

        return true;
    }

    public function rewardFor(Tenant $referred, SubscriptionPayment $payment): void
    {
        if (! $referred->referred_by_id || ! $payment->isPaid() || $payment->amount <= 0) {
            return;
        }

        DB::transaction(function () use ($referred, $payment) {
            if (DB::table('referral_rewards')->where('referred_tenant_id', $referred->id)->exists()) {
                return;
            }

            $referrer = Tenant::query()->whereKey($referred->referred_by_id)->lockForUpdate()->first();
            if (! $referrer) {
                return;
            }

            $days = (int) config('hermes.referral.reward_days');
            $granted = $this->extend($referrer, $days);

            DB::table('referral_rewards')->insert([
                'referrer_tenant_id' => $referrer->id,
                'referred_tenant_id' => $referred->id,
                'subscription_payment_id' => $payment->id,
                'days' => $granted,
                'note' => $granted ? "Bonus {$granted} hari karena {$referred->name} berlangganan" : 'Tidak ditambah (usaha tanpa batas / dibekukan)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            activity('referral')->performedOn($referrer)->log("Bonus referral {$granted} hari dari {$referred->name}");
        });
    }

    public function summary(Tenant $tenant): array
    {
        return [
            'code' => $this->codeFor($tenant),
            'url' => route('register', ['ref' => $this->codeFor($tenant)]),
            'joined' => Tenant::query()->where('referred_by_id', $tenant->id)->count(),
            'rewarded' => DB::table('referral_rewards')->where('referrer_tenant_id', $tenant->id)->count(),
            'days' => (int) DB::table('referral_rewards')->where('referrer_tenant_id', $tenant->id)->sum('days'),
            'reward_days' => (int) config('hermes.referral.reward_days'),
            'bonus_days' => (int) config('hermes.referral.new_tenant_bonus_days'),
        ];
    }

    private function extend(Tenant $tenant, int $days): int
    {
        if ($days <= 0 || $tenant->isSuspended() || $tenant->hasUnlimitedAccess()) {
            return 0;
        }

        $today = Carbon::today();

        if ($tenant->status === 'active') {
            $base = $tenant->paid_until && $tenant->paid_until->gte($today) ? $tenant->paid_until->copy() : $today->copy()->subDay();
            $tenant->forceFill(['paid_until' => $base->addDays($days)->toDateString()])->save();
        } else {
            $base = $tenant->trial_ends_at && $tenant->trial_ends_at->isFuture() ? $tenant->trial_ends_at->copy() : now();
            $tenant->forceFill(['trial_ends_at' => $base->addDays($days)])->save();
        }

        return $days;
    }
}
