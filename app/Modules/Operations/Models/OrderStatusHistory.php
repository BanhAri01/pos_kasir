<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat perpindahan status pesanan. */
class OrderStatusHistory extends Model
{
    use BelongsToTenant;

    protected $fillable = ['sale_id', 'from_status_id', 'to_status_id', 'user_id'];

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'to_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
