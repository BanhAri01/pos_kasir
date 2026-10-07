<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nomor antrean (walk-in). */
class QueueTicket extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'queue_date', 'number', 'customer_name', 'customer_id', 'service_note', 'status', 'staff_id', 'called_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'number' => 'integer',
            'called_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
