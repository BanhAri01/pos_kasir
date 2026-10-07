<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Pos\Http\Requests\StoreSaleRequest;
use App\Modules\Pos\Http\Resources\SaleResource;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\SaleAdjustmentService;
use App\Modules\Pos\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosSaleController extends Controller
{
    public function store(StoreSaleRequest $request, SaleService $sales, DeviceService $devices): JsonResponse
    {
        $device = $devices->fromRequest($request);

        $sale = $sales->record(
            $request->validated(),
            $request->user(),
            $device?->tenant_id === $request->user()->tenant_id ? $device->id : null,
        );

        $sale->load(['items', 'payments', 'cashier:id,name', 'customer', 'outlet:id,name']);

        return response()->json(['data' => SaleResource::make($sale)->resolve()], $sale->wasRecentlyCreated ? 201 : 200);
    }

    /** Transaksi hari ini di outlet aktif (untuk riwayat di layar kasir). */
    public function index(Request $request, CurrentOutlet $outlet, TenantContext $context): JsonResponse
    {
        $timezone = $context->get()->timezone;
        $start = now($timezone)->startOfDay()->utc();

        $sales = Sale::query()
            ->where('outlet_id', $outlet->id($request->user()))
            ->where('completed_at', '>=', $start)
            ->with(['items', 'payments', 'cashier:id,name', 'customer'])
            ->latest('completed_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => SaleResource::collection($sales)->resolve()]);
    }

    public function show(string $uuid): JsonResponse
    {
        $sale = Sale::query()->where('uuid', $uuid)->with(['items', 'payments', 'cashier:id,name', 'customer', 'outlet:id,name'])->firstOrFail();

        return response()->json(['data' => SaleResource::make($sale)->resolve()]);
    }

    public function void(Request $request, string $uuid, SaleAdjustmentService $service): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'approver_id' => ['nullable', 'integer'],
            'approver_pin' => ['nullable', 'string'],
        ], ['reason.required' => 'Tulis alasan pembatalan.']);

        $sale = Sale::query()->where('uuid', $uuid)->firstOrFail();
        $service->void($sale, $data['reason'], $request->user(), $data['approver_id'] ?? null, $data['approver_pin'] ?? null);

        return response()->json(['message' => "Transaksi {$sale->number} sudah dibatalkan. Stok sudah dikembalikan."]);
    }

    public function refund(Request $request, string $uuid, SaleAdjustmentService $service): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'reason' => ['required', 'string', 'max:255'],
            'restock' => ['boolean'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'shift_uuid' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'approver_id' => ['nullable', 'integer'],
            'approver_pin' => ['nullable', 'string'],
        ], [
            'reason.required' => 'Tulis alasan pengembalian.',
            'items.required' => 'Pilih barang yang dikembalikan.',
        ]);

        $sale = Sale::query()->where('uuid', $uuid)->firstOrFail();
        $shiftId = ! empty($data['shift_uuid']) ? Shift::query()->where('uuid', $data['shift_uuid'])->value('id') : null;

        $refund = $service->refund(
            $sale, $data['items'], $data['reason'], $data['restock'] ?? true, $data['payment_method_id'] ?? null,
            $request->user(), $shiftId, $data['approver_id'] ?? null, $data['approver_pin'] ?? null, $data['uuid'] ?? null,
        );

        return response()->json([
            'message' => 'Pengembalian dicatat. Kembalikan uang '.Rupiah::format($refund->amount).' ke pembeli.',
            'amount' => $refund->amount,
        ]);
    }
}
