<?php

namespace App\Modules\Warehouse\Services;

use App\Core\Support\Qty;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Purchasing\Models\PurchaseItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Laporan gudang untuk satu gudang/outlet dalam satu periode:
 *  - susut: stok keluar karena rusak, hilang, kadar air, hama, tumpah + kekurangan saat hitung stok,
 *  - selisih timbang pemasok: berat nota vs berat timbangan asli,
 *  - selisih hitung stok: hasil hitung di gudang vs catatan aplikasi.
 *
 * Nilai rupiah = jumlah x modal rata-rata saat kejadian.
 */
class WarehouseReportService
{
    /**
     * @param  CarbonInterface  $from  awal periode (sudah dalam UTC)
     * @param  CarbonInterface  $to  akhir periode (sudah dalam UTC)
     */
    public function shrinkage(int $outletId, CarbonInterface $from, CarbonInterface $to): array
    {
        $adjustmentReasons = StockAdjustment::query()
            ->where('outlet_id', $outletId)->where('type', 'out')
            ->whereIn('reason', StockAdjustment::SHRINKAGE_REASONS)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('reason', 'id');

        $movements = StockMovement::query()
            ->where('outlet_id', $outletId)
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) use ($adjustmentReasons) {
                $q->where(fn ($q) => $q->where('type', 'adjust_out')
                    ->where('reference_type', (new StockAdjustment)->getMorphClass())
                    ->whereIn('reference_id', $adjustmentReasons->keys()))
                    ->orWhere(fn ($q) => $q->where('type', 'opname')->where('qty_change', '<', 0));
            })
            ->with('product:id,name,base_unit_id', 'product.unit:id,name')
            ->get();

        $byReason = [];
        $byProduct = [];
        foreach ($movements as $m) {
            $reason = $m->type === 'opname' ? 'opname' : ($adjustmentReasons[$m->reference_id] ?? 'lainnya');
            $qty = Qty::negate($m->qty_change);
            $value = Qty::money((int) $m->unit_cost, $qty);

            $byReason[$reason] = ($byReason[$reason] ?? 0) + $value;

            $key = $m->product_id;
            $byProduct[$key] ??= ['name' => $m->product?->name ?? 'Barang dihapus', 'unit' => $m->product?->unit?->name ?? '', 'qty' => '0', 'value' => 0];
            $byProduct[$key]['qty'] = Qty::add($byProduct[$key]['qty'], $qty);
            $byProduct[$key]['value'] += $value;
        }

        $labels = StockAdjustment::REASONS['out'] + ['opname' => 'Kurang saat hitung stok'];
        arsort($byReason);
        usort($byProduct, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'total_value' => array_sum($byReason),
            'reasons' => collect($byReason)->map(fn ($value, $reason) => ['label' => $labels[$reason] ?? $reason, 'value' => $value])->values()->all(),
            'products' => array_map(fn ($p) => [...$p, 'qty' => Qty::display($p['qty'])], $byProduct),
        ];
    }

    /** Selisih berat nota vs timbangan, dikelompokkan per pemasok. */
    public function weighing(int $outletId, string $fromDate, string $toDate): array
    {
        /** @var Collection<int, PurchaseItem> $items */
        $items = PurchaseItem::query()
            ->whereNotNull('received_qty')
            ->whereHas('purchase', fn ($q) => $q->where('outlet_id', $outletId)->whereDate('purchased_on', '>=', $fromDate)->whereDate('purchased_on', '<=', $toDate))
            ->with('purchase:id,uuid,number,supplier_id,purchased_on', 'purchase.supplier:id,name', 'product:id,name,base_unit_id', 'product.unit:id,name')
            ->get();

        $suppliers = [];
        foreach ($items as $item) {
            $key = $item->purchase->supplier_id ?? 0;
            $billed = Qty::mul($item->qty, $item->conversion_qty);
            // Nilai selisih memakai harga beli per satuan dasar.
            $basePrice = (int) round($item->unit_cost / (float) $item->conversion_qty);

            $suppliers[$key] ??= ['name' => $item->purchase->supplier?->name ?? 'Tanpa pemasok', 'deliveries' => [], 'billed' => '0', 'received' => '0', 'diff' => '0', 'value' => 0];
            $s = &$suppliers[$key];
            $s['deliveries'][$item->purchase_id] = true;
            $s['billed'] = Qty::add($s['billed'], $billed);
            $s['received'] = Qty::add($s['received'], $item->received_qty);
            $s['diff'] = Qty::add($s['diff'], $item->weight_diff);
            $s['value'] += Qty::money($basePrice, $item->weight_diff);
            unset($s);
        }

        usort($suppliers, fn ($a, $b) => $a['value'] <=> $b['value']); // paling rugi di atas

        return [
            'total_value' => array_sum(array_column($suppliers, 'value')),
            'suppliers' => array_map(fn ($s) => [
                'name' => $s['name'],
                'deliveries' => count($s['deliveries']),
                'billed' => Qty::display($s['billed']),
                'received' => Qty::display($s['received']),
                'diff' => Qty::display($s['diff']),
                'short' => Qty::isNegative($s['diff']),
                'value' => $s['value'],
            ], $suppliers),
            'items' => $items->filter(fn ($i) => ! Qty::isZero($i->weight_diff))
                ->sortByDesc(fn ($i) => $i->purchase->purchased_on)
                ->take(30)
                ->map(fn (PurchaseItem $i) => [
                    'purchase_uuid' => $i->purchase->uuid,
                    'number' => $i->purchase->number,
                    'date' => $i->purchase->purchased_on->locale('id')->translatedFormat('j M Y'),
                    'supplier' => $i->purchase->supplier?->name ?? 'Tanpa pemasok',
                    'product' => $i->product?->name,
                    'unit' => $i->product?->unit?->name ?? '',
                    'billed' => Qty::display(Qty::mul($i->qty, $i->conversion_qty)),
                    'received' => Qty::display($i->received_qty),
                    'diff' => Qty::display($i->weight_diff),
                    'short' => Qty::isNegative($i->weight_diff),
                ])->values()->all(),
        ];
    }

    /** Hasil hitung stok dalam periode, hanya barang yang selisih. */
    public function opnames(int $outletId, CarbonInterface $from, CarbonInterface $to, string $timezone): array
    {
        return StockOpname::query()
            ->where('outlet_id', $outletId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['items' => fn ($q) => $q->where('difference', '!=', 0), 'items.product:id,name,base_unit_id', 'items.product.unit:id,name', 'user:id,name'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (StockOpname $o) => [
                'number' => $o->number,
                'date' => $o->created_at->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
                'user' => $o->user?->name,
                'value' => $o->items->sum(fn ($i) => Qty::money((int) $i->unit_cost, $i->difference)),
                'items' => $o->items->map(fn ($i) => [
                    'product' => $i->product?->name,
                    'unit' => $i->product?->unit?->name ?? '',
                    'system' => Qty::display($i->system_qty),
                    'counted' => Qty::display($i->counted_qty),
                    'diff' => Qty::display($i->difference),
                    'short' => Qty::isNegative($i->difference),
                    'value' => Qty::money((int) $i->unit_cost, $i->difference),
                ])->all(),
            ])->all();
    }
}
