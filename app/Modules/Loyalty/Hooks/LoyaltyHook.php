<?php

namespace App\Modules\Loyalty\Hooks;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;
use App\Modules\WhatsApp\Services\WhatsAppService;

class LoyaltyHook implements SaleHook
{
    public function __construct(
        private TenantContext $context,
        private LoyaltyService $loyalty,
        private WhatsAppService $whatsapp,
    ) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        $tenant = $this->context->get();
        if (! $tenant->hasModule('loyalty') || ! $sale->customer_id) {
            return;
        }

        $earned = $this->loyalty->applySale($sale, $data, $strict);
        $settings = LoyaltyService::settings($tenant);

        if ($earned <= 0 || ! $settings['notify'] || ! $tenant->hasModule('whatsapp_notification')) {
            return;
        }

        $customer = Customer::withTrashed()->find($sale->customer_id);
        if (! $customer?->phone) {
            return;
        }

        $this->whatsapp->sendTemplate('loyalty_points', $customer->phone, [
            'nama' => $customer->name,
            'poin' => $earned,
            'total' => $customer->loyalty_points,
            'target' => $settings['points_for_reward'],
            'hadiah' => Rupiah::format($settings['reward_value']),
        ], $sale);
    }
}
