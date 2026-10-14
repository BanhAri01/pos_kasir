<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Payments\MidtransGateway;
use App\Modules\Billing\Services\WalletService;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SalePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosQrisController extends Controller
{
    /**
     * Kasir membuat QRIS dinamis untuk transaksi saat ini.
     */
    public function generate(Request $request, TenantContext $context, Gateways $gateways): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
            'sale_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $tenant = $context->get();
        $gateway = $gateways->forTenant($tenant);

        if (! $gateway) {
            return response()->json([
                'message' => 'Layanan QRIS otomatis belum aktif. Hubungi admin.',
            ], 422);
        }

        $saleUuid = ! empty($data['sale_uuid']) ? $data['sale_uuid'] : (string) Str::uuid();
        $timestamp = now()->format('ymdHis');
        $orderId = "POS-{$tenant->id}-{$saleUuid}-{$timestamp}";

        // Simpan metadata order di cache untuk pencocokan cepat
        Cache::put("pos_qris:{$orderId}", [
            'tenant_id' => $tenant->id,
            'sale_uuid' => $saleUuid,
            'amount' => $data['amount'],
            'status' => 'pending',
        ], now()->addHours(2));

        if ($gateway instanceof MidtransGateway) {
            try {
                $webhookUrl = route('pos.qris.webhook');
                $result = $gateway->createDirectQris($orderId, $data['amount'], $webhookUrl);

                $qrUrl = $result['qr_url'] ?? null;
                $qrString = $result['qr_string'] ?? null;

                if (empty($qrUrl) && ! empty($qrString)) {
                    $qrUrl = $this->generateQrSvgDataUri($qrString);
                }

                return response()->json([
                    'order_id' => $orderId,
                    'amount' => $data['amount'],
                    'qr_string' => $qrString,
                    'qr_url' => $qrUrl,
                    'status' => 'pending',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Gagal membuat QRIS Midtrans POS: '.$e->getMessage(), ['order_id' => $orderId]);

                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        // Mode Fake / Testing
        $mockQrString = "00020101021226590014ID.LINKAJA.WWW011893600911002239472302150000{$orderId}520454995303360540{$data['amount']}5802ID5911HERMES_POS6007JAKARTA6304";
        return response()->json([
            'order_id' => $orderId,
            'amount' => $data['amount'],
            'qr_string' => $mockQrString,
            'qr_url' => $this->generateQrSvgDataUri($mockQrString),
            'status' => 'pending',
            'is_mock' => true,
        ]);
    }

    /**
     * Simulasi pembayaran berhasil (khusus mode testing / pengujian lokal).
     */
    public function simulate(Request $request, string $orderId, TenantContext $context, WalletService $wallets): JsonResponse
    {
        $cached = Cache::get("pos_qris:{$orderId}");
        if (! $cached) {
            return response()->json(['message' => 'Transaksi QRIS tidak ditemukan atau telah kedaluwarsa.'], 404);
        }

        $tenant = $context->get();
        $this->markOrderPaid($orderId, $cached, $tenant, (int) ($cached['amount'] ?? 0), $wallets);

        return response()->json(['status' => 'paid', 'order_id' => $orderId]);
    }

    /**
     * Konversi string QRIS EMVCo menjadi gambar SVG Data URI scannable.
     */
    private function generateQrSvgDataUri(string $content): string
    {
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(280),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($content);

            return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
        } catch (\Throwable) {
            return '';
        }
    }


    /**
     * Kasir mengecek status pembayaran QRIS (polling otomatis tiap beberapa detik).
     */
    public function check(Request $request, string $orderId, TenantContext $context, Gateways $gateways, WalletService $wallets): JsonResponse
    {
        $cached = Cache::get("pos_qris:{$orderId}");
        if ($cached && ($cached['status'] ?? '') === 'paid') {
            return response()->json(['status' => 'paid', 'order_id' => $orderId]);
        }

        $tenant = $context->get();
        $gateway = $gateways->forTenant($tenant);

        if ($gateway) {
            $result = $gateway->fetchStatus($orderId);
            if ($result && $result->status === 'paid') {
                $this->markOrderPaid($orderId, $cached, $tenant, $result->amount ?? ($cached['amount'] ?? 0), $wallets);

                return response()->json(['status' => 'paid', 'order_id' => $orderId]);
            }
        }

        return response()->json([
            'status' => $cached['status'] ?? 'pending',
            'order_id' => $orderId,
        ]);
    }

    /**
     * Webhook notifikasi pembayaran dari Midtrans.
     */
    public function webhook(Request $request, Gateways $gateways, WalletService $wallets): JsonResponse
    {
        $payload = $request->all();
        $orderId = (string) ($payload['order_id'] ?? '');

        if (! str_starts_with($orderId, 'POS-')) {
            return response()->json(['message' => 'ignored'], 200);
        }

        // Format order_id: POS-{tenant_id}-{sale_uuid}-{timestamp}
        $parts = explode('-', $orderId);
        $tenantId = (int) ($parts[1] ?? 0);
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json(['message' => 'tenant not found'], 404);
        }

        $gateway = $gateways->forTenant($tenant);
        if (! $gateway) {
            return response()->json(['message' => 'no gateway configured'], 400);
        }

        $result = $gateway->parseNotification($payload);
        if (! $result) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        $cached = Cache::get("pos_qris:{$orderId}");
        $grossAmount = $result->amount ?? ($cached['amount'] ?? (int) round((float) ($payload['gross_amount'] ?? 0)));

        if ($result->status === 'paid') {
            $this->markOrderPaid($orderId, $cached, $tenant, $grossAmount, $wallets);

            return response()->json(['message' => 'ok', 'status' => 'paid']);
        }

        if (in_array($result->status, ['failed', 'expired'], true)) {
            if ($cached) {
                Cache::put("pos_qris:{$orderId}", array_merge($cached, ['status' => $result->status]), now()->addHours(2));
            }
        }

        return response()->json(['message' => 'ok', 'status' => $result->status]);
    }

    /**
     * Tandai pesanan QRIS lunas, tambahkan saldo ke dompet toko, dan update data kasir jika ada.
     */
    private function markOrderPaid(string $orderId, ?array $cached, Tenant $tenant, int $amount, WalletService $wallets): void
    {
        // Cegah dobel kredit dompet (idempotent)
        $lockKey = "pos_qris_credited:{$orderId}";
        if (! Cache::add($lockKey, 1, now()->addDays(7))) {
            return;
        }

        // 1. Masukkan dana ke Dompet Toko
        $wallets->creditQris(
            tenant: $tenant,
            grossAmount: $amount,
            reference: $orderId,
            description: "Pembayaran QRIS Kasir ({$orderId})",
            meta: ['order_id' => $orderId, 'sale_uuid' => $cached['sale_uuid'] ?? null]
        );

        // 2. Update status di cache
        if ($cached) {
            Cache::put("pos_qris:{$orderId}", array_merge($cached, ['status' => 'paid']), now()->addHours(2));
        }

        // 3. Jika Sale sudah ada di DB berdasarkan sale_uuid, pastikan pembayaran tercatat
        if (! empty($cached['sale_uuid'])) {
            $sale = Sale::withoutGlobalScopes()->where('uuid', $cached['sale_uuid'])->first();
            if ($sale) {
                $method = PaymentMethod::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('type', 'qris')
                    ->first();

                SalePayment::firstOrCreate(
                    ['sale_id' => $sale->id, 'reference' => $orderId],
                    [
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'payment_method_id' => $method?->id,
                        'method_type' => 'qris',
                        'method_name' => 'QRIS (HermesPay)',
                        'amount' => $amount,
                        'paid_at' => now(),
                    ]
                );
            }
        }
    }
}
