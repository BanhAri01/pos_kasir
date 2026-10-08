<?php

namespace App\Modules\Billing\Payments;

final class ChargeRequest
{
    public function __construct(
        public readonly string $reference,
        public readonly int $amount,
        public readonly array $items,
        public readonly array $customer,
        public readonly array $enabledPayments,
        public readonly string $finishUrl,
        public readonly ?string $notificationUrl = null,
        public readonly int $expiryMinutes = 60,
    ) {}
}
