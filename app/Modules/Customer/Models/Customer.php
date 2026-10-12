<?php

namespace App\Modules\Customer\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Operations\Models\CustomerServiceNote;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['uuid', 'name', 'phone', 'address', 'notes', 'member_code', 'price_level_id', 'credit_limit', 'billing_day', 'deposit_balance'];

    protected $hidden = ['bill_token'];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'billing_day' => 'integer',
            'deposit_balance' => 'integer',
        ];
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(\App\Modules\Warehouse\Models\CustomerDeposit::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function serviceNotes(): HasMany
    {
        return $this->hasMany(CustomerServiceNote::class)->latest();
    }

    /** Sisa utang (kasbon + tempo) kotor sebelum dipotong saldo deposit. */
    public function outstandingBalance(): int
    {
        return (int) $this->receivables()->where('status', 'open')->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as balance')->value('balance');
    }

    /** Sisa kewajiban bersih setelah dipotong saldo titip uang (deposit). */
    public function netOutstandingBalance(): int
    {
        return max(0, $this->outstandingBalance() - (int) $this->deposit_balance);
    }

    /** Tambah / catat mutasi titip uang pelanggan. */
    public function addDeposit(int $amount, string $type, ?string $notes = null, string $source = 'warehouse', ?int $receivableId = null, ?int $userId = null): \App\Modules\Warehouse\Models\CustomerDeposit
    {
        $current = (int) $this->deposit_balance;
        $newBalance = match ($type) {
            'deposit', 'overpayment' => $current + $amount,
            'withdrawal', 'deduction' => max(0, $current - $amount),
            default => $current,
        };

        $this->update(['deposit_balance' => $newBalance]);

        return $this->deposits()->create([
            'tenant_id' => $this->tenant_id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'notes' => $notes,
            'source' => $source,
            'receivable_id' => $receivableId,
            'created_by' => $userId,
        ]);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
