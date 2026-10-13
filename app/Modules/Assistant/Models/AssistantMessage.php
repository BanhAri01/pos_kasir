<?php

namespace App\Modules\Assistant\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    protected $table = 'assistant_messages';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'sender_type',
        'channel',
        'sender_phone',
        'message',
        'tools_called',
        'tokens_used',
        'cost_idr',
    ];

    protected $casts = [
        'tools_called' => 'array',
        'tokens_used' => 'integer',
        'cost_idr' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
