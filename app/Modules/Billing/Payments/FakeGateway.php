<?php

namespace App\Modules\Billing\Payments;

use Illuminate\Support\Facades\Cache;

class FakeGateway implements PaymentGateway
{
    public function __construct(private string $secret) {}

    public function name(): string
    {
        return 'fake';
    }

    public function createCharge(ChargeRequest $charge): string
    {
        Cache::put($this->key($charge->reference), [
            'status' => 'pending',
            'amount' => $charge->amount,
            'finish' => $charge->finishUrl,
        ], now()->addDay());

        return route('billing.fake-pay', ['reference' => $charge->reference]);
    }

    public function pay(string $reference): ?string
    {
        $row = Cache::get($this->key($reference));
        if (! $row) {
            return null;
        }

        Cache::put($this->key($reference), [...$row, 'status' => 'paid'], now()->addDay());

        return $row['finish'];
    }

    public function fetchStatus(string $reference): ?PaymentResult
    {
        $row = Cache::get($this->key($reference));
        if (! $row) {
            return null;
        }

        return new PaymentResult($reference, $row['status'], 'qris', 'FAKE-'.$reference, $row['amount'], $row);
    }

    public function parseNotification(array $payload): ?PaymentResult
    {
        $reference = (string) ($payload['order_id'] ?? '');
        $amount = (int) ($payload['gross_amount'] ?? 0);

        if ($reference === '' || ! hash_equals($this->sign($reference, $amount), (string) ($payload['signature_key'] ?? ''))) {
            return null;
        }

        return new PaymentResult(
            $reference,
            ($payload['transaction_status'] ?? '') === 'settlement' ? 'paid' : 'pending',
            'qris',
            'FAKE-'.$reference,
            $amount,
            $payload,
        );
    }

    public function sign(string $reference, int $amount): string
    {
        return hash_hmac('sha256', $reference.'|'.$amount, $this->secret);
    }

    private function key(string $reference): string
    {
        return 'fake-payment:'.$reference;
    }
}
