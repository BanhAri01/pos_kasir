<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Jenis usaha = kombinasi modul + template. Menambah jenis usaha baru cukup lewat data.
 */
class BusinessType extends Model
{
    public const CATEGORIES = [
        'retail' => 'Jual Barang',
        'fnb' => 'Makanan & Minuman',
        'service' => 'Jasa',
    ];

    protected $fillable = [
        'code', 'name', 'category', 'pos_layout', 'icon', 'description', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'business_type_modules');
    }
}
