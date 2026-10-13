<?php

namespace App\Modules\Billing\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletMutation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'wallet_id',
        'type',
        'category',
        'amount',
        'fee',
        'net_amount',
        'balance_after',
        'reference',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'net_amount' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
        ];
    }

    public function getGrossAmountAttribute(): int
    {
        return (int) $this->amount;
    }

    public function getFeeAmountAttribute(): int
    {
        return (int) $this->fee;
    }


    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TenantWallet::class, 'wallet_id');
    }
}
