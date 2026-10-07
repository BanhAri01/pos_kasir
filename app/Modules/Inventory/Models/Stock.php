<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jumlah stok satu barang di satu outlet. Hanya diubah lewat StockService. */
class Stock extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'product_id', 'location_id', 'qty', 'avg_cost'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'avg_cost' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** Blok / rak tempat barang ini disimpan di gudang (modul multi_warehouse). */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }
}
