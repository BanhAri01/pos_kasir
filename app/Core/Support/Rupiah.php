<?php

namespace App\Core\Support;

/** Format Rupiah tanpa ekstensi intl: 125000 -> "Rp125.000". */
class Rupiah
{
    public static function format(int|float|null $amount): string
    {
        $value = (int) round((float) $amount);

        return ($value < 0 ? '-' : '').'Rp'.number_format(abs($value), 0, ',', '.');
    }

    /** Hanya angka: 125.000 */
    public static function number(int|float|null $amount): string
    {
        return number_format((int) round((float) $amount), 0, ',', '.');
    }

    /** Terbilang: 125000 -> "seratus dua puluh lima ribu" (untuk faktur). */
    public static function spell(int $amount): string
    {
        return match (true) {
            $amount === 0 => 'nol',
            $amount < 0 => 'minus '.self::spell(-$amount),
            default => trim(preg_replace('/\s+/', ' ', self::words($amount))),
        };
    }

    private static function words(int $amount): string
    {
        $words =['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        return match (true) {
            $amount < 12 => $words[$amount],
            $amount < 20 => self::words($amount - 10).' belas',
            $amount < 100 => self::words(intdiv($amount, 10)).' puluh '.self::words($amount % 10),
            $amount < 200 => 'seratus '.self::words($amount - 100),
            $amount < 1000 => self::words(intdiv($amount, 100)).' ratus '.self::words($amount % 100),
            $amount < 2000 => 'seribu '.self::words($amount - 1000),
            $amount < 1_000_000 => self::words(intdiv($amount, 1000)).' ribu '.self::words($amount % 1000),
            $amount < 1_000_000_000 => self::words(intdiv($amount, 1_000_000)).' juta '.self::words($amount % 1_000_000),
            $amount < 1_000_000_000_000 => self::words(intdiv($amount, 1_000_000_000)).' miliar '.self::words($amount % 1_000_000_000),
            default => self::words(intdiv($amount, 1_000_000_000_000)).' triliun '.self::words($amount % 1_000_000_000_000),
        };
    }
}
