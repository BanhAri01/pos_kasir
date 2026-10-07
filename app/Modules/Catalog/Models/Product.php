<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Modules\Inventory\Models\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Barang atau layanan yang dijual (juga bahan baku untuk resep).
 */
class Product extends Model
{
    use BelongsToTenant, LogsActivity, SoftDeletes;

    public const TYPES = ['goods', 'service', 'package', 'ingredient', 'membership'];

    public const PRICING_MODES = ['fixed', 'per_weight', 'open_price'];

    protected $fillable = [
        'uuid', 'category_id', 'parent_id', 'has_variants', 'variant_options', 'variant_values', 'type', 'name', 'code', 'barcode', 'base_unit_id', 'price', 'cost_price',
        'pricing_mode', 'track_stock', 'min_stock', 'pack_size', 'pack_name', 'pack_weight_fixed', 'is_packaging', 'track_batch', 'consignor_id', 'consignment_share_bp', 'duration_minutes', 'image_path', 'description',
        'is_sample', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost_price' => 'integer',
            'track_stock' => 'boolean',
            'min_stock' => 'decimal:3',
            'pack_size' => 'decimal:3',
            'pack_weight_fixed' => 'boolean',
            'is_packaging' => 'boolean',
            'track_batch' => 'boolean',
            'has_variants' => 'boolean',
            'variant_options' => 'array',
            'variant_values' => 'array',
            'consignment_share_bp' => 'integer',
            'duration_minutes' => 'integer',
            'is_sample' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'product_outlet')
            ->withPivot(['price', 'is_available', 'sold_out_on'])
            ->withTimestamps();
    }

    /** Pilihan ukuran / tambahan (modul variants_modifiers). */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** Bahan baku per 1 menu (modul recipe). */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /** Model (induk) dari varian ini (modul variant_matrix). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id')->withTrashed();
    }

    /** Varian ukuran x warna dari model ini. */
    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    /** Pemilik barang titipan (modul consignment). */
    public function consignor(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class, 'consignor_id')->withTrashed();
    }

    /** "M / Hitam" */
    public function variantLabel(): ?string
    {
        return $this->variant_values ? implode(' / ', array_values($this->variant_values)) : null;
    }

    /** Satuan lain (modul multi_unit). */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /** Harga grosir / per tipe pelanggan (modul price_levels). */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    /** Layanan & paket tidak punya stok. Model yang punya varian: stoknya ada di setiap varian. */
    public function tracksStock(): bool
    {
        return $this->track_stock && ! $this->has_variants && ! in_array($this->type, ['service', 'package', 'membership'], true);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(config('hermes.media_disk'))->url($this->image_path) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Perubahan harga wajib tercatat (standar kualitas: activity log untuk ubah harga).
        return LogOptions::defaults()
            ->logOnly(['name', 'price', 'cost_price', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('barang');
    }
}
