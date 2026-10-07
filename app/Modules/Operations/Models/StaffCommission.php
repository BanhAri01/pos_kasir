<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Pos\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Komisi yang didapat karyawan dari satu layanan. */
class StaffCommission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'sale_id', 'sale_item_id', 'amount', 'status', 'paid_at'];

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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
