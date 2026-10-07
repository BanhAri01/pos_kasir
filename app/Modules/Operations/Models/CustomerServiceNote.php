<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan layanan pelanggan (warna cat rambut, perawatan terakhir). */
class CustomerServiceNote extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'staff_id', 'sale_id', 'note', 'created_by'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
