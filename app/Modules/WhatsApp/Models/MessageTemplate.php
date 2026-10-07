<?php

namespace App\Modules\WhatsApp\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Template pesan WhatsApp per usaha (bisa diubah pemilik). */
class MessageTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['code', 'body', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
