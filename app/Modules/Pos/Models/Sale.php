<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/** Satu transaksi penjualan (nota). */
class Sale extends Model
{
    use BelongsToTenant;

    public const ORDER_TYPES = [
        'walk_in' => 'Langsung',
        'dine_in' => 'Makan di tempat',
        'take_away' => 'Bungkus',
        'delivery' => 'Diantar',
    ];

    protected $fillable = [
        'outlet_id', 'uuid', 'number', 'device_id', 'shift_id', 'cashier_id', 'customer_id', 'table_id',
        'split_from_id', 'order_type', 'status', 'payment_status', 'subtotal', 'discount_type',
        'discount_value', 'discount_amount', 'service_charge_amount', 'tax_amount', 'rounding_amount',
        'total', 'paid_amount', 'change_amount', 'due_amount', 'refunded_amount', 'due_date', 'note',
        'queue_number', 'order_status_id', 'estimated_ready_at', 'picked_up_at', 'device_created_at',
        'synced_at', 'completed_at', 'voided_at', 'void_reason', 'voided_by', 'authorized_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'service_charge_amount' => 'integer',
            'tax_amount' => 'integer',
            'rounding_amount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'change_amount' => 'integer',
            'due_amount' => 'integer',
            'refunded_amount' => 'integer',
            'due_date' => 'date',
            'estimated_ready_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'device_created_at' => 'datetime',
            'synced_at' => 'datetime',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Pengiriman / surat jalan (modul delivery). */
    public function deliveries(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\Delivery::class);
    }

    /** Penjualan yang dihitung di laporan (bukan void, bukan pesanan terbuka). */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /** Link struk digital (bisa dibuka pelanggan tanpa login). */
    public function receiptUrl(): string
    {
        return URL::signedRoute('receipt.show', ['uuid' => $this->uuid]);
    }
}
