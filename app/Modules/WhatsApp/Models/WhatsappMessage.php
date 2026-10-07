<?php

namespace App\Modules\WhatsApp\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Catatan setiap pesan WhatsApp yang dikirim. */
class WhatsappMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['to_phone', 'template_code', 'body', 'status', 'provider', 'provider_message_id', 'error', 'attempts', 'related_type', 'related_id', 'sent_at'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
