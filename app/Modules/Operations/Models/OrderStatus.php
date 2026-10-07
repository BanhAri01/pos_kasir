<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Tahap pesanan, mis. Diterima > Dicuci > Siap Diambil. */
class OrderStatus extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'color', 'sort_order', 'is_final', 'notify_customer'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_final' => 'boolean',
            'notify_customer' => 'boolean',
        ];
    }
}
