<?php

namespace App\Modules\Billing\Payments;

use App\Models\Tenant;

class Gateways
{
    public function fakeAllowed(): bool
    {
        return config('hermes.payment.driver') === 'fake' && ! app()->isProduction();
    }

    public function platform(): ?PaymentGateway
    {
        if ($this->fakeAllowed()) {
            return $this->fake();
        }

        $config = config('hermes.payment.midtrans');
        if (config('hermes.payment.driver') !== 'midtrans' || blank($config['server_key'])) {
            return null;
        }

        return new MidtransGateway((string) $config['server_key'], (bool) $config['is_production']);
    }

    public function forTenant(Tenant $tenant, bool $fallbackToPlatform = true): ?PaymentGateway
    {
        if ($this->fakeAllowed()) {
            return $this->fake();
        }

        if (filled($tenant->midtrans_server_key)) {
            return new MidtransGateway((string) $tenant->midtrans_server_key, (bool) $tenant->midtrans_production);
        }

        if ($fallbackToPlatform) {
            return $this->platform();
        }

        return null;
    }

    public function fake(): FakeGateway
    {
        return new FakeGateway((string) config('app.key'));
    }
}
