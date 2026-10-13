<?php

namespace App\Modules\Billing\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantWallet extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'balance',
        'total_qris_income',
        'pending_withdrawal',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'bank_account_name',
    ];

    public function setBankAccountNameAttribute($value): void
    {
        $this->attributes['bank_account_holder'] = $value;
    }

    public function getBankAccountNameAttribute(): ?string
    {
        return $this->attributes['bank_account_holder'] ?? null;
    }


    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'total_qris_income' => 'integer',
            'pending_withdrawal' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(WalletMutation::class, 'wallet_id')->latest('id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'wallet_id')->latest('id');
    }

    public function hasBankDetails(): bool
    {
        return filled($this->bank_name) && filled($this->bank_account_number) && filled($this->bank_account_holder);
    }

    public function canWithdraw(int $amount): bool
    {
        return $amount >= 20000 && $this->balance >= $amount;
    }
}
