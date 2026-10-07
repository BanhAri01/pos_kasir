<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Pesanan yang tampil di layar dapur / barista. */
class KitchenTicket extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'uuid', 'label', 'order_type', 'items', 'note', 'status', 'sale_uuid', 'created_by', 'ready_at', 'served_at'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }
}
