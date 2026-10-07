<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Batch / lot barang di satu gudang (modul batch_lot). qty = sisa.
 * Hanya diubah lewat BatchService (dipanggil dari StockService).
 */
class StockBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'outlet_id', 'product_id', 'batch_no', 'received_on', 'expires_at', 'moisture', 'quality_note',
        'initial_qty', 'qty', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'expires_at' => 'date',
            'moisture' => 'decimal:2',
            'initial_qty' => 'decimal:3',
            'qty' => 'decimal:3',
            'unit_cost' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** Masih ada sisa. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('qty', '>', 0);
    }

    /**
     * Urutan keluar: kedaluwarsa paling dekat dulu (FEFO), yang tanpa tanggal kedaluwarsa paling akhir,
     * lalu yang paling lama masuk (FIFO).
     */
    public function scopeIssueOrder(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('received_on')
            ->orderBy('id');
    }
}
