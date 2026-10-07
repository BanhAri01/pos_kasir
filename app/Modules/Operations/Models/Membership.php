<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Keanggotaan seorang pelanggan. */
class Membership extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'membership_plan_id', 'sale_id', 'trainer_id', 'starts_on', 'ends_on', 'sessions_remaining', 'status', 'reminder_sent_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sessions_remaining' => 'integer',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(MemberCheckin::class);
    }
}
