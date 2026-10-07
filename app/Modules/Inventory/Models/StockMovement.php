<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Catatan setiap perubahan stok (tidak pernah diubah / dihapus). */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /** Label bahasa sehari-hari untuk riwayat stok. */
    public const LABELS = [
        'initial' => 'Stok awal',
        'sale' => 'Terjual',
        'sale_void' => 'Penjualan dibatalkan',
        'refund' => 'Barang dikembalikan pembeli',
        'purchase' => 'Belanja dari pemasok',
        'adjust_in' => 'Stok masuk',
        'adjust_out' => 'Stok keluar',
        'opname' => 'Hitung stok',
        'transfer_in' => 'Kiriman dari outlet lain',
        'transfer_out' => 'Dikirim ke outlet lain',
        'recipe_usage' => 'Dipakai untuk resep',
        'production_out' => 'Dipakai untuk olah / kemas',
        'production_in' => 'Hasil olah / kemas',
    ];

    protected $fillable = [
        'outlet_id', 'product_id', 'type', 'qty_change', 'qty_before', 'qty_after', 'unit_cost',
        'reference_type', 'reference_id', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:3',
            'qty_before' => 'decimal:3',
            'qty_after' => 'decimal:3',
            'unit_cost' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
