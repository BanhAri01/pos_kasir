<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Operations\Models\Booking;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Report\Services\ReportFilter;
use App\Modules\Report\Services\ReportService;
use App\Modules\Sync\Models\SyncConflict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda: sapaan, tombol Mulai Jualan, ringkasan hari ini (untuk pemilik/manajer),
 * dan hal yang perlu perhatian (stok menipis, kasbon, utang ke pemasok, janji hari ini).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, CurrentOutlet $outlet, ReportService $reports): Response
    {
        $tenant = $context->get();
        $user = $request->user();
        $timezone = $tenant->timezone;
        $hour = (int) now($timezone)->format('G');
        $outletId = $outlet->id($user);
        $canReports = $user->can(Permission::ViewReports->value);

        $greeting = match (true) {
            $hour >= 4 && $hour < 11 => 'Selamat pagi',
            $hour >= 11 && $hour < 15 => 'Selamat siang',
            $hour >= 15 && $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        $today = now($timezone)->toDateString();
        $todayFilter = ReportFilter::make($today, $today, $timezone, [$outletId]);

        return Inertia::render('Dashboard', [
            'greeting' => $greeting,
            'today' => now($timezone)->locale('id')->translatedFormat('l, j F Y'),
            // Catatan dari kasir offline yang perlu dicek pemilik/manajer.
            'conflictCount' => $canReports ? SyncConflict::query()->unresolved()->count() : 0,
            'stats' => $canReports ? Inertia::defer(fn () => [
                'summary' => $reports->summaryWithComparison($todayFilter),
                'week' => $reports->series(ReportFilter::make(now($timezone)->subDays(6)->toDateString(), $today, $timezone, [$outletId])),
                'topProducts' => $reports->products($todayFilter, 5, 'qty'),
            ]) : null,
            'mine' => ! $canReports && $user->can(Permission::UsePos->value) ? $this->mine($user->id, $todayFilter) : null,
            'alerts' => Inertia::defer(fn () => $this->alerts($request, $outletId, $timezone)),
        ]);
    }

    /** Untuk kasir: penjualan dirinya hari ini. */
    private function mine(int $userId, ReportFilter $f): array
    {
        $row = Sale::query()->completed()->where('cashier_id', $userId)->whereBetween('completed_at', $f->utcRange())->selectRaw('COUNT(*) as trx, COALESCE(SUM(total), 0) as total')->first();

        return ['transactions' => (int) $row->trx, 'total' => (int) $row->total];
    }

    private function alerts(Request $request, int $outletId, string $timezone): array
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->get();
        $alerts = [];

        if ($user->can(Permission::ManageStock->value) || $user->can(Permission::ManageProducts->value)) {
            $low = DB::table('stocks')
                ->join('products', 'products.id', '=', 'stocks.product_id')
                ->where('stocks.tenant_id', $tenant->id)
                ->where('stocks.outlet_id', $outletId)
                ->whereNull('products.deleted_at')
                ->where('products.is_active', true)
                ->where('products.track_stock', true)
                ->whereNotNull('products.min_stock')
                ->whereColumn('stocks.qty', '<=', 'products.min_stock')
                ->count();
            if ($low) {
                $alerts[] = ['key' => 'stock', 'tone' => 'warn', 'text' => "{$low} barang stoknya menipis", 'href' => route('stock.index')];
            }
        }

        if ($tenant->hasModule('kasbon') && $user->can(Permission::ManageCustomers->value)) {
            $kasbon = (int) Receivable::query()->where('status', 'open')->sum(DB::raw('amount - paid_amount'));
            if ($kasbon > 0) {
                $alerts[] = ['key' => 'kasbon', 'tone' => 'info', 'text' => 'Kasbon pelanggan belum lunas: '.\App\Core\Support\Rupiah::format($kasbon), 'href' => route('receivables.index')];
            }
        }

        if ($tenant->hasModule('supplier_purchase') && $user->can(Permission::ManageStock->value)) {
            $due = Purchase::query()->where('payment_status', '!=', 'paid')->whereNotNull('due_date')->where('due_date', '<=', now($timezone)->addDays(3)->toDateString())->count();
            if ($due) {
                $alerts[] = ['key' => 'purchase', 'tone' => 'danger', 'text' => "{$due} utang ke pemasok jatuh tempo dalam 3 hari", 'href' => route('purchases.index', ['status' => 'utang'])];
            }
        }

        if ($tenant->hasModule('booking') && $user->can(Permission::UsePos->value)) {
            $start = now($timezone)->startOfDay()->utc();
            $count = Booking::query()->where('outlet_id', $outletId)->where('status', 'booked')->whereBetween('start_at', [$start, $start->copy()->addDay()])->count();
            if ($count) {
                $alerts[] = ['key' => 'booking', 'tone' => 'info', 'text' => "{$count} janji temu hari ini", 'href' => route('bookings.index')];
            }
        }

        return $alerts;
    }
}
