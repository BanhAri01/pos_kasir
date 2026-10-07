<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelompok pilihan, mis. "Ukuran" (Kecil/Besar) atau "Topping". */
class ModifierGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'selection', 'is_required', 'max_select', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'max_select' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
