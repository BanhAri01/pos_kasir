<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Uang Masuk" / "Uang Keluar" dari laci kasir di luar penjualan. */
class CashMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['shift_id', 'uuid', 'type', 'amount', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
