<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kirim stok antar outlet. Stok outlet asal berkurang saat dikirim,
 * stok outlet tujuan bertambah saat ditandai "Sudah diterima".
 */
class StockTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'from_outlet_id', 'to_outlet_id', 'number', 'status', 'note', 'user_id', 'received_by', 'sent_at', 'received_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id');
    }

    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
