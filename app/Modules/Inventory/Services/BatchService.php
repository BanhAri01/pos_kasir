<?php

namespace App\Modules\Inventory\Services;

use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockBatchMovement;
use App\Modules\Inventory\Models\StockMovement;

/**
 * Batch / lot (modul batch_lot). Dipanggil oleh StockService setiap stok berubah, untuk barang yang
 * dinyalakan "Catat nomor batch".
 *
 *  - Stok masuk dengan info batch -> batch baru (nomor, tanggal masuk, kedaluwarsa, kadar air).
 *  - Stok masuk tanpa info batch (retur, hitung stok, dll) -> masuk ke batch "tanpa nomor" hari itu.
 *  - Stok keluar -> diambil dari batch yang kedaluwarsa paling dekat (FEFO), lalu yang paling lama (FIFO).
 *    Kalau batch kurang (stok minus), sisanya tidak dicatat per batch.
 */
class BatchService
{
    public function __construct(private TenantContext $context) {}

    public function enabledFor(Product $product): bool
    {
        return $product->track_batch && (bool) $this->context->get()?->hasModule('batch_lot');
    }

    /**
     * @param  array{batch_no?:string|null, expires_at?:string|null, moisture?:string|float|null, quality_note?:string|null,
     *               sources?: list<array{batch_no:string, expires_at:?string, moisture:?string, quality_note:?string, received_on:string, qty:string}>}  $info
     */
    public function apply(StockMovement $movement, Product $product, array $info = []): void
    {
        $change = Qty::normalize($movement->qty_change);

        if (Qty::isNegative($change)) {
            $this->issue($movement, $product, Qty::negate($change));

            return;
        }

        $remaining = $change;

        // Kiriman antar gudang: batch asal ikut pindah (nomor & kedaluwarsa sama).
        foreach ($info['sources'] ?? [] as $source) {
            $qty = Qty::cmp($source['qty'], $remaining) > 0 ? $remaining : Qty::normalize($source['qty']);
            if (Qty::cmp($qty, '0') <= 0) {
                break;
            }
            $this->receive($movement, $product, $qty, $source);
            $remaining = Qty::sub($remaining, $qty);
        }

        if (Qty::cmp($remaining, '0') > 0) {
            $this->receive($movement, $product, $remaining, $info);
        }
    }

    private function receive(StockMovement $movement, Product $product, string $qty, array $info): void
    {
        $today = now($this->context->get()?->timezone ?? 'Asia/Jakarta')->toDateString();
        $batchNo = trim((string) ($info['batch_no'] ?? ''));

        // Tanpa nomor batch: digabung ke batch "tanpa nomor" hari ini supaya tidak muncul banyak batch kecil.
        if ($batchNo === '') {
            $batch = StockBatch::query()
                ->where('outlet_id', $movement->outlet_id)->where('product_id', $product->id)
                ->where('batch_no', $this->autoNumber($today))
                ->lockForUpdate()->first();

            if ($batch) {
                $batch->update(['qty' => Qty::add($batch->qty, $qty), 'initial_qty' => Qty::add($batch->initial_qty, $qty)]);
                $this->log($batch, $movement, $qty);

                return;
            }

            $batchNo = $this->autoNumber($today);
        }

        $batch = StockBatch::create([
            'outlet_id' => $movement->outlet_id,
            'product_id' => $product->id,
            'batch_no' => $batchNo,
            'received_on' => $info['received_on'] ?? $today,
            'expires_at' => ($info['expires_at'] ?? null) ?: null,
            'moisture' => isset($info['moisture']) && $info['moisture'] !== '' ? $info['moisture'] : null,
            'quality_note' => ($info['quality_note'] ?? null) ?: null,
            'initial_qty' => $qty,
            'qty' => $qty,
            'unit_cost' => (int) $movement->unit_cost,
        ]);

        $this->log($batch, $movement, $qty);
    }

    private function issue(StockMovement $movement, Product $product, string $qty): void
    {
        $batches = StockBatch::query()
            ->where('outlet_id', $movement->outlet_id)->where('product_id', $product->id)
            ->available()->issueOrder()->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if (Qty::cmp($qty, '0') <= 0) {
                break;
            }

            $take = Qty::cmp($batch->qty, $qty) >= 0 ? $qty : Qty::normalize($batch->qty);
            $batch->update(['qty' => Qty::sub($batch->qty, $take)]);
            $this->log($batch, $movement, Qty::negate($take));
            $qty = Qty::sub($qty, $take);
        }
    }

    /**
     * Batch yang terpakai oleh satu catatan stok keluar, untuk dibawa ke gudang tujuan.
     *
     * @return list<array{batch_no:string, expires_at:?string, moisture:?string, quality_note:?string, received_on:string, qty:string}>
     */
    public function sourcesOf(StockMovement $movement): array
    {
        return StockBatchMovement::query()
            ->where('stock_movement_id', $movement->id)->where('qty_change', '<', 0)
            ->with('batch')->get()
            ->map(fn (StockBatchMovement $m) => [
                'batch_no' => $m->batch->batch_no,
                'expires_at' => $m->batch->expires_at?->toDateString(),
                'moisture' => $m->batch->moisture,
                'quality_note' => $m->batch->quality_note,
                'received_on' => $m->batch->received_on->toDateString(),
                'qty' => Qty::negate($m->qty_change),
            ])->all();
    }

    private function log(StockBatch $batch, StockMovement $movement, string $qty): void
    {
        StockBatchMovement::create(['stock_batch_id' => $batch->id, 'stock_movement_id' => $movement->id, 'qty_change' => $qty]);
    }

    private function autoNumber(string $date): string
    {
        return 'TANPA-'.str_replace('-', '', $date);
    }
}
