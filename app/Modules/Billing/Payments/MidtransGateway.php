<?php

namespace App\Modules\Billing\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MidtransGateway implements PaymentGateway
{
    public function __construct(private string $serverKey, private bool $production) {}

    public function name(): string
    {
        return 'midtrans';
    }

    public function createCharge(ChargeRequest $charge): string
    {
        $headers = $charge->notificationUrl ? ['X-Override-Notification' => $charge->notificationUrl] : [];

        try {
            $response = $this->client($this->snapUrl())->withHeaders($headers)->post('/snap/v1/transactions', [
                'transaction_details' => ['order_id' => $charge->reference, 'gross_amount' => $charge->amount],
                'customer_details' => array_filter([
                    'first_name' => Str::limit((string) ($charge->customer['name'] ?? 'Pelanggan'), 50, ''),
                    'phone' => $charge->customer['phone'] ?? null,
                ]),
                'item_details' => array_map(fn (array $item) => [
                    'id' => Str::limit((string) $item['id'], 50, ''),
                    'name' => Str::limit((string) $item['name'], 50, ''),
                    'price' => (int) $item['price'],
                    'quantity' => (int) $item['quantity'],
                ], $charge->items),
                'enabled_payments' => $charge->enabledPayments,
                'expiry' => ['unit' => 'minute', 'duration' => $charge->expiryMinutes],
                'callbacks' => ['finish' => $charge->finishUrl],
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Tidak bisa terhubung ke Midtrans. Coba lagi sebentar.', previous: $exception);
        }

        if ($response->failed() || ! $response->json('redirect_url')) {
            throw new RuntimeException('Midtrans menolak transaksi: '.implode(', ', (array) $response->json('error_messages', [$response->status()])));
        }

        return (string) $response->json('redirect_url');
    }

    public function fetchStatus(string $reference): ?PaymentResult
    {
        try {
            $response = $this->client($this->coreUrl())->get('/v2/'.rawurlencode($reference).'/status');
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed() || (string) $response->json('status_code') === '404') {
            return null;
        }

        return $this->parseNotification((array) $response->json());
    }

    public function parseNotification(array $payload): ?PaymentResult
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $expected = hash('sha512', $orderId.($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$this->serverKey);

        if ($orderId === '' || $this->serverKey === '' || ! hash_equals($expected, (string) ($payload['signature_key'] ?? ''))) {
            return null;
        }

        $status = match ((string) ($payload['transaction_status'] ?? '')) {
            'capture' => ($payload['fraud_status'] ?? 'accept') === 'accept' ? 'paid' : 'pending',
            'settlement' => 'paid',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };

        $method = (string) ($payload['payment_type'] ?? '');

        return new PaymentResult(
            reference: $orderId,
            status: $status,
            method: $method !== '' ? $method : null,
            gatewayRef: $payload['transaction_id'] ?? null,
            amount: isset($payload['gross_amount']) ? (int) round((float) $payload['gross_amount']) : null,
            raw: $payload,
        );
    }

    public function keyIsValid(): ?bool
    {
        try {
            $response = $this->client($this->coreUrl())->get('/v2/CEK-KEY-'.Str::random(10).'/status');
        } catch (ConnectionException) {
            return null;
        }

        $code = (string) $response->json('status_code', (string) $response->status());

        return match (true) {
            $code === '401' || $response->status() === 401 => false,
            in_array($code, ['404', '200', '201', '407'], true) => true,
            default => null,
        };
    }

    private function client(string $baseUrl): PendingRequest
    {
        if ($this->serverKey === '') {
            throw new RuntimeException('Server key Midtrans belum diisi.');
        }

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300, fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false);
    }

    private function snapUrl(): string
    {
        return $this->production ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function coreUrl(): string
    {
        return $this->production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}
