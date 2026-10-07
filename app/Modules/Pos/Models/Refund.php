<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pengembalian barang / uang ke pembeli. */
class Refund extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sale_id', 'uuid', 'number', 'amount', 'reason', 'restock', 'payment_method_id', 'method_type',
        'shift_id', 'user_id', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'restock' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
