<?php

namespace App\Modules\Purchasing\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Pembayaran utang ke pemasok. */
class PurchasePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['purchase_id', 'amount', 'payment_method_id', 'paid_at', 'user_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
