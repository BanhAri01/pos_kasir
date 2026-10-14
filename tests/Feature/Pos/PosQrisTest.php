<?php

use App\Models\Tenant;
use App\Modules\Billing\Payments\FakeGateway;
use App\Modules\Billing\Payments\Gateways;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    config(['hermes.payment.driver' => 'fake']);
});

describe('uji fitur qris dinamis kasir & integrasi pembayaran sentral (moka pay)', function () {
    it('dapat menghasilkan kode qris dinamis untuk transaksi kasir', function () {
        $response = $this->actingAs($this->owner)->postJson(route('pos.api.qris.generate'), [
            'amount' => 50000,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'order_id',
                'amount',
                'status',
            ]);

        $orderId = $response->json('order_id');
        expect($orderId)->toContain("POS-{$this->tenant->id}-")
            ->and(Cache::has("pos_qris:{$orderId}"))->toBeTrue();
    });

    it('dapat mengecek status qris yang sedang menunggu pembayaran', function () {
        $orderId = "POS-{$this->tenant->id}-test-order-123";
        Cache::put("pos_qris:{$orderId}", [
            'tenant_id' => $this->tenant->id,
            'sale_uuid' => null,
            'amount' => 75000,
            'status' => 'pending',
        ], 3600);

        $response = $this->actingAs($this->owner)->getJson(route('pos.api.qris.check', $orderId));

        $response->assertOk()
            ->assertJson([
                'status' => 'pending',
                'order_id' => $orderId,
            ]);
    });

    it('memproses webhook settlement midtrans dan mengkreditkan dana ke dompet toko', function () {
        $orderId = "POS-{$this->tenant->id}-sale-uuid-789-261014";
        $amount = 100000;

        Cache::put("pos_qris:{$orderId}", [
            'tenant_id' => $this->tenant->id,
            'sale_uuid' => 'sale-uuid-789',
            'amount' => $amount,
            'status' => 'pending',
        ], 3600);

        $fake = new FakeGateway((string) config('app.key'));
        $signature = $fake->sign($orderId, $amount);

        $payload = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $amount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ];

        $response = $this->postJson(route('pos.qris.webhook'), $payload);

        $response->assertOk()
            ->assertJson(['status' => 'paid']);

        // Pastikan status di cache berubah menjadi paid
        $cached = Cache::get("pos_qris:{$orderId}");
        expect($cached['status'])->toBe('paid');

        // Pastikan dana masuk ke dompet toko (Rp 100.000 - 0.7% MDR = Rp 99.300)
        $wallet = $this->tenant->wallet()->first();
        expect($wallet)->not->toBeNull()
            ->and($wallet->balance)->toBe(99300)
            ->and($wallet->total_qris_income)->toBe(99300);

        // Pastikan mutasi wallet tercatat
        $mutation = \App\Modules\Billing\Models\WalletMutation::withoutGlobalScopes()->where('reference', $orderId)->first();
        expect($mutation)->not->toBeNull()
            ->and($mutation->amount)->toBe(100000)
            ->and($mutation->fee)->toBe(700)
            ->and($mutation->net_amount)->toBe(99300);
    });

    it('mencegah dobel kredit jika webhook midtrans terkirim berulang kali (idempotent)', function () {
        $orderId = "POS-{$this->tenant->id}-sale-idemp-111-261014";
        $amount = 50000;

        Cache::put("pos_qris:{$orderId}", [
            'tenant_id' => $this->tenant->id,
            'sale_uuid' => 'sale-idemp-111',
            'amount' => $amount,
            'status' => 'pending',
        ], 3600);

        $fake = new FakeGateway((string) config('app.key'));
        $signature = $fake->sign($orderId, $amount);

        $payload = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $amount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ];

        // Webhook pertama
        $this->postJson(route('pos.qris.webhook'), $payload)->assertOk();

        // Webhook kedua (retry dari midtrans)
        $this->postJson(route('pos.qris.webhook'), $payload)->assertOk();

        $wallet = $this->tenant->wallet()->first();
        // 50.000 - 0.7% (350) = 49.650 (hanya 1x masuk, tidak berlipat jadi 99.300)
        expect($wallet->balance)->toBe(49650);
    });

    it('dapat mensimulasikan pembayaran qris pada mode testing', function () {
        $orderId = "POS-{$this->tenant->id}-mock-order-222";
        Cache::put("pos_qris:{$orderId}", [
            'tenant_id' => $this->tenant->id,
            'sale_uuid' => 'mock-uuid-222',
            'amount' => 20000,
            'status' => 'pending',
        ], 3600);

        $response = $this->actingAs($this->owner)->postJson(route('pos.api.qris.simulate', $orderId));

        $response->assertOk()
            ->assertJson(['status' => 'paid']);

        $cached = Cache::get("pos_qris:{$orderId}");
        expect($cached['status'])->toBe('paid');

        $wallet = $this->tenant->wallet()->first();
        expect($wallet->balance)->toBe(19860); // 20.000 - 0.7% (140) = 19.860
    });
});

