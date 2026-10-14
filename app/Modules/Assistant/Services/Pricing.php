<?php

namespace App\Modules\Assistant\Services;

class Pricing
{
    /** Biaya rupiah satu respons. Model tak dikenal dihitung dengan harga termahal supaya pulsa tidak bocor. */
    public static function cost(string $model, array $usage): float
    {
        $prices = config('hermes.assistant.prices.'.$model) ?? config('hermes.assistant.prices.claude-opus-5-5');

        $usd = ($usage['input'] ?? 0) * $prices['input']
            + ($usage['output'] ?? 0) * $prices['output']
            + ($usage['cache_read'] ?? 0) * $prices['cache_read']
            + ($usage['cache_write'] ?? 0) * $prices['cache_write'];

        return round($usd / 1_000_000 * (float) config('hermes.assistant.usd_rate'), 2);
    }
}
