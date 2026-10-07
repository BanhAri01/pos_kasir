<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Operations\Models\OrderStatus;
use App\Modules\Operations\Services\OrderStatusService;
use App\Modules\Operations\Services\ReceivableService;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pesanan laundry (dan usaha sejenis): papan per status + lanjut ke status berikutnya + ambil & lunasi. */
class OrderBoardController extends Controller
{
    public function __construct(private OrderStatusService $statuses, private TenantContext $context) {}

    public function index(Request $request, CurrentOutlet $outlet): Response
    {
        $timezone = $this->context->get()->timezone;
        $statuses = $this->statuses->statuses();
        $final = $statuses->where('is_final', true)->pluck('id');

        $orders = Sale::query()
            ->where('outlet_id', $outlet->id($request->user()))
            ->whereNotNull('order_status_id')
            ->where('status', 'completed')
            ->where(fn ($q) => $q->whereNotIn('order_status_id', $final)->orWhere('picked_up_at', '>=', now()->subDay()))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))))
            ->with(['customer:id,name,phone', 'items:id,sale_id,name,qty,unit_name'])
            ->orderBy('estimated_ready_at')
            ->limit(200)
            ->get()
            ->map(fn (Sale $s) => [
                'uuid' => $s->uuid,
                'number' => $s->number,
                'customer' => $s->customer?->name,
                'phone' => Phone::display($s->customer?->phone),
                'items' => $s->items->map(fn ($i) => \App\Core\Support\Qty::display($i->qty).' '.($i->unit_name ? $i->unit_name.' ' : '').$i->name)->join(', '),
                'status_id' => $s->order_status_id,
                'total' => $s->total,
                'due_amount' => $s->due_amount,
                'ready_at' => $s->estimated_ready_at?->timezone($timezone)->locale('id')->translatedFormat('D, j M H:i'),
                'late' => $s->estimated_ready_at && $s->estimated_ready_at->isPast() && ! $final->contains($s->order_status_id),
            ]);

        return Inertia::render('Operations/Orders', [
            'statuses' => $statuses->map->only(['id', 'name', 'color', 'is_final', 'notify_customer'])->values(),
            'orders' => $orders,
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->where('type', '!=', 'kasbon')->orderBy('sort_order')->get(['id', 'name']),
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function move(Request $request, string $uuid): RedirectResponse
    {
        $data = $request->validate(['status_id' => ['nullable', 'integer']]);
        $sale = Sale::query()->where('uuid', $uuid)->with('customer')->firstOrFail();

        $status = ! empty($data['status_id']) ? OrderStatus::query()->findOrFail($data['status_id']) : $this->statuses->next($sale);
        if (! $status) {
            return back()->with('info', 'Pesanan ini sudah di tahap terakhir.');
        }

        $this->statuses->moveTo($sale, $status, $request->user());

        return back()->with('success', "Nota {$sale->number} sekarang: {$status->name}.".($status->notify_customer && $sale->customer?->phone ? ' Pelanggan dikabari lewat WhatsApp.' : ''));
    }

    /** Pelanggan mengambil pesanan: lunasi sisa pembayaran (kalau ada), lalu tandai sudah diambil. */
    public function pickup(Request $request, string $uuid, ReceivableService $receivables): RedirectResponse
    {
        $data = $request->validate(['payment_method_id' => ['nullable', 'integer']]);
        $sale = Sale::query()->where('uuid', $uuid)->with('customer')->firstOrFail();

        if ($sale->due_amount > 0 && $sale->customer) {
            $receivables->pay($sale->customer, $sale->due_amount, $data['payment_method_id'] ?? null, $request->user());
        }

        $final = $this->statuses->statuses()->firstWhere('is_final', true);
        $this->statuses->moveTo($sale->fresh('customer'), $final, $request->user());

        return back()->with('success', "Nota {$sale->number} sudah diambil. Terima kasih!");
    }
}
