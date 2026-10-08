<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'outlet_id', 'table_id', 'uuid', 'code', 'customer_name', 'note', 'items', 'subtotal', 'service_charge_amount',
        'tax_amount', 'total', 'fee_amount', 'pay_method', 'payment_status', 'payment_reference', 'payment_url',
        'payment_channel', 'payment_payload', 'paid_at', 'status', 'handled_by', 'handled_at', 'reject_reason', 'ip_address',
    ];

    protected $hidden = ['payment_payload', 'ip_address'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'payment_payload' => 'array',
            'subtotal' => 'integer',
            'service_charge_amount' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'fee_amount' => 'integer',
            'paid_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function isPaidOnline(): bool
    {
        return $this->pay_method === 'online' && $this->payment_status === 'paid';
    }

    public function visibleToCashier(): bool
    {
        return $this->status === 'new' && ($this->pay_method === 'cashier' || $this->payment_status === 'paid');
    }

    public function forCashier(string $timezone): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'customer_name' => $this->customer_name,
            'note' => $this->note,
            'table_id' => $this->table_id,
            'table' => $this->diningTable?->name,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'paid' => $this->isPaidOnline(),
            'time' => $this->created_at?->timezone($timezone)->format('H:i'),
        ];
    }

    public function forCustomer(): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'customer_name' => $this->customer_name,
            'table' => $this->diningTable?->name,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'service_charge_amount' => $this->service_charge_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'fee_amount' => $this->fee_amount,
            'pay_method' => $this->pay_method,
            'payment_status' => $this->payment_status,
            'payment_url' => $this->payment_status === 'pending' ? $this->payment_url : null,
            'status' => $this->status,
            'reject_reason' => $this->reject_reason,
        ];
    }
}
