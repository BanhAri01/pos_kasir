<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Payments\MidtransGateway;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Operations\Models\SelfOrder;
use App\Modules\Operations\Services\SelfOrderService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SelfOrderController extends Controller
{
    public function __construct(
        private SelfOrderService $orders,
        private CurrentOutlet $outlet,
        private TenantContext $context,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $timezone = $this->context->get()->timezone;

        return response()->json([
            'data' => $this->orders->pending($this->outlet->id($request->user()))->map(fn (SelfOrder $o) => $this->cashierView($o, $timezone))->values(),
        ]);
    }

    public function accept(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['shift_uuid' => ['nullable', 'uuid']]);
        $order = $this->find($request, $uuid);
        $sale = $this->orders->accept($order, $request->user(), $data['shift_uuid'] ?? null);

        return response()->json([
            'order' => $this->cashierView($order->fresh('diningTable'), $this->context->get()->timezone),
            'sale_uuid' => $sale?->uuid,
            'message' => $sale
                ? "Pesanan {$order->code} sudah lunas lewat QRIS dan dikirim ke dapur."
                : "Pesanan {$order->code} masuk ke meja {$order->diningTable?->name}.",
        ]);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Tulis alasan singkat, contoh: menu habis.']);
        $order = $this->orders->reject($this->find($request, $uuid), $request->user(), $data['reason']);

        return response()->json([
            'message' => $order->payment_status === 'paid'
                ? "Pesanan {$order->code} ditolak. Pelanggan sudah membayar, kembalikan uangnya secara langsung."
                : "Pesanan {$order->code} ditolak.",
        ]);
    }

    public function settings(Request $request): Response
    {
        $tenant = $this->context->get();
        $tables = $this->tablesWithToken($request);

        return Inertia::render('Operations/SelfOrder', [
            'tables' => $tables->map(fn (DiningTable $t) => ['id' => $t->id, 'name' => $t->name, 'area' => $t->area, 'url' => route('self-order.show', $t->qr_token)])->values(),
            'payment' => [
                'mode' => $tenant->qr_payment,
                'has_keys' => filled($tenant->midtrans_server_key),
                'production' => (bool) $tenant->midtrans_production,
                'webhook_url' => route('self-order.webhook', $tenant->uuid),
            ],
            'fee' => config('hermes.payment.fees.qris'),
        ]);
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $tenant = $this->context->get();
        $data = $request->validate([
            'mode' => ['required', Rule::in(['cashier', 'online'])],
            'server_key' => ['nullable', 'string', 'max:200', 'regex:/^(SB-)?Mid-server-[A-Za-z0-9_-]+$/'],
            'production' => ['boolean'],
        ], ['server_key.regex' => 'Server key Midtrans biasanya diawali "Mid-server-" (atau "SB-Mid-server-" untuk uji coba).']);

        $serverKey = $data['server_key'] ?? null;
        if ($data['mode'] === 'online' && blank($serverKey) && blank($tenant->midtrans_server_key)) {
            return back()->withErrors(['server_key' => 'Isi server key Midtrans dulu supaya pembeli bisa bayar QRIS.']);
        }

        $production = (bool) ($data['production'] ?? false);
        if (filled($serverKey) && str_starts_with($serverKey, 'SB-') && $production) {
            return back()->withErrors(['server_key' => 'Key ini key uji coba (SB-). Matikan pilihan "Uang sungguhan" atau pakai key produksi.']);
        }

        if (filled($serverKey) && (new MidtransGateway($serverKey, $production))->keyIsValid() === false) {
            return back()->withErrors(['server_key' => 'Server key ditolak Midtrans. Periksa lagi key dan pilihan uang sungguhan / uji coba.']);
        }

        $tenant->forceFill(array_filter([
            'qr_payment' => $data['mode'],
            'midtrans_server_key' => $serverKey,
            'midtrans_production' => $production,
        ], fn ($v) => $v !== null))->save();

        return back()->with('success', $data['mode'] === 'online' ? 'Bayar QRIS dari meja sudah aktif.' : 'Pembeli membayar di kasir.');
    }

    public function printQr(Request $request): View
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd));
        $tenant = $this->context->get();

        return view('documents.table-qr', [
            'tenant' => $tenant,
            'cards' => $this->tablesWithToken($request)
                ->when($request->filled('meja'), fn ($c) => $c->where('id', (int) $request->query('meja')))
                ->map(fn (DiningTable $t) => [
                    'name' => $t->name,
                    'area' => $t->area,
                    'svg' => preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString(route('self-order.show', $t->qr_token))),
                ])->values(),
        ]);
    }

    public function regenerate(Request $request, DiningTable $table): RedirectResponse
    {
        $table->forceFill(['qr_token' => DiningTable::newToken()])->save();

        return back()->with('success', "QR {$table->name} sudah diganti. Cetak ulang QR meja ini, QR lama tidak bisa dipakai lagi.");
    }

    private function tablesWithToken(Request $request)
    {
        $tables = DiningTable::query()->where('outlet_id', $this->outlet->id($request->user()))->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        foreach ($tables->whereNull('qr_token') as $table) {
            $table->forceFill(['qr_token' => DiningTable::newToken()])->save();
        }

        return $tables;
    }

    private function cashierView(SelfOrder $order, string $timezone): array
    {
        return [
            ...$order->forCashier($timezone),
            'delivery_product_id' => $order->delivery_fee > 0 ? $this->orders->deliveryProduct()->id : null,
        ];
    }

    private function find(Request $request, string $uuid): SelfOrder
    {
        return SelfOrder::query()->with('diningTable:id,name')->where('outlet_id', $this->outlet->id($request->user()))->where('uuid', $uuid)->firstOrFail();
    }
}
