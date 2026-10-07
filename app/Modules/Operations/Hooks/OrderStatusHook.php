<?php

namespace App\Modules\Operations\Hooks;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Operations\Services\OrderStatusService;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;

/**
 * Pesanan laundry (dan usaha sejenis) otomatis masuk status pertama, dengan perkiraan selesai
 * dari layanan yang paling lama (mis. cuci setrika 2 hari).
 */
class OrderStatusHook implements SaleHook
{
    public function __construct(
        private TenantContext $context,
        private OrderStatusService $statuses,
    ) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        if (! $this->context->get()->hasModule('order_status')) {
            return;
        }

        $minutes = (int) $sale->items()->join('products', 'products.id', '=', 'sale_items.product_id')->max('products.duration_minutes');

        $sale->update(['estimated_ready_at' => $sale->completed_at->copy()->addMinutes($minutes > 0 ? $minutes : 2880)]);
        $this->statuses->start($sale, $cashier->id);
    }
}
