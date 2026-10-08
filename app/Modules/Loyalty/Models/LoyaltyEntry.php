<?php

namespace App\Modules\Loyalty\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'sale_id', 'type', 'points', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
