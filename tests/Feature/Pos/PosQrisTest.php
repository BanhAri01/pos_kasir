<?php

use App\Modules\Billing\Payments\Gateways;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
});

function midtransStatus(string $orderId, int $amount, string $status, string $key): array
{
    $gross = $amount.'.00';

    return [
        'order_id' => $orderId,
        'status_code' => '200',
        'gross_amount' => $gross,
        'transaction_status' => $status,
        'payment_type' => 'qris',
        'signature_key' => hash('sha512', $orderId.'200'.$gross.$key),
    ];
}

describe('QRIS dinamis kasir memakai akun Midtrans milik usaha', function () {
    it('mode uji (fake) membuat QR, lalu simulasi bayar menandai lunas', function () {
        config(['hermes.payment.driver' => 'fake']);

        $res = $this->actingAs($this->owner)->postJson(route('pos.api.qris.generate'), ['amount' => 50000])
            ->assertOk()->assertJson(['is_mock' => true, 'status' => 'pending']);
        $orderId = $res->json('order_id');

        expect($orderId)->toStartWith("POS-{$this->tenant->id}-")
            ->and($res->json('qr_url'))->toStartWith('data:image/svg+xml');

        $this->actingAs($this->owner)->getJson(route('pos.api.qris.check', $orderId))->assertJson(['status' => 'pending']);
        $this->actingAs($this->owner)->postJson(route('pos.api.qris.simulate', $orderId))->assertOk();
        $this->actingAs($this->owner)->getJson(route('pos.api.qris.check', $orderId))->assertJson(['status' => 'paid']);
    });

    it('tidak pernah memakai akun Midtrans platform untuk uang toko', function () {
        config([
            'hermes.payment.driver' => 'midtrans',
            'hermes.payment.midtrans.server_key' => 'SB-Mid-server-PLATFORM',
        ]);
        Http::fake();

        expect(app(Gateways::class)->forTenant($this->tenant))->toBeNull();

        $this->actingAs($this->owner)->postJson(route('pos.api.qris.generate'), ['amount' => 50000])->assertStatus(422);

        Http::assertNothingSent();
    });

    it('memakai kunci Midtrans toko dan hanya lunas bila nominal cocok', function () {
        config(['hermes.payment.driver' => 'midtrans']);
        $key = 'SB-Mid-server-TOKO';
        $this->tenant->forceFill(['midtrans_server_key' => $key, 'midtrans_production' => false])->save();

        $orderId = null;
        $paidAmount = 1000;
        Http::fake(function ($request) use ($key, &$orderId, &$paidAmount) {
            expect($request->header('Authorization')[0])->toBe('Basic '.base64_encode($key.':'));

            return $request->method() === 'POST'
                ? Http::response(['status_code' => '201', 'qr_string' => '00020101021226TEST', 'transaction_status' => 'pending'])
                : Http::response(midtransStatus($orderId, $paidAmount, 'settlement', $key));
        });

        $res = $this->actingAs($this->owner)->postJson(route('pos.api.qris.generate'), ['amount' => 50000])->assertOk();
        $orderId = $res->json('order_id');
        expect($res->json('is_mock'))->toBeFalse();

        $this->actingAs($this->owner)->getJson(route('pos.api.qris.check', $orderId))->assertJson(['status' => 'pending']);

        $paidAmount = 50000;
        $this->actingAs($this->owner)->getJson(route('pos.api.qris.check', $orderId))->assertJson(['status' => 'paid']);
    });

    it('simulasi bayar tertutup di production', function () {
        config(['hermes.payment.driver' => 'fake']);
        $orderId = "POS-{$this->tenant->id}-abc";
        Cache::put("pos_qris:{$orderId}", ['tenant_id' => $this->tenant->id, 'amount' => 10000, 'status' => 'pending'], 600);

        app()->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($this->owner)->postJson(route('pos.api.qris.simulate', $orderId))->assertNotFound();
        expect(Cache::get("pos_qris:{$orderId}")['status'])->toBe('pending');
    });

    it('QR milik usaha lain tidak bisa dicek atau disimulasikan', function () {
        config(['hermes.payment.driver' => 'fake']);
        $other = registerTenant('toko_kelontong', '6281200000999');
        $orderId = "POS-{$other->tenant->id}-xyz";
        Cache::put("pos_qris:{$orderId}", ['tenant_id' => $other->tenant->id, 'amount' => 10000, 'status' => 'pending'], 600);

        $this->actingAs($this->owner)->getJson(route('pos.api.qris.check', $orderId))->assertNotFound();
        $this->actingAs($this->owner)->postJson(route('pos.api.qris.simulate', $orderId))->assertNotFound();
        expect(Cache::get("pos_qris:{$orderId}")['status'])->toBe('pending');
    });
});
