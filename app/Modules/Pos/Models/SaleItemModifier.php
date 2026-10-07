<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Pilihan / tambahan yang dipilih untuk satu barang terjual (salinan nama & harga). */
class SaleItemModifier extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['sale_item_id', 'modifier_option_id', 'group_name', 'name', 'price_delta'];

    protected function casts(): array
    {
        return ['price_delta' => 'integer'];
    }
}
