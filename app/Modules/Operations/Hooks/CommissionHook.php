<?php

namespace App\Modules\Operations\Hooks;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Operations\Models\StaffCommission;
use App\Modules\Operations\Services\CommissionCalculator;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;

/** Komisi untuk karyawan yang mengerjakan layanan (kapster, terapis, trainer). */
class CommissionHook implements SaleHook
{
    public function __construct(
        private TenantContext $context,
        private CommissionCalculator $calculator,
    ) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        if (! $this->context->get()->hasModule('staff_commission')) {
            return;
        }

        foreach ($sale->items()->whereNotNull('staff_id')->with('product:id,category_id')->get() as $item) {
            $amount = $this->calculator->amountFor($item, $item->staff_id, $item->product?->category_id);

            if ($amount > 0) {
                StaffCommission::create([
                    'user_id' => $item->staff_id,
                    'sale_id' => $sale->id,
                    'sale_item_id' => $item->id,
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
            }
        }
    }
}
