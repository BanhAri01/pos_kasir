<?php

namespace App\Modules\Operations\Hooks;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;

/**
 * Kasbon / jual tempo / bayar saat ambil: sisa yang belum dibayar dicatat sebagai piutang pelanggan.
 */
class ReceivableHook implements SaleHook
{
    public function __construct(private TenantContext $context) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        if ($sale->due_amount <= 0 || ! $sale->customer_id) {
            return;
        }

        Receivable::create([
            'customer_id' => $sale->customer_id,
            'sale_id' => $sale->id,
            'type' => $this->context->get()->hasModule('kasbon') ? 'kasbon' : 'tempo',
            'amount' => $sale->due_amount,
            'paid_amount' => 0,
            'due_date' => $sale->due_date,
            'status' => 'open',
            'note' => "Nota {$sale->number}",
        ]);
    }
}
