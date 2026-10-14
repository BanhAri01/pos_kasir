<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Akun pengirim WhatsApp (null tenant = nomor milik platform). */
class WhatsappAccount extends Model
{
    protected $fillable = ['tenant_id', 'provider', 'sender_phone', 'credentials', 'is_active'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
