<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Modules\Pos\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pengiriman / antar-jemput dengan surat jalan. */
class Delivery extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'sale_id', 'uuid', 'number', 'type', 'recipient_name', 'phone', 'address', 'scheduled_at', 'driver_name', 'vehicle', 'fee', 'status', 'delivered_at', 'note'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'fee' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
