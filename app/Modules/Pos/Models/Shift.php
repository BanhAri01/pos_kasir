<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Buka / tutup kasir: menghitung uang di laci di awal dan akhir jualan. */
class Shift extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'outlet_id', 'uuid', 'device_id', 'user_id', 'opened_at', 'opening_cash', 'closed_at',
        'expected_cash', 'counted_cash', 'cash_difference', 'closing_note', 'closed_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'cash_difference' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
