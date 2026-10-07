<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sale_id', 'uuid', 'payment_method_id', 'method_type', 'method_name', 'amount', 'reference',
        'shift_id', 'received_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
