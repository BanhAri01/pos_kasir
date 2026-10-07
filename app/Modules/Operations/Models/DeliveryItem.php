<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;

/** Barang dalam satu surat jalan. */
class DeliveryItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['sale_item_id', 'name', 'qty', 'unit_name'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
        ];
    }
}
