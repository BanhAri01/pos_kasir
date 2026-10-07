<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stok masuk / keluar manual. */
class StockAdjustment extends Model
{
    use BelongsToTenant;

    public const REASONS = [
        'in' => ['barang_datang' => 'Barang datang', 'dikembalikan' => 'Dikembalikan', 'lainnya' => 'Lainnya'],
        'out' => [
            'rusak' => 'Rusak / basi', 'hilang' => 'Hilang', 'dipakai_sendiri' => 'Dipakai sendiri',
            // Susut gudang (modul shrinkage)
            'susut_air' => 'Susut kadar air', 'hama' => 'Kena hama / kutu', 'tumpah' => 'Tumpah / tercecer',
            'lainnya' => 'Lainnya',
        ],
    ];

    /** Alasan yang dihitung sebagai susut di laporan susut. */
    public const SHRINKAGE_REASONS = ['rusak', 'hilang', 'susut_air', 'hama', 'tumpah'];

    /** Hanya tampil kalau modul Catat Susut menyala. */
    public const WAREHOUSE_REASONS = ['susut_air', 'hama', 'tumpah'];

    protected $fillable = ['outlet_id', 'uuid', 'number', 'type', 'reason', 'note', 'user_id'];

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
