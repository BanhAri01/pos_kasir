<?php

namespace App\Modules\Billing\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WithdrawalRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'wallet_id',
        'amount',
        'admin_fee',
        'net_amount',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'status',
        'requested_by',
        'processed_by',
        'processed_at',
        'rejection_reason',
        'reject_reason',
        'transfer_proof',
        'transfer_reference',
        'notes',
    ];

    public function getTransferReferenceAttribute(): ?string
    {
        return $this->transfer_proof;
    }

    public function setTransferReferenceAttribute($value): void
    {
        $this->attributes['transfer_proof'] = $value;
    }

    public function getRejectReasonAttribute(): ?string
    {
        return $this->rejection_reason;
    }

    public function setRejectReasonAttribute($value): void
    {
        $this->attributes['rejection_reason'] = $value;
    }


    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'admin_fee' => 'integer',
            'net_amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TenantWallet::class, 'wallet_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
