<?php

namespace App\Core\Support;

use App\Models\Outlet;
use Illuminate\Http\Request;

class DocumentPaper
{
    public const SIZES = [
        'a4' => 'A4',
        'kontinyu' => 'Kontinyu 9,5 x 11 inci',
    ];

    public static function resolve(Request $request, ?Outlet $outlet): string
    {
        $asked = (string) $request->query('kertas', '');

        if (array_key_exists($asked, self::SIZES)) {
            return $asked;
        }

        $default = (string) ($outlet?->document_paper ?? 'a4');

        return array_key_exists($default, self::SIZES) ? $default : 'a4';
    }
}
