<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catatan satu kali olah / kemas ulang / bongkar karung. */
class ProductionOrder extends Model
{
    use BelongsToTenant;

    public const KINDS = ['repack' => 'Kemas ulang', 'unpack' => 'Bongkar karung', 'production' => 'Olah / produksi'];

    protected $fillable = [
        'outlet_id', 'uuid', 'number', 'kind', 'production_formula_id', 'output_product_id', 'batches',
        'planned_output_qty', 'output_qty', 'input_weight', 'shrinkage_qty', 'materials_cost', 'packaging_cost',
        'labor_cost', 'utility_cost', 'machine_cost', 'other_cost', 'unit_cost', 'batch_no', 'expires_at', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'batches' => 'decimal:3',
            'planned_output_qty' => 'decimal:3',
            'output_qty' => 'decimal:3',
            'input_weight' => 'decimal:3',
            'shrinkage_qty' => 'decimal:3',
            'materials_cost' => 'integer',
            'packaging_cost' => 'integer',
            'labor_cost' => 'integer',
            'utility_cost' => 'integer',
            'machine_cost' => 'integer',
            'other_cost' => 'integer',
            'unit_cost' => 'integer',
            'expires_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(ProductionFormula::class, 'production_formula_id');
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id')->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Biaya olah: upah + listrik + mesin + lain-lain. */
    public function processingCost(): int
    {
        return $this->labor_cost + $this->utility_cost + $this->machine_cost + $this->other_cost;
    }
}
