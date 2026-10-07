<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\Qty;

/**
 * Rumus total belanja. Rumus yang SAMA ada di resources/js/pos/lib/calculator.js
 * (supaya angka di layar kasir selalu sama dengan yang disimpan server).
 *
 * Urutan:
 *   1. Baris  = harga x jumlah - diskon barang
 *   2. Subtotal = jumlah semua baris
 *   3. Diskon transaksi (persen atau rupiah)
 *   4. Service charge dari (subtotal - diskon)
 *   5. Pajak dari (subtotal - diskon + service). Kalau harga sudah termasuk pajak,
 *      pajak hanya dicatat (tidak menambah total).
 *
 * Semua uang dibulatkan ke rupiah (bilangan bulat).
 */
class SaleCalculator
{
    /**
     * @param  list<array{unit_price: int, qty: string|int|float, discount_amount?: int}>  $lines
     * @return array{lines: list<array{gross: int, discount: int, subtotal: int}>, subtotal: int, discount_amount: int,
     *               service_charge_amount: int, tax_amount: int, total: int}
     */
    public function calculate(
        array $lines,
        ?string $discountType = null,
        int $discountValue = 0,
        int $serviceChargeBp = 0,
        int $taxBp = 0,
        bool $taxInclusive = false,
    ): array {
        $computed = [];
        $subtotal = 0;

        foreach ($lines as $line) {
            $gross = Qty::money((int) $line['unit_price'], (string) $line['qty']);
            $discount = min(max(0, (int) ($line['discount_amount'] ?? 0)), $gross);
            $lineSubtotal = $gross - $discount;

            $computed[] = ['gross' => $gross, 'discount' => $discount, 'subtotal' => $lineSubtotal];
            $subtotal += $lineSubtotal;
        }

        $discountAmount = match ($discountType) {
            'percent' => (int) round($subtotal * min(10000, max(0, $discountValue)) / 10000),
            'amount' => min(max(0, $discountValue), $subtotal),
            default => 0,
        };

        $base = $subtotal - $discountAmount;
        $service = (int) round($base * max(0, $serviceChargeBp) / 10000);
        $taxable = $base + $service;

        if ($taxInclusive) {
            $tax = $taxBp > 0 ? (int) round($taxable * $taxBp / (10000 + $taxBp)) : 0;
            $total = $taxable;
        } else {
            $tax = (int) round($taxable * max(0, $taxBp) / 10000);
            $total = $taxable + $tax;
        }

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'service_charge_amount' => $service,
            'tax_amount' => $tax,
            'total' => $total,
        ];
    }
}
