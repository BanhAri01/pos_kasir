<?php

namespace App\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Outlet extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    /** Toko (melayani pembeli) atau gudang (penyimpanan, tetap bisa jual). */
    public const TYPES = ['store' => 'Toko', 'warehouse' => 'Gudang'];

    protected $fillable = [
        'name', 'code', 'type', 'address', 'phone', 'receipt_header', 'receipt_footer', 'receipt_paper', 'document_paper',
        'tax_rate_bp', 'tax_inclusive', 'service_charge_bp', 'settings', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate_bp' => 'integer',
            'tax_inclusive' => 'boolean',
            'service_charge_bp' => 'integer',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isWarehouse(): bool
    {
        return $this->type === 'warehouse';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'address', 'phone', 'tax_rate_bp', 'service_charge_bp', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('outlet');
    }
}
