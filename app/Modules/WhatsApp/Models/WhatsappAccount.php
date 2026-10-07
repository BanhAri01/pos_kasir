<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;

/** Akun pengirim WhatsApp (null tenant = nomor milik platform). */
class WhatsappAccount extends Model
{
    protected $fillable = ['provider', 'sender_phone', 'credentials', 'is_active'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
