<?php

namespace App\Modules\Assistant\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantDailyUsage extends Model
{
    protected $table = 'assistant_daily_usage';

    protected $fillable = [
        'tenant_id',
        'date',
        'total_messages',
        'total_tokens',
        'cost_idr',
    ];

    protected $casts = [
        'date' => 'date',
        'total_messages' => 'integer',
        'total_tokens' => 'integer',
        'cost_idr' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
