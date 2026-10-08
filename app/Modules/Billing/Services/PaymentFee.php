<?php

namespace App\Modules\Billing\Services;

use InvalidArgumentException;

class PaymentFee
{
    public static function channels(): array
    {
        return config('hermes.payment.fees');
    }

    public static function exists(?string $channel): bool
    {
        return $channel !== null && array_key_exists($channel, self::channels());
    }

    public static function feeFor(int $base, string $channel): int
    {
        if (! self::exists($channel)) {
            throw new InvalidArgumentException("Saluran bayar {$channel} tidak dikenal.");
        }

        $rule = self::channels()[$channel];
        $vat = 10000 + (int) config('hermes.payment.fee_vat_bp');
        $rateTimesVat = $rule['percent_bp'] * $vat;
        $denominator = 100000000 - $rateTimesVat;
        $numerator = $base * 100000000 + $rule['flat'] * $vat * 10000;
        $gross = intdiv($numerator + $denominator - 1, $denominator);

        return max(0, $gross - $base);
    }

    public static function options(int $base): array
    {
        $options = [];
        foreach (self::channels() as $key => $rule) {
            $fee = self::feeFor($base, $key);
            $options[] = ['key' => $key, 'label' => $rule['label'], 'fee' => $fee, 'total' => $base + $fee];
        }

        return $options;
    }

    public static function enabledPayments(string $channel): array
    {
        return self::channels()[$channel]['channels'];
    }
}
