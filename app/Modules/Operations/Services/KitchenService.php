<?php

namespace App\Modules\Operations\Services;

use App\Modules\Operations\Models\KitchenTicket;
use Illuminate\Validation\ValidationException;

/** Layar dapur: terima pesanan dari kasir, lalu dapur menandai Mulai > Siap > Sudah diantar. */
class KitchenService
{
    public const FLOW = ['new' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'];

    /** Dari kasir (termasuk kasir offline lewat sinkronisasi). Idempotent dengan uuid. */
    public function create(array $data, ?int $userId): KitchenTicket
    {
        return KitchenTicket::query()->firstOrCreate(['uuid' => $data['uuid']], [
            'outlet_id' => $data['outlet_id'],
            'label' => $data['label'],
            'order_type' => $data['order_type'] ?? null,
            'items' => $data['items'],
            'note' => $data['note'] ?? null,
            'status' => 'new',
            'sale_uuid' => $data['sale_uuid'] ?? null,
            'created_by' => $userId,
        ]);
    }

    public function advance(KitchenTicket $ticket): KitchenTicket
    {
        $next = self::FLOW[$ticket->status] ?? null;
        if (! $next) {
            throw ValidationException::withMessages(['ticket' => 'Pesanan ini sudah selesai.']);
        }

        $ticket->update([
            'status' => $next,
            'ready_at' => $next === 'ready' ? now() : $ticket->ready_at,
            'served_at' => $next === 'served' ? now() : $ticket->served_at,
        ]);

        return $ticket;
    }
}
