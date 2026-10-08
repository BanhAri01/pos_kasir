<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Services\PaymentFee;
use App\Modules\Billing\Services\PlanGuard;
use App\Modules\Billing\Services\Plans;
use App\Modules\Billing\Services\ReferralService;
use App\Modules\Billing\Services\SubscriptionBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller
{
    public function __construct(private SubscriptionBilling $billing, private TenantContext $context) {}

    public function index(Request $request, PlanGuard $guard, ReferralService $referrals): Response
    {
        $tenant = $this->context->get();
        $catalog = Plans::catalog();

        foreach ($catalog['plans'] as &$plan) {
            $plan['options'] = [];
            foreach ($plan['prices'] as $months => $base) {
                $plan['options'][$months] = PaymentFee::options($base);
            }
        }
        unset($plan);

        return Inertia::render('Billing/Index', [
            'subscription' => self::status($tenant),
            'catalog' => $catalog,
            'usage' => $guard->usage($tenant),
            'canPay' => $request->user()->can(Permission::ManageBusiness->value),
            'onlineAvailable' => $this->billing->available(),
            'referral' => $referrals->summary($tenant),
            'payments' => SubscriptionPayment::query()->where('tenant_id', $tenant->id)->latest('id')->limit(20)->get()
                ->map->summary()->values(),
        ]);
    }

    public function checkout(Request $request): HttpResponse
    {
        abort_unless($request->user()->can(Permission::ManageBusiness->value), 403);

        $data = $request->validate([
            'plan' => ['required', Rule::in(Plans::keys())],
            'months' => ['required', 'integer', Rule::in(array_keys(Plans::durations()))],
            'channel' => ['required', Rule::in(array_keys(PaymentFee::channels()))],
        ], [
            'plan.required' => 'Pilih paket dulu.',
            'channel.required' => 'Pilih cara bayar dulu.',
        ]);

        $payment = $this->billing->start($this->context->get(), $request->user(), $data['plan'], (int) $data['months'], $data['channel']);

        return Inertia::location($payment->redirect_url);
    }

    public function finish(Request $request): RedirectResponse
    {
        $payment = SubscriptionPayment::query()
            ->where('tenant_id', $this->context->id())
            ->where('reference', (string) $request->query('ref', $request->query('order_id', '')))
            ->first();

        if (! $payment) {
            return redirect()->route('billing.index')->with('info', 'Status pembayaran akan diperbarui otomatis setelah pembayaran diterima.');
        }

        $payment = $this->billing->refresh($payment);

        return match ($payment->status) {
            'paid' => redirect()->route('billing.index')->with('success', 'Pembayaran diterima. Langganan aktif sampai '.$payment->period_until?->translatedFormat('j F Y').'. Terima kasih!'),
            'pending' => redirect()->route('billing.index')->with('info', 'Pembayaran belum kami terima. Kalau sudah membayar, tunggu 1-2 menit lalu buka lagi halaman ini.'),
            default => redirect()->route('billing.index')->with('error', 'Pembayaran tidak berhasil atau sudah kedaluwarsa. Silakan buat tagihan baru.'),
        };
    }

    public function webhook(Request $request): JsonResponse
    {
        $gateway = $this->billing->gateway();
        $result = $gateway?->parseNotification($request->all());

        if (! $result) {
            return response()->json(['message' => 'invalid'], 403);
        }

        $this->billing->apply($result);

        return response()->json(['message' => 'ok']);
    }

    public function fakePay(Request $request, Gateways $gateways, string $reference): RedirectResponse
    {
        abort_unless($gateways->fakeAllowed(), 404);

        $finish = $gateways->fake()->pay($reference);
        abort_unless($finish !== null, 404);

        return redirect()->to($finish);
    }

    public static function status(Tenant $tenant): array
    {
        $ends = $tenant->accessEndsAt();

        return [
            'plan' => $tenant->planKey(),
            'plan_label' => $tenant->planLabel(),
            'status' => $tenant->status,
            'on_trial' => $tenant->status === 'trial',
            'unlimited' => $tenant->hasUnlimitedAccess(),
            'suspended' => $tenant->isSuspended(),
            'has_access' => $tenant->hasAccess(),
            'in_grace' => $tenant->isInGrace(),
            'grace_days_left' => $tenant->graceDaysLeft(),
            'days_left' => $tenant->daysLeft(),
            'ends_at' => $ends?->translatedFormat('j F Y'),
        ];
    }
}
