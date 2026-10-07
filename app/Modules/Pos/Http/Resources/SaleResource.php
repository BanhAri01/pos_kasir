<?php

namespace App\Modules\Pos\Http\Resources;

use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = app(TenantContext::class)->get()?->timezone ?? 'Asia/Jakarta';
        $at = $this->completed_at ?? $this->created_at;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'order_type' => $this->order_type,
            'order_type_label' => Sale::ORDER_TYPES[$this->order_type] ?? $this->order_type,
            'date' => $at?->timezone($timezone)->locale('id')->translatedFormat('j M Y'),
            'time' => $at?->timezone($timezone)->format('H:i'),
            'cashier' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'name' => $this->customer->name, 'phone' => $this->customer->phone,
            ] : null),
            'outlet' => $this->whenLoaded('outlet', fn () => $this->outlet?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'qty' => Qty::display($item->qty),
                'qty_raw' => (float) $item->qty,
                'unit' => $item->unit_name,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'subtotal' => $item->subtotal,
                'note' => $item->note,
                'refunded_qty' => (float) $item->refunded_qty,
                'refundable_qty' => (float) Qty::sub($item->qty, $item->refunded_qty),
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'name' => $p->method_name, 'type' => $p->method_type, 'amount' => $p->amount,
            ])),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'service_charge_amount' => $this->service_charge_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'change_amount' => $this->change_amount,
            'due_amount' => $this->due_amount,
            'refunded_amount' => $this->refunded_amount,
            'note' => $this->note,
            'void_reason' => $this->void_reason,
            'receipt_url' => $this->receiptUrl(),
        ];
    }
}
