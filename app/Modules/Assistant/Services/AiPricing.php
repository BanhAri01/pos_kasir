<?php

namespace App\Modules\Assistant\Services;

class AiPricing
{
    // Nilai tukar asumsi $1 USD = Rp 16.000
    // Haiku: $0.80 / 1M input (~Rp 12.8 / 1k), $4.00 / 1M output (~Rp 64 / 1k)
    // Sonnet: $3.00 / 1M input (~Rp 48 / 1k), $15.00 / 1M output (~Rp 240 / 1k)
    public const RATES = [
        'claude-3-5-haiku-20241022' => [
            'input_per_million_usd' => 0.80,
            'output_per_million_usd' => 4.00,
        ],
        'claude-3-5-sonnet-20241022' => [
            'input_per_million_usd' => 3.00,
            'output_per_million_usd' => 15.00,
        ],
        'claude-3-haiku-20240307' => [
            'input_per_million_usd' => 0.25,
            'output_per_million_usd' => 1.25,
        ],
        'demo' => [
            'input_per_million_usd' => 0.0,
            'output_per_million_usd' => 0.0,
        ],
    ];

    public static function calculateCost(string $model, int $inputTokens, int $outputTokens, float $usdToIdr = 16000.0): float
    {
        $rate = self::RATES[$model] ?? self::RATES['claude-3-5-haiku-20241022'];
        $inputUsd = ($inputTokens / 1_000_000) * $rate['input_per_million_usd'];
        $outputUsd = ($outputTokens / 1_000_000) * $rate['output_per_million_usd'];

        return round(($inputUsd + $outputUsd) * $usdToIdr, 2);
    }
}
