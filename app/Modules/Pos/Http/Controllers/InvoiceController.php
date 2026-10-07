<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Faktur / nota A4 untuk pelanggan besar (toko bangunan, grosir). Dicetak dari browser. */
class InvoiceController extends Controller
{
    public function __invoke(Request $request, string $uuid, TenantContext $context): View
    {
        abort_unless($request->user()->can('use_pos') || $request->user()->can('view_reports'), 403);

        $sale = Sale::query()->where('uuid', $uuid)->with(['items.modifiers', 'payments', 'outlet', 'cashier:id,name', 'customer'])->firstOrFail();
        $tenant = $context->get();

        return view('documents.invoice', [
            'sale' => $sale,
            'tenant' => $tenant,
            'outlet' => $sale->outlet,
            'outletPhone' => Phone::display($sale->outlet->phone),
            'customerPhone' => Phone::display($sale->customer?->phone),
            'at' => ($sale->completed_at ?? $sale->created_at)->timezone($tenant->timezone),
        ]);
    }
}
