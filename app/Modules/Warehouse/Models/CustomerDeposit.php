<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDeposit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'type',
        'amount',
        'balance_after',
        'notes',
        'source',
        'receivable_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'deposit' => 'Titip Uang (Setor)',
            'withdrawal' => 'Tarik Titip Uang',
            'deduction' => 'Potong Utang / Nota',
            'overpayment' => 'Kelebihan Bayar Nota',
            default => ucfirst($this->type),
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->typeLabel();
    }
}
