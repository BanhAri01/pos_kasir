<?php

namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;

class RefundItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['sale_item_id', 'qty', 'amount'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'amount' => 'integer'];
    }
}
