<?php

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Payments\ChargeRequest;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Payments\PaymentGateway;
use App\Modules\Billing\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubscriptionBilling
{
    public function __construct(private Gateways $gateways) {}

    public function gateway(): ?PaymentGateway
    {
        return $this->gateways->platform();
    }

    public function available(): bool
    {
        return $this->gateway() !== null;
    }

    public function start(Tenant $tenant, User $user, string $plan, int $months, string $channel): SubscriptionPayment
    {
        if (! Plans::exists($plan) || ! isset(Plans::durations()[$months]) || ! PaymentFee::exists($channel)) {
            throw ValidationException::withMessages(['plan' => 'Pilih paket, lama langganan, dan cara bayar yang tersedia.']);
        }

        $gateway = $this->gateway();
        if (! $gateway) {
            throw ValidationException::withMessages(['plan' => 'Bayar online belum aktif. Silakan hubungi admin lewat WhatsApp.']);
        }
        if ($tenant->isSuspended()) {
            throw ValidationException::withMessages(['plan' => 'Usaha ini sedang dibekukan. Silakan hubungi admin.']);
        }
        if ($tenant->hasUnlimitedAccess()) {
            throw ValidationException::withMessages(['plan' => 'Langganan Anda aktif tanpa batas waktu, tidak perlu membayar.']);
        }

        $base = Plans::price($plan, $months);
        $fee = PaymentFee::feeFor($base, $channel);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'reference' => 'HPOS-'.$tenant->id.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
            'gateway' => $gateway->name(),
            'plan' => $plan,
            'months' => $months,
            'channel' => $channel,
            'base_amount' => $base,
            'fee_amount' => $fee,
            'amount' => $base + $fee,
            'status' => 'pending',
        ]);

        $items = [[
            'id' => 'HPOS-'.strtoupper($plan).'-'.$months,
            'name' => 'Hermes POS '.Plans::label($plan).' '.$months.' bulan',
            'price' => $base,
            'quantity' => 1,
        ]];
        if ($fee > 0) {
            $items[] = ['id' => 'BIAYA-BAYAR', 'name' => 'Biaya layanan pembayaran', 'price' => $fee, 'quantity' => 1];
        }

        try {
            $redirect = $gateway->createCharge(new ChargeRequest(
                reference: $payment->reference,
                amount: $payment->amount,
                items: $items,
                customer: ['name' => $user->name, 'phone' => $tenant->phone ?? $user->phone],
                enabledPayments: PaymentFee::enabledPayments($channel),
                finishUrl: route('billing.finish', ['ref' => $payment->reference]),
                expiryMinutes: (int) config('hermes.payment.expiry_minutes'),
            ));
        } catch (RuntimeException $e) {
            $payment->update(['status' => 'failed', 'note' => Str::limit($e->getMessage(), 250)]);
            Log::warning('Gagal membuat tagihan langganan '.$payment->reference.': '.$e->getMessage());

            throw ValidationException::withMessages(['plan' => 'Gagal membuat tagihan. '.$e->getMessage()]);
        }

        $payment->update(['redirect_url' => $redirect]);

        return $payment;
    }

    public function refresh(SubscriptionPayment $payment): SubscriptionPayment
    {
        if ($payment->status !== 'pending' || ! ($gateway = $this->gateway()) || $gateway->name() !== $payment->gateway) {
            return $payment;
        }

        $result = $gateway->fetchStatus($payment->reference);

        return $result ? ($this->apply($result) ?? $payment) : $payment;
    }

    public function apply(PaymentResult $result): ?SubscriptionPayment
    {
        return DB::transaction(function () use ($result) {
            $payment = SubscriptionPayment::query()->where('reference', $result->reference)->lockForUpdate()->first();

            if (! $payment) {
                Log::warning('Notifikasi pembayaran langganan untuk referensi tidak dikenal: '.$result->reference);

                return null;
            }

            $payment->last_payload = $result->raw;

            if ($payment->isPaid()) {
                $payment->save();

                return $payment;
            }

            if ($result->isPaid() && $result->amount !== null && $result->amount !== $payment->amount) {
                Log::error("Nominal pembayaran tidak cocok untuk {$payment->reference}: {$result->amount} vs {$payment->amount}");
                $payment->save();

                return $payment;
            }

            if (! $result->isPaid()) {
                if ($result->status !== 'pending') {
                    $payment->status = $result->status;
                }
                $payment->save();

                return $payment;
            }

            $tenant = Tenant::query()->whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();

            $payment->fill([
                'status' => 'paid',
                'method' => $result->method,
                'gateway_ref' => $result->gatewayRef,
                'paid_at' => now(),
            ]);

            if ($tenant->isSuspended()) {
                $payment->save();
                Log::warning("Pembayaran {$payment->reference} diterima untuk usaha yang dibekukan; tidak diperpanjang otomatis.");

                return $payment;
            }

            [$from, $until] = $tenant->extendSubscription($payment->months, $payment->plan);
            $payment->fill(['period_from' => $from->toDateString(), 'period_until' => $until->toDateString()])->save();
            app(ReferralService::class)->rewardFor($tenant, $payment);

            activity('langganan')->performedOn($tenant)->log(sprintf(
                'Langganan %s %d bulan dibayar Rp%s, aktif sampai %s',
                Plans::label($payment->plan),
                $payment->months,
                number_format($payment->amount, 0, ',', '.'),
                $until->translatedFormat('j F Y'),
            ));

            return $payment;
        });
    }

    public function extendManually(Tenant $tenant, User $admin, string $plan, int $months, ?int $amount, ?string $note): SubscriptionPayment
    {
        if (! Plans::exists($plan) || $months < 1 || $months > 36) {
            throw ValidationException::withMessages(['months' => 'Pilih paket dan lama langganan (1 sampai 36 bulan).']);
        }

        $payment = DB::transaction(function () use ($tenant, $admin, $plan, $months, $amount, $note) {
            $tenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            [$from, $until] = $tenant->extendSubscription($months, $plan);
            $base = $amount ?? (isset(Plans::durations()[$months]) ? Plans::price($plan, $months) : Plans::get($plan)['price'] * $months);

            return SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'reference' => 'MANUAL-'.$tenant->id.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
                'gateway' => 'manual',
                'plan' => $plan,
                'months' => $months,
                'channel' => 'manual',
                'base_amount' => $base,
                'fee_amount' => 0,
                'amount' => $base,
                'status' => 'paid',
                'method' => 'manual',
                'period_from' => $from->toDateString(),
                'period_until' => $until->toDateString(),
                'note' => $note,
                'paid_at' => now(),
            ]);
        });

        app(ReferralService::class)->rewardFor($payment->tenant()->first(), $payment);

        return $payment;
    }
}
