<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Modules\Operations\Models\SelfOrder;
use App\Modules\Operations\Services\SelfOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicOrderController extends Controller
{
    public function __construct(private SelfOrderService $orders, private TenantContext $context) {}

    public function show(string $token): Response
    {
        [$table, $tenant] = $this->resolveTable($token);

        return $this->context->runAs($tenant, function () use ($table, $tenant) {
            $outlet = Outlet::query()->findOrFail($table->outlet_id);
            $open = $this->orders->isOpen($tenant);

            return Inertia::render('Order/Menu', [
                'token' => $table->qr_token,
                'business' => ['name' => $tenant->name, 'outlet' => $outlet->name, 'logo_url' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null],
                'table' => $table->name,
                'open' => $open,
                'menu' => $open ? $this->orders->menu($outlet) : ['categories' => [], 'products' => []],
                'charges' => [
                    'service_charge_bp' => $outlet->service_charge_bp,
                    'tax_bp' => $outlet->tax_rate_bp,
                    'tax_inclusive' => (bool) $outlet->tax_inclusive,
                ],
                'online' => $open && $this->orders->onlinePaymentAvailable($tenant),
                'qrisFee' => config('hermes.payment.fees.qris'),
                'feeVatBp' => (int) config('hermes.payment.fee_vat_bp'),
            ]);
        });
    }

    public function store(Request $request, string $token): HttpResponse
    {
        [$table, $tenant] = $this->resolveTable($token);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
            'pay_method' => ['required', Rule::in(['cashier', 'online'])],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:20'],
            'items.*.modifier_ids.*' => ['integer'],
            'items.*.note' => ['nullable', 'string', 'max:150'],
        ], [
            'customer_name.required' => 'Tulis nama Anda supaya pelayan tahu pesanan ini milik siapa.',
            'items.required' => 'Pilih minimal satu menu.',
        ]);

        $order = $this->context->runAs($tenant, function () use ($table, $tenant, $data, $request) {
            abort_unless($this->orders->isOpen($tenant), 404);

            return $this->orders->place($table, $data, $request->ip());
        });

        if ($order->pay_method === 'online' && $order->payment_url) {
            return Inertia::location($order->payment_url);
        }

        return redirect()->route('self-order.status', $order->uuid);
    }

    public function status(string $uuid): Response
    {
        [$order, $tenant] = $this->resolveOrder($uuid);

        return $this->context->runAs($tenant, function () use ($order, $tenant) {
            $order = $this->orders->refreshPayment($tenant, $order)->load('diningTable');

            return Inertia::render('Order/Status', [
                'order' => $order->forCustomer(),
                'business' => $tenant->name,
                'menuUrl' => $order->diningTable?->qr_token ? route('self-order.show', $order->diningTable->qr_token) : null,
            ]);
        });
    }

    public function poll(string $uuid): JsonResponse
    {
        [$order, $tenant] = $this->resolveOrder($uuid);

        return $this->context->runAs($tenant, fn () => response()->json(
            $this->orders->refreshPayment($tenant, $order)->load('diningTable')->forCustomer()
        ));
    }

    public function webhook(Request $request, string $tenantUuid): JsonResponse
    {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();
        if (! $tenant) {
            return response()->json(['message' => 'unknown'], 404);
        }

        return $this->context->runAs($tenant, function () use ($tenant, $request) {
            $result = $this->orders->gatewayFor($tenant)?->parseNotification($request->all());
            if (! $result) {
                return response()->json(['message' => 'invalid'], 403);
            }

            $this->orders->applyPayment($result);

            return response()->json(['message' => 'ok']);
        });
    }

    private function resolveTable(string $token): array
    {
        $table = SelfOrderService::tableByToken($token);
        abort_unless($table, 404);
        $tenant = Tenant::query()->findOrFail($table->tenant_id);
        abort_if($tenant->isSuspended(), 404);

        return [$table, $tenant];
    }

    private function resolveOrder(string $uuid): array
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $uuid) === 1, 404);
        $order = SelfOrder::query()->withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
        $tenant = Tenant::query()->findOrFail($order->tenant_id);

        return [$this->context->runAs($tenant, fn () => SelfOrder::query()->findOrFail($order->id)), $tenant];
    }
}
