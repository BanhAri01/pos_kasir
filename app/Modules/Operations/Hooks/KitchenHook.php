<?php

namespace App\Modules\Operations\Hooks;

use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Operations\Models\KitchenTicket;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Str;

/**
 * Pesanan yang dibayar langsung (bukan dari meja yang sudah dikirim ke dapur)
 * muncul di layar dapur / barista.
 */
class KitchenHook implements SaleHook
{
    public function __construct(private TenantContext $context) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        if (! $this->context->get()->hasModule('kitchen_display') || ($data['send_to_kitchen'] ?? true) === false) {
            return;
        }

        $items = $sale->items()->with(['modifiers', 'product:id,type'])->get()
            ->filter(fn ($item) => ! in_array($item->product?->type, ['service', 'membership'], true));

        if ($items->isEmpty()) {
            return;
        }

        KitchenTicket::create([
            'outlet_id' => $sale->outlet_id,
            'uuid' => (string) Str::uuid(),
            'label' => self::label($sale->table_id, $sale->queue_number, $sale->order_type),
            'order_type' => $sale->order_type,
            'items' => $items->map(fn ($item) => [
                'name' => $item->name,
                'qty' => Qty::display($item->qty),
                'note' => $item->note,
                'modifiers' => $item->modifiers->pluck('name')->all(),
            ])->values()->all(),
            'note' => $sale->note,
            'status' => 'new',
            'sale_uuid' => $sale->uuid,
            'created_by' => $cashier->id,
        ]);
    }

    public static function label(?int $tableId, ?string $queueNumber, ?string $orderType): string
    {
        if ($tableId && ($table = DiningTable::query()->find($tableId))) {
            return "Meja {$table->name}";
        }
        if ($queueNumber) {
            return "Antrean {$queueNumber}";
        }

        return $orderType === 'take_away' ? 'Bungkus' : 'Pesanan';
    }
}
