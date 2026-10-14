<?php

namespace App\Modules\Customer\Models;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Operations\Models\CustomerServiceNote;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use App\Modules\Warehouse\Models\CustomerDeposit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['uuid', 'name', 'phone', 'address', 'notes', 'member_code', 'price_level_id', 'credit_limit', 'billing_day'];

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
        return $this->hasMany(CustomerDeposit::class);
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

    /**
     * Catat mutasi titip uang. Baris pelanggan dikunci supaya dua simpanan bersamaan
     * tidak saling menimpa saldo, dan saldo tidak pernah bisa minus.
     */
    public function addDeposit(int $amount, string $type, ?string $notes = null, string $source = 'warehouse', ?int $receivableId = null, ?int $userId = null): CustomerDeposit
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal titip uang harus lebih dari Rp0.']);
        }

        $sign = match ($type) {
            'deposit', 'overpayment' => 1,
            'withdrawal', 'deduction' => -1,
            default => throw new InvalidArgumentException("Jenis titip uang tidak dikenal: {$type}"),
        };

        return DB::transaction(function () use ($amount, $type, $sign, $notes, $source, $receivableId, $userId) {
            $current = (int) static::query()->whereKey($this->getKey())->lockForUpdate()->value('deposit_balance');
            $balance = $current + $sign * $amount;

            if ($balance < 0) {
                throw ValidationException::withMessages(['amount' => 'Saldo titip uang tidak cukup (sisa '.Rupiah::format($current).').']);
            }

            $this->forceFill(['deposit_balance' => $balance])->save();

            return $this->deposits()->create([
                'tenant_id' => $this->tenant_id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balance,
                'notes' => $notes,
                'source' => $source,
                'receivable_id' => $receivableId,
                'created_by' => $userId,
            ]);
        });
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
