<?php

namespace App\Modules\Billing\Services;

class Plans
{
    public static function all(): array
    {
        return config('hermes.plans');
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(?string $plan): bool
    {
        return $plan !== null && array_key_exists($plan, self::all());
    }

    public static function normalize(?string $plan): string
    {
        return self::exists($plan) ? $plan : config('hermes.default_plan');
    }

    public static function get(?string $plan): array
    {
        return self::all()[self::normalize($plan)];
    }

    public static function label(?string $plan): string
    {
        return self::get($plan)['label'];
    }

    public static function rank(?string $plan): int
    {
        return (int) array_search(self::normalize($plan), self::keys(), true);
    }

    public static function covers(?string $plan, string $required): bool
    {
        return self::rank($plan) >= self::rank($required);
    }

    public static function requiredForModule(string $code): ?string
    {
        return config('hermes.plan_modules')[$code] ?? null;
    }

    public static function requiredForFeature(string $feature): ?string
    {
        return config('hermes.plan_features')[$feature] ?? null;
    }

    public static function durations(): array
    {
        return config('hermes.durations');
    }

    public static function price(string $plan, int $months): int
    {
        $discountBp = self::durations()[$months]['discount'] ?? 0;
        $gross = self::get($plan)['price'] * $months;

        return (int) (round($gross * (10000 - $discountBp) / 10000 / 1000) * 1000);
    }

    public static function catalog(): array
    {
        $durations = [];
        foreach (self::durations() as $months => $row) {
            $durations[] = ['months' => $months, 'label' => $row['label']];
        }

        $plans = [];
        foreach (self::all() as $key => $plan) {
            $prices = [];
            foreach (array_keys(self::durations()) as $months) {
                $prices[$months] = self::price($key, $months);
            }
            $plans[] = [
                'key' => $key,
                'label' => $plan['label'],
                'price' => $plan['price'],
                'tagline' => $plan['tagline'],
                'highlights' => $plan['highlights'],
                'prices' => $prices,
            ];
        }

        return ['plans' => $plans, 'durations' => $durations];
    }
}
