<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use BelongsToTenant;

    /** Satuan bawaan untuk setiap usaha baru. [nama, simbol, boleh desimal] */
    public const DEFAULTS = [
        ['Pcs', 'pcs', false], ['Porsi', 'porsi', false], ['Gelas', 'gelas', false],
        ['Bungkus', 'bks', false], ['Botol', 'btl', false], ['Dus', 'dus', false],
        ['Pak', 'pak', false], ['Lembar', 'lbr', false], ['Pasang', 'psg', false],
        ['Kali', 'kali', false], ['Paket', 'paket', false], ['Sak', 'sak', false],
        ['Batang', 'btg', false], ['Roll', 'roll', false], ['Karung', 'krg', false],
        ['Kg', 'kg', true], ['Gram', 'g', true], ['Liter', 'L', true],
        ['Meter', 'm', true], ['Kubik', 'm³', true],
    ];

    protected $fillable = ['name', 'symbol', 'allow_decimal'];

    protected function casts(): array
    {
        return ['allow_decimal' => 'boolean'];
    }
}
