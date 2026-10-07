<?php

namespace App\Modules\Purchasing\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Belanja barang dari pemasok (menambah stok, bisa utang). */
class Purchase extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'uuid', 'number', 'supplier_id', 'supplier_invoice_no', 'purchased_on', 'total', 'freight_cost', 'unloading_cost', 'other_cost', 'paid_amount', 'due_date', 'payment_status', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'due_date' => 'date',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'freight_cost' => 'integer',
            'unloading_cost' => 'integer',
            'other_cost' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
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
