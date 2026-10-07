<?php

namespace App\Core\Support;

/**
 * Menyeragamkan no HP Indonesia ke format 628xxxxxxxxx (siap dipakai WhatsApp).
 *
 * Menerima: 0812-3456-7890, 0812 3456 7890, +62 812 3456 7890, 62812..., 812...
 */
class Phone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $normalized = '62'.$digits;

        // No HP Indonesia: 62 8xx, total 10-15 digit.
        return preg_match('/^628\d{7,12}$/', $normalized) ? $normalized : null;
    }

    /** Untuk ditampilkan: 0812-3456-7890 */
    public static function display(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $local = '0'.substr($phone, 2);

        return trim(implode('-', str_split($local, 4)), '-');
    }
}
