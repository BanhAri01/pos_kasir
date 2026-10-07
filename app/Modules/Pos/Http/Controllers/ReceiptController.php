<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Struk digital: dibuka pelanggan dari link WhatsApp tanpa login.
 * Link ditandatangani (signed URL), jadi tidak bisa ditebak / diubah.
 * Tambahkan ?cetak=1 untuk langsung membuka jendela cetak (printer biasa 58/80mm).
 */
class ReceiptController extends Controller
{
    public function show(Request $request, string $uuid, TenantContext $context): View
    {
        $sale = Sale::allTenants()->where('uuid', $uuid)->firstOrFail();

        return $context->runAs($sale->tenant()->firstOrFail(), function ($tenant) use ($sale, $request) {
            $sale->load(['items', 'payments', 'outlet', 'cashier:id,name', 'customer']);

            return view('receipts.show', [
                'sale' => $sale,
                'tenant' => $tenant,
                'outlet' => $sale->outlet,
                'outletPhone' => Phone::display($sale->outlet->phone),
                'at' => ($sale->completed_at ?? $sale->created_at)->timezone($tenant->timezone),
                'timezoneLabel' => $tenant->timezoneLabel(),
                'paper' => $request->query('kertas', $sale->outlet->receipt_paper ?? '58'),
                'autoPrint' => $request->boolean('cetak'),
            ]);
        });
    }
}
