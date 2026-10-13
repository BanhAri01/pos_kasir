<?php

namespace App\Modules\Assistant\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAssistantPhone extends Model
{
    protected $table = 'tenant_assistant_phones';

    protected $fillable = [
        'tenant_id',
        'phone_number',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
