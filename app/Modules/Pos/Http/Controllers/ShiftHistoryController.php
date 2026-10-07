<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\ShiftService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Riwayat buka/tutup kasir: siapa, kapan, berapa uangnya, ada selisih atau tidak. */
class ShiftHistoryController extends Controller
{
    public function __invoke(Request $request, CurrentOutlet $outlet, TenantContext $context, ShiftService $service): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);
        $timezone = $context->get()->timezone;
        $format = fn ($dt) => $dt?->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i');

        $shifts = Shift::query()
            ->where('outlet_id', $outlet->id($request->user()))
            ->with(['user:id,name', 'closer:id,name'])
            ->latest('opened_at')
            ->limit(60)
            ->get()
            ->map(fn (Shift $s) => [
                'uuid' => $s->uuid,
                'user' => $s->user?->name,
                'status' => $s->status,
                'opened_at' => $format($s->opened_at),
                'closed_at' => $format($s->closed_at),
                'opening_cash' => $s->opening_cash,
                'expected_cash' => $s->isOpen() ? $service->summary($s)['expected_cash'] : $s->expected_cash,
                'counted_cash' => $s->counted_cash,
                'cash_difference' => $s->cash_difference,
                'closing_note' => $s->closing_note,
                'sales_total' => (int) $s->sales()->where('status', 'completed')->sum('total'),
            ]);

        return Inertia::render('Sales/Shifts', ['shifts' => $shifts]);
    }
}
