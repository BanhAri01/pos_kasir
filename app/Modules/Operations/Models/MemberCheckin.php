<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Absen masuk member. */
class MemberCheckin extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'membership_id', 'customer_id', 'method', 'user_id', 'checked_in_at'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
