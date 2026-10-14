<?php

namespace App\Modules\Assistant\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Tambahan pulsa asisten untuk satu bulan (period = YYYY-MM). */
class AssistantTopup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['period', 'amount', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }
}
