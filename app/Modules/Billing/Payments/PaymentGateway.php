<?php

namespace App\Modules\Billing\Payments;

interface PaymentGateway
{
    public function name(): string;

    public function createCharge(ChargeRequest $charge): string;

    public function fetchStatus(string $reference): ?PaymentResult;

    public function parseNotification(array $payload): ?PaymentResult;
}
