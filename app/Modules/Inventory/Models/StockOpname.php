<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hitung stok: mencocokkan stok di aplikasi dengan stok sebenarnya di rak. */
class StockOpname extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'number', 'status', 'note', 'user_id', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
