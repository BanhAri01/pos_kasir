<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Modules\Inventory\Http\Requests\StockOpnameRequest;
use App\Modules\Inventory\Http\Requests\StockTransferRequest;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\StockDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockDocumentController extends Controller
{
    public function __construct(
        private StockDocumentService $documents,
        private CurrentOutlet $outlet,
    ) {}

    public function storeAdjustment(StockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $adjustment = $this->documents->adjust(
            $this->outlet->id($request->user()), $data['type'], $data['reason'], $data['items'], $data['note'] ?? null,
        );

        $what = $data['type'] === 'in' ? 'Stok masuk' : 'Stok keluar';

        return redirect()->route('stock.index')->with('success', "{$what} sudah dicatat ({$adjustment->items()->count()} barang).");
    }

    public function storeOpname(StockOpnameRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $opname = $this->documents->opname($this->outlet->id($request->user()), $data['items'], $data['note'] ?? null);

        return redirect()->route('stock.index')->with('success', "Hasil hitung stok sudah disimpan ({$opname->items()->count()} barang). Stok sudah disamakan.");
    }

    public function transfers(TenantContext $context): Response
    {
        $timezone = $context->get()->timezone;

        $transfers = StockTransfer::query()
            ->with(['fromOutlet:id,name', 'toOutlet:id,name', 'items.product:id,name'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (StockTransfer $t) => [
                'id' => $t->id,
                'number' => $t->number,
                'status' => $t->status,
                'from' => $t->fromOutlet?->name,
                'to' => $t->toOutlet?->name,
                'items' => $t->items->map(fn ($i) => $i->product?->name.' × '.Qty::display($i->qty))->all(),
                'sent_at' => $t->sent_at?->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
            ]);

        return Inertia::render('Stock/Transfers', [
            'transfers' => $transfers,
            'canSend' => Outlet::count() > 1,
        ]);
    }

    public function createTransfer(Request $request): Response
    {
        $current = $this->outlet->get($request->user());

        return Inertia::render('Stock/TransferForm', [
            'fromOutlet' => ['id' => $current->id, 'name' => $current->name],
            'outlets' => Outlet::query()->where('is_active', true)->where('id', '!=', $current->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeTransfer(StockTransferRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $transfer = $this->documents->sendTransfer(
            $this->outlet->id($request->user()), (int) $data['to_outlet_id'], $data['items'], $data['note'] ?? null,
        );

        return redirect()->route('stock.transfers')->with('success', "Kiriman {$transfer->number} sudah dicatat. Tandai \"Sudah Diterima\" saat barang sampai.");
    }

    public function receiveTransfer(StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeStock();
        $this->documents->receiveTransfer($transfer);

        return back()->with('success', "Kiriman {$transfer->number} sudah diterima. Stok outlet tujuan bertambah.");
    }

    public function cancelTransfer(StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeStock();
        $this->documents->cancelTransfer($transfer);

        return back()->with('success', "Kiriman {$transfer->number} dibatalkan. Stok kembali ke outlet asal.");
    }

    private function authorizeStock(): void
    {
        abort_unless(request()->user()->can('manage_stock'), 403);
    }
}
