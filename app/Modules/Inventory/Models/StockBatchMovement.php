<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Batch mana yang bertambah/berkurang oleh satu catatan stok. */
class StockBatchMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_batch_id', 'stock_movement_id', 'qty_change'];

    protected function casts(): array
    {
        return ['qty_change' => 'decimal:3'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }
}
