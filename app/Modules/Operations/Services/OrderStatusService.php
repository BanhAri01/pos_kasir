<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OrderStatus;
use App\Modules\Operations\Models\OrderStatusHistory;
use App\Modules\Pos\Models\Sale;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alur status pesanan (laundry, jahit, servis): Diterima > Dicuci > Disetrika > Siap Diambil > Sudah Diambil.
 * Saat masuk status yang ditandai "beri tahu pelanggan", pesan WhatsApp dikirim.
 */
class OrderStatusService
{
    public const DEFAULTS = [
        ['Diterima', 'info', false, false],
        ['Dicuci', 'info', false, false],
        ['Disetrika', 'info', false, false],
        ['Siap Diambil', 'success', false, true],
        ['Sudah Diambil', 'neutral', true, false],
    ];

    public function __construct(private WhatsAppService $whatsapp) {}

    /** @return Collection<int, OrderStatus> */
    public function statuses(): Collection
    {
        if (! OrderStatus::query()->exists()) {
            foreach (self::DEFAULTS as $i => [$name, $color, $final, $notify]) {
                OrderStatus::create(['name' => $name, 'color' => $color, 'sort_order' => $i, 'is_final' => $final, 'notify_customer' => $notify]);
            }
        }

        return OrderStatus::query()->orderBy('sort_order')->get();
    }

    public function start(Sale $sale, ?int $userId): void
    {
        $first = $this->statuses()->first();
        $sale->update(['order_status_id' => $first->id]);
        OrderStatusHistory::create(['sale_id' => $sale->id, 'to_status_id' => $first->id, 'user_id' => $userId]);
    }

    public function moveTo(Sale $sale, OrderStatus $status, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $status, $user) {
            $from = $sale->order_status_id;
            $sale->update([
                'order_status_id' => $status->id,
                'picked_up_at' => $status->is_final ? now() : $sale->picked_up_at,
            ]);
            OrderStatusHistory::create(['sale_id' => $sale->id, 'from_status_id' => $from, 'to_status_id' => $status->id, 'user_id' => $user->id]);

            if ($status->notify_customer && $sale->customer?->phone) {
                $this->whatsapp->sendTemplate('order_ready', $sale->customer->phone, [
                    'nama' => $sale->customer->name,
                    'nota' => $sale->number,
                    'status' => $status->name,
                    'sisa' => $sale->due_amount > 0 ? 'Sisa pembayaran: '.\App\Core\Support\Rupiah::format($sale->due_amount) : 'Sudah lunas',
                ], $sale);
            }

            return $sale;
        });
    }

    public function next(Sale $sale): ?OrderStatus
    {
        $statuses = $this->statuses();
        $index = $statuses->search(fn ($s) => $s->id === $sale->order_status_id);

        return $index === false ? $statuses->first() : $statuses->get($index + 1);
    }
}
