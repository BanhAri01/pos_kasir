<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;

/** Layanan dalam satu janji temu. */
class BookingItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'name', 'duration_minutes', 'price'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'integer',
        ];
    }
}
