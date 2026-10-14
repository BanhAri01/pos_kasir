<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Payments\FakeGateway;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Payments\MidtransGateway;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * QRIS dinamis di kasir. Uang selalu masuk ke akun Midtrans milik usaha itu sendiri,
 * tidak pernah lewat akun platform. Status dicek langsung ke Midtrans (server ke server).
 */
class PosQrisController extends Controller
{
    private const TTL_MINUTES = 30;

    public function generate(Request $request, TenantContext $context, Gateways $gateways): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:100000000'],
        ]);

        $tenant = $context->get();
        $gateway = $gateways->forTenant($tenant);
        $amount = (int) $data['amount'];
        $orderId = 'POS-'.$tenant->id.'-'.Str::lower(Str::random(20));

        if ($gateway instanceof FakeGateway) {
            $qrString = 'HERMES-FAKE-QRIS|'.$orderId.'|'.$amount;
        } elseif ($gateway instanceof MidtransGateway) {
            try {
                $qrString = $gateway->createDirectQris($orderId, $amount)['qr_string'];
            } catch (Throwable $e) {
                Log::warning('Gagal membuat QRIS kasir: '.$e->getMessage(), ['tenant' => $tenant->id]);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            return response()->json([
                'message' => 'QRIS otomatis belum aktif. Pemilik perlu mengisi kunci Midtrans milik usaha di Pengaturan > Pembayaran Online. Sementara itu pakai QRIS cetak toko dan cek uang masuk di HP.',
            ], 422);
        }

        if ($qrString === '') {
            return response()->json(['message' => 'Midtrans tidak mengirim kode QRIS. Coba lagi.'], 422);
        }

        Cache::put($this->key($orderId), ['tenant_id' => $tenant->id, 'amount' => $amount, 'status' => 'pending'], now()->addMinutes(self::TTL_MINUTES));

        return response()->json([
            'order_id' => $orderId,
            'amount' => $amount,
            'qr_url' => $this->qrSvg($qrString),
            'status' => 'pending',
            'is_mock' => $gateway instanceof FakeGateway,
        ]);
    }

    public function check(string $orderId, TenantContext $context, Gateways $gateways): JsonResponse
    {
        $tenant = $context->get();
        $cached = $this->owned($orderId, $tenant->id);

        if ($cached['status'] === 'paid') {
            return response()->json(['status' => 'paid', 'order_id' => $orderId]);
        }

        $result = $gateways->forTenant($tenant)?->fetchStatus($orderId);
        $status = $result?->status ?? 'pending';

        if ($status === 'paid' && (int) $result->amount !== (int) $cached['amount']) {
            Log::warning('Nominal QRIS kasir tidak cocok', ['order' => $orderId, 'expected' => $cached['amount'], 'got' => $result->amount]);
            $status = 'pending';
        }

        if ($status !== 'pending') {
            Cache::put($this->key($orderId), [...$cached, 'status' => $status], now()->addMinutes(self::TTL_MINUTES));
        }

        return response()->json(['status' => $status, 'order_id' => $orderId]);
    }

    /** Hanya untuk uji lokal dengan driver pembayaran palsu; di production selalu 404. */
    public function simulate(string $orderId, TenantContext $context, Gateways $gateways): JsonResponse
    {
        abort_unless($gateways->fakeAllowed(), 404);

        $cached = $this->owned($orderId, $context->id());
        Cache::put($this->key($orderId), [...$cached, 'status' => 'paid'], now()->addMinutes(self::TTL_MINUTES));

        return response()->json(['status' => 'paid', 'order_id' => $orderId]);
    }

    private function owned(string $orderId, int $tenantId): array
    {
        $cached = Cache::get($this->key($orderId));
        abort_if(! is_array($cached) || (int) $cached['tenant_id'] !== $tenantId, 404);

        return $cached;
    }

    private function key(string $orderId): string
    {
        return 'pos_qris:'.$orderId;
    }

    private function qrSvg(string $content): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(280, 1), new SvgImageBackEnd)))->writeString($content);

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }
}
