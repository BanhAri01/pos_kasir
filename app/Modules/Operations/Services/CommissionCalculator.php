<?php

namespace App\Modules\Operations\Services;

use App\Core\Support\Qty;
use App\Modules\Operations\Models\CommissionRule;
use App\Modules\Pos\Models\SaleItem;
use Illuminate\Support\Collection;

/**
 * Menghitung komisi karyawan untuk satu layanan.
 *
 * Aturan yang dipakai (yang paling khusus menang):
 *   1. karyawan + layanan     2. layanan          3. karyawan + kategori
 *   4. kategori               5. karyawan saja    6. aturan umum
 *
 * Persen dihitung dari nilai layanan setelah diskon; nominal dikali jumlah.
 */
class CommissionCalculator
{
    private ?Collection $rules = null;

    public function amountFor(SaleItem $item, int $staffId, ?int $categoryId): int
    {
        $rule = $this->ruleFor($staffId, $item->product_id, $categoryId);

        if (! $rule) {
            return 0;
        }

        return $rule->type === 'percent'
            ? (int) round($item->subtotal * $rule->value / 10000)
            : Qty::money($rule->value, $item->qty);
    }

    public function ruleFor(int $staffId, ?int $productId, ?int $categoryId): ?CommissionRule
    {
        $this->rules ??= CommissionRule::query()->get();

        $candidates = [
            fn ($r) => $r->user_id === $staffId && $r->product_id === $productId && $productId,
            fn ($r) => $r->user_id === null && $r->product_id === $productId && $productId,
            fn ($r) => $r->user_id === $staffId && $r->product_id === null && $r->category_id === $categoryId && $categoryId,
            fn ($r) => $r->user_id === null && $r->product_id === null && $r->category_id === $categoryId && $categoryId,
            fn ($r) => $r->user_id === $staffId && $r->product_id === null && $r->category_id === null,
            fn ($r) => $r->user_id === null && $r->product_id === null && $r->category_id === null,
        ];

        foreach ($candidates as $match) {
            if ($rule = $this->rules->first($match)) {
                return $rule;
            }
        }

        return null;
    }
}
