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

    protected $fillable = ['uuid', 'name', 'phone', 'address', 'notes', 'member_code', 'price_level_id', 'credit_limit'];

    protected function casts(): array
    {
        return ['credit_limit' => 'integer'];
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function serviceNotes(): HasMany
    {
        return $this->hasMany(CustomerServiceNote::class)->latest();
    }

    /** Sisa utang (kasbon + tempo) yang belum lunas. */
    public function outstandingBalance(): int
    {
        return (int) $this->receivables()->where('status', 'open')->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as balance')->value('balance');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
