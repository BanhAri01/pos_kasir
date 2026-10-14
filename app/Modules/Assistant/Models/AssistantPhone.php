<?php

namespace App\Modules\Assistant\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nomor WhatsApp yang boleh bertanya ke Asisten Toko. Satu nomor hanya untuk satu usaha. */
class AssistantPhone extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'phone', 'label'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
