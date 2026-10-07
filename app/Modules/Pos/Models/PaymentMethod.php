<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use BelongsToTenant;

    /** Metode bawaan untuk setiap usaha baru. [nama, tipe] */
    public const DEFAULTS = [
        ['Tunai', 'cash'],
        ['QRIS', 'qris'],
        ['Transfer Bank', 'transfer'],
        ['E-Wallet', 'ewallet'],
    ];

    protected $fillable = ['name', 'type', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }
}
