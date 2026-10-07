<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Pos\Services\PosBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Layar kasir: aplikasi Vue tersendiri (resources/js/pos), bukan halaman Inertia,
 * supaya bisa dibuat berjalan offline (Fase 5).
 */
class PosController extends Controller
{
    public function show(Request $request, DeviceService $devices): View
    {
        // Pastikan HP ini tercatat (nomor nota offline memakai kode HP).
        if (! $devices->fromRequest($request)) {
            $devices->remember($request, $request->user());
        }

        $prefs = $request->user()->preferences ?? [];

        return view('pos', [
            'theme' => $prefs['theme'] ?? 'system',
            'size' => $prefs['display_size'] ?? 'normal',
        ]);
    }

    public function bootstrap(Request $request, CurrentOutlet $outlet, DeviceService $devices, PosBootstrapService $service): JsonResponse
    {
        $current = $outlet->get($request->user());

        if (! $current) {
            return response()->json(['message' => 'Anda belum ditugaskan di outlet mana pun. Minta pemilik usaha menambahkan Anda ke outlet.'], 422);
        }

        $device = $devices->fromRequest($request);
        if ($device && $device->tenant_id !== $request->user()->tenant_id) {
            $device = null;
        }

        return response()->json($service->build($request->user(), $current, $device));
    }
}
