<?php

namespace App\Modules\EggCollector\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\EggCollector\Services\EggSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ringkasan uang pengepul: hari ini, minggu ini, bulan ini, untung per jenis telur, piutang pembeli, utang ke peternak. */
class EggSummaryController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private CurrentOutlet $outlet,
        private EggSummaryService $summary,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $seeAll = $user->can(Permission::ViewAllOutlets->value);
        $outlets = Outlet::query()->where('is_active', true)->orderBy('id')
            ->when(! $seeAll, fn ($q) => $q->whereIn('id', $user->outlets()->pluck('outlets.id')))
            ->get(['id', 'name']);

        $choice = $request->query('outlet');
        $outletIds = match (true) {
            is_numeric($choice) && $outlets->contains('id', (int) $choice) => [(int) $choice],
            $seeAll => [],
            default => [$this->outlet->id($user)],
        };

        return Inertia::render('Pengepul/Summary', [
            ...$this->summary->overview($this->context->get()->timezone, $outletIds),
            'outlets' => $outlets->values(),
            'outlet' => $outletIds ? (string) $outletIds[0] : 'semua',
            'can_see_all' => $seeAll,
        ]);
    }
}
