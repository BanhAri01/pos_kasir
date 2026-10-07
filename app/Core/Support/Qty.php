<?php

namespace App\Core\Support;

/**
 * Hitungan jumlah barang (qty) memakai bcmath dengan 3 angka di belakang koma,
 * supaya 0,1 kg + 0,2 kg = 0,3 kg tepat (tanpa galat float).
 */
class Qty
{
    public const SCALE = 3;

    public static function normalize(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        // Terima koma sebagai pemisah desimal (kebiasaan Indonesia): "1,5" -> "1.5"
        $value = str_replace(',', '.', (string) $value);

        return bcadd($value, '0', self::SCALE);
    }

    /**
     * Rapikan ketikan pengguna sebelum divalidasi: "1,5" -> "1.5", "1.245,5" -> "1245.5".
     * Nilai yang bukan teks dikembalikan apa adanya.
     */
    public static function fromInput(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return str_contains($value, ',') ? str_replace(['.', ','], ['', '.'], $value) : $value;
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function isNegative(string $value): bool
    {
        return self::cmp($value, '0') < 0;
    }

    public static function isZero(string $value): bool
    {
        return self::cmp($value, '0') === 0;
    }

    public static function negate(string $value): string
    {
        return bcmul(self::normalize($value), '-1', self::SCALE);
    }

    /** Rupiah (int) x qty, dibulatkan ke rupiah terdekat. */
    public static function money(int $price, string $qty): int
    {
        return (int) round((float) bcmul((string) $price, self::normalize($qty), 4));
    }

    /** Untuk ditampilkan: "2", "1,5", "0,25" (tanpa nol di belakang). */
    public static function display(string|int|float|null $value): string
    {
        $normalized = rtrim(rtrim(self::normalize($value), '0'), '.');

        return str_replace('.', ',', $normalized === '-0' ? '0' : $normalized);
    }
}
