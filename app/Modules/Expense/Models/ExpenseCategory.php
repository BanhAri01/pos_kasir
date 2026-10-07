<?php

namespace App\Modules\Expense\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jenis pengeluaran: Listrik & Air, Gaji, Sewa, Bahan, Lain-lain. */
class ExpenseCategory extends Model
{
    use BelongsToTenant;

    public const DEFAULTS = ['Bahan & Belanja Kecil', 'Gaji Karyawan', 'Listrik & Air', 'Sewa Tempat', 'Transportasi', 'Lain-lain'];

    protected $fillable = ['name', 'sort_order'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
