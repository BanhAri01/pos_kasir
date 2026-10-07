<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Janji temu (booking) dengan karyawan & durasi. */
class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'uuid', 'customer_id', 'customer_name', 'customer_phone', 'staff_id', 'start_at', 'end_at', 'status', 'note', 'total_price', 'reminder_sent_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'total_price' => 'integer',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
