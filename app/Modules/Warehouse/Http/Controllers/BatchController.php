<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Daftar batch yang masih ada di gudang aktif, urut sesuai urutan keluar (FEFO). */
class BatchController extends Controller
{
    public function index(Request $request, CurrentOutlet $outlet, TenantContext $context): Response
    {
        $today = Carbon::now($context->get()->timezone)->startOfDay();

        $batches = StockBatch::query()
            ->where('outlet_id', $outlet->id($request->user()))
            ->available()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q
                ->where('batch_no', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$request->string('q').'%'))))
            ->with('product.unit:id,name')
            ->issueOrder()
            ->limit(200)
            ->get();

        return Inertia::render('Warehouse/Batches', [
            'groups' => $batches->groupBy('product_id')->map(fn ($rows) => [
                'product' => $rows->first()->product?->name,
                'unit' => $rows->first()->product?->unit?->name ?? '',
                'total' => Qty::display($rows->reduce(fn ($sum, $b) => Qty::add($sum, $b->qty), '0')),
                'batches' => $rows->map(fn (StockBatch $b) => [
                    'id' => $b->id,
                    'batch_no' => $b->batch_no,
                    'qty' => Qty::display($b->qty),
                    'received_on' => $b->received_on->locale('id')->translatedFormat('j M Y'),
                    'expires_at' => $b->expires_at?->locale('id')->translatedFormat('j M Y'),
                    'days_left' => $b->expires_at ? (int) $today->diffInDays($b->expires_at, false) : null,
                    'moisture' => $b->moisture !== null ? rtrim(rtrim(str_replace('.', ',', (string) $b->moisture), '0'), ',') : null,
                    'quality_note' => $b->quality_note,
                ])->values()->all(),
            ])->values(),
            'q' => $request->string('q')->toString(),
            'warningDays' => WarehouseDashboardController::EXPIRY_WARNING_DAYS,
        ]);
    }
}
