<?php

namespace App\Modules\Operations\Services;

use App\Core\Support\NumberSequence;
use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Modules\Operations\Models\Delivery;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pengiriman barang / antar-jemput dengan surat jalan. Bisa dikirim sebagian. */
class DeliveryService
{
    public function __construct(private NumberSequence $numbers) {}

    /**
     * @param  array<string, mixed>  $data  recipient_name, phone, address, scheduled_at, driver_name, vehicle, fee, note, type,
     *                                      items: list<{sale_item_id, qty}> (kosong = semua barang)
     */
    public function create(Sale $sale, array $data): Delivery
    {
        return DB::transaction(function () use ($sale, $data) {
            $delivery = Delivery::create([
                'outlet_id' => $sale->outlet_id,
                'sale_id' => $sale->id,
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next('SJ'),
                'type' => $data['type'] ?? 'delivery',
                'recipient_name' => $data['recipient_name'],
                'phone' => Phone::normalize($data['phone'] ?? null),
                'address' => $data['address'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle' => $data['vehicle'] ?? null,
                'fee' => (int) ($data['fee'] ?? 0),
                'status' => 'pending',
                'note' => $data['note'] ?? null,
            ]);

            $chosen = collect($data['items'] ?? [])->keyBy('sale_item_id');
            foreach ($sale->items as $item) {
                $qty = $chosen->isEmpty() ? $item->qty : ($chosen[$item->id]['qty'] ?? null);
                if ($qty && Qty::cmp((string) $qty, '0') > 0) {
                    $delivery->items()->create(['sale_item_id' => $item->id, 'name' => $item->name, 'qty' => Qty::normalize($qty), 'unit_name' => $item->unit_name]);
                }
            }

            return $delivery;
        });
    }

    public function setStatus(Delivery $delivery, string $status): Delivery
    {
        $delivery->update(['status' => $status, 'delivered_at' => $status === 'delivered' ? now() : null]);

        return $delivery;
    }
}
