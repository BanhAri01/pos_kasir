<?php

namespace App\Modules\Assistant\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'channel', 'phone', 'role', 'mode', 'status', 'content', 'model',
        'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cost', 'external_id',
    ];

    protected function casts(): array
    {
        return ['cost' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
