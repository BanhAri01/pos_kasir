<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Pos\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Piutang: kasbon pelanggan atau penjualan tempo. */
class Receivable extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'sale_id', 'type', 'amount', 'paid_amount', 'due_date', 'status', 'note', 'last_reminded_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'due_date' => 'date',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }
}
