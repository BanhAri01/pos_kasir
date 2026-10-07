<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Operations\Models\KitchenTicket;
use App\Modules\Operations\Services\KitchenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Layar dapur / barista: pesanan baru otomatis muncul (halaman memuat ulang setiap 5 detik). */
class KitchenController extends Controller
{
    public function index(Request $request, CurrentOutlet $outlet, TenantContext $context): Response
    {
        $timezone = $context->get()->timezone;

        $tickets = KitchenTicket::query()
            ->where('outlet_id', $outlet->id($request->user()))
            ->where(fn ($q) => $q->whereIn('status', ['new', 'preparing', 'ready'])->orWhere('served_at', '>=', now()->subMinutes(30)))
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn (KitchenTicket $t) => [
                'id' => $t->id,
                'label' => $t->label,
                'status' => $t->status,
                'items' => $t->items,
                'note' => $t->note,
                'time' => $t->created_at->timezone($timezone)->format('H:i'),
                'minutes' => (int) $t->created_at->diffInMinutes(now(), true),
            ]);

        return Inertia::render('Operations/Kitchen', ['tickets' => $tickets]);
    }

    public function advance(KitchenTicket $ticket, KitchenService $service): RedirectResponse
    {
        $service->advance($ticket);

        return back();
    }
}
