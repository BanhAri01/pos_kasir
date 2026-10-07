<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Sync\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Menerima antrean transaksi dari kasir (offline maupun online). */
class SyncController extends Controller
{
    public function __invoke(Request $request, SyncService $sync, DeviceService $devices): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required'],
            'items.*.type' => ['required', Rule::in(SyncService::TYPES)],
            'items.*.payload' => ['required', 'array'],
        ]);

        $device = $devices->fromRequest($request);
        if ($device && $device->tenant_id !== $request->user()->tenant_id) {
            $device = null;
        }

        return response()->json([
            'results' => $sync->process($data['items'], $request->user(), $device),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
