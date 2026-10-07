<?php

namespace App\Modules\Operations\Hooks;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Operations\Models\MembershipPlan;
use App\Modules\Operations\Services\MembershipService;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;
use Illuminate\Validation\ValidationException;

/** Menjual paket member di kasir otomatis mengaktifkan / memperpanjang keanggotaan pelanggan. */
class MembershipHook implements SaleHook
{
    public function __construct(
        private TenantContext $context,
        private MembershipService $memberships,
    ) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        $tenant = $this->context->get();
        if (! $tenant->hasModule('membership')) {
            return;
        }

        $plans = MembershipPlan::query()->where('is_active', true)
            ->whereIn('product_id', $sale->items()->pluck('product_id')->filter())
            ->get()->keyBy('product_id');

        if ($plans->isEmpty()) {
            return;
        }

        $customer = $sale->customer;
        if (! $customer) {
            if ($strict) {
                throw ValidationException::withMessages(['customer' => 'Pilih pelanggan dulu untuk menjual paket member.']);
            }
            activity('member')->performedOn($sale)->log("Paket member terjual tanpa nama pelanggan (nota {$sale->number}). Aktifkan manual.");

            return;
        }

        foreach ($sale->items as $item) {
            if ($plan = $plans->get($item->product_id)) {
                foreach (range(1, max(1, (int) floor((float) $item->qty))) as $i) {
                    $this->memberships->activate($customer, $plan, $sale->id, $tenant->timezone);
                }
            }
        }
    }
}
