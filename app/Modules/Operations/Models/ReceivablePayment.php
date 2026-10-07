<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cicilan / pelunasan piutang. */
class ReceivablePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['receivable_id', 'uuid', 'amount', 'payment_method_id', 'method_type', 'shift_id', 'user_id', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
