<?php

namespace App\Modules\Inventory\Services;

use App\Core\Support\NumberSequence;
use App\Core\Support\Qty;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dokumen stok: stok masuk/keluar, hitung stok (opname), dan kirim stok antar outlet.
 * Semua perubahan jumlah stok tetap lewat StockService.
 */
class StockDocumentService
{
    public function __construct(
        private StockService $stock,
        private NumberSequence $numbers,
    ) {}

    /**
     * @param  list<array{product_id: int, qty: string|float, unit_cost?: int|null}>  $items
     */
    public function adjust(int $outletId, string $type, string $reason, array $items, ?string $note = null): StockAdjustment
    {
        return DB::transaction(function () use ($outletId, $type, $reason, $items, $note) {
            $adjustment = StockAdjustment::create([
                'outlet_id' => $outletId,
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next($type === 'in' ? 'SM' : 'SK'),
                'type' => $type,
                'reason' => $reason,
                'note' => $note,
                'user_id' => auth()->id(),
            ]);

            $products = Product::whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $qty = Qty::normalize($item['qty']);
                $unitCost = $type === 'in' && isset($item['unit_cost']) ? (int) $item['unit_cost'] : null;

                $adjustment->items()->create(['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => $unitCost]);

                $this->stock->change(
                    $outletId,
                    $product,
                    $type === 'in' ? $qty : Qty::negate($qty),
                    $type === 'in' ? 'adjust_in' : 'adjust_out',
                    $adjustment,
                    $unitCost,
                    StockAdjustment::REASONS[$type][$reason] ?? null,
                );
            }

            return $adjustment;
        });
    }

    /**
     * Hitung stok: stok di aplikasi disamakan dengan hasil hitung di rak.
     *
     * @param  list<array{product_id: int, counted_qty: string|float}>  $items
     */
    public function opname(int $outletId, array $items, ?string $note = null): StockOpname
    {
        return DB::transaction(function () use ($outletId, $items, $note) {
            $opname = StockOpname::create([
                'outlet_id' => $outletId,
                'number' => $this->numbers->next('HS'),
                'status' => 'completed',
                'note' => $note,
                'user_id' => auth()->id(),
                'completed_at' => now(),
            ]);

            $products = Product::whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $systemQty = $this->stock->qty($outletId, $product);
                $counted = Qty::normalize($item['counted_qty']);

                $opname->items()->create([
                    'product_id' => $product->id,
                    'system_qty' => $systemQty,
                    'counted_qty' => $counted,
                    'difference' => Qty::sub($counted, $systemQty),
                    'unit_cost' => $this->stock->avgCost($outletId, $product), // untuk nilai rupiah selisih
                ]);

                $this->stock->setTo($outletId, $product, $counted, $opname, "Hitung stok {$opname->number}");
            }

            return $opname;
        });
    }

    /**
     * Kirim stok: stok outlet asal langsung berkurang.
     *
     * @param  list<array{product_id: int, qty: string|float}>  $items
     */
    public function sendTransfer(int $fromOutletId, int $toOutletId, array $items, ?string $note = null): StockTransfer
    {
        if ($fromOutletId === $toOutletId) {
            throw ValidationException::withMessages(['to_outlet_id' => 'Outlet tujuan harus berbeda dengan outlet asal.']);
        }

        return DB::transaction(function () use ($fromOutletId, $toOutletId, $items, $note) {
            $transfer = StockTransfer::create([
                'from_outlet_id' => $fromOutletId,
                'to_outlet_id' => $toOutletId,
                'number' => $this->numbers->next('KS'),
                'status' => 'sent',
                'note' => $note,
                'user_id' => auth()->id(),
                'sent_at' => now(),
            ]);

            $products = Product::whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $qty = Qty::normalize($item['qty']);
                // Modal di gudang asal ikut dibawa, supaya modal rata-rata di tujuan tetap benar.
                $transfer->items()->create(['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => $this->stock->avgCost($fromOutletId, $product)]);
                $this->stock->change($fromOutletId, $product, Qty::negate($qty), 'transfer_out', $transfer, note: "Kirim {$transfer->number}");
            }

            return $transfer;
        });
    }

    /** Outlet tujuan menandai kiriman sudah diterima: stok tujuan bertambah. */
    public function receiveTransfer(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureSent($transfer);

            foreach ($transfer->items()->with('product')->get() as $item) {
                $this->stock->change($transfer->to_outlet_id, $item->product, $item->qty, 'transfer_in', $transfer, $item->unit_cost, "Terima {$transfer->number}", batch: $this->batchSources($transfer, $item->product_id));
            }

            $transfer->update(['status' => 'received', 'received_by' => auth()->id(), 'received_at' => now()]);

            return $transfer;
        });
    }

    /** Batal kirim: stok dikembalikan ke outlet asal. */
    public function cancelTransfer(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->ensureSent($transfer);

            foreach ($transfer->items()->with('product')->get() as $item) {
                $this->stock->change($transfer->from_outlet_id, $item->product, $item->qty, 'transfer_in', $transfer, note: "Batal kirim {$transfer->number}", batch: $this->batchSources($transfer, $item->product_id));
            }

            $transfer->update(['status' => 'canceled']);

            return $transfer;
        });
    }

    /** Batch yang ikut terkirim (modul batch_lot), supaya nomor batch & kedaluwarsa ikut pindah. */
    private function batchSources(StockTransfer $transfer, int $productId): array
    {
        $out = StockMovement::query()
            ->where('reference_type', $transfer->getMorphClass())->where('reference_id', $transfer->id)
            ->where('type', 'transfer_out')->where('product_id', $productId)
            ->first();

        return $out ? ['sources' => app(BatchService::class)->sourcesOf($out)] : [];
    }

    private function ensureSent(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'sent') {
            throw ValidationException::withMessages([
                'transfer' => $transfer->status === 'received'
                    ? 'Kiriman ini sudah diterima sebelumnya.'
                    : 'Kiriman ini sudah dibatalkan.',
            ]);
        }
    }
}
