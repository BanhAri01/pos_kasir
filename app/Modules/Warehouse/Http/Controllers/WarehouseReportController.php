<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Report\Services\ReportService;
use App\Modules\Warehouse\Services\WarehouseReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Laporan gudang: susut, selisih timbang pemasok, dan selisih hitung stok. */
class WarehouseReportController extends Controller
{
    public function index(Request $request, CurrentOutlet $outlet, TenantContext $context, WarehouseReportService $reports): Response
    {
        $tenant = $context->get();
        $tabs = array_keys(array_filter([
            'susut' => $tenant->hasModule('shrinkage'),
            'timbang' => $tenant->hasModule('weighed_receiving'),
            'hitung' => $tenant->hasModule('shrinkage'),
        ]));
        abort_if($tabs === [], 404);

        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : $tabs[0];
        $presets = array_intersect_key(ReportService::presets($tenant->timezone), array_flip(['7days', 'month', 'last_month']));
        $period = array_key_exists($request->query('period'), $presets) ? $request->query('period') : 'month';
        ['from' => $fromDate, 'to' => $toDate] = $presets[$period];

        $outletId = $outlet->id($request->user());
        $from = Carbon::parse($fromDate, $tenant->timezone)->startOfDay()->utc();
        $to = Carbon::parse($toDate, $tenant->timezone)->endOfDay()->utc();

        return Inertia::render('Warehouse/Reports', [
            'tabs' => $tabs,
            'tab' => $tab,
            'period' => $period,
            'periods' => collect($presets)->map(fn ($p, $key) => ['value' => $key, 'label' => $p['label']])->values(),
            'shrinkage' => $tab === 'susut' ? $reports->shrinkage($outletId, $from, $to) : null,
            'weighing' => $tab === 'timbang' ? $reports->weighing($outletId, $fromDate, $toDate) : null,
            'opnames' => $tab === 'hitung' ? $reports->opnames($outletId, $from, $to, $tenant->timezone) : null,
        ]);
    }
}
