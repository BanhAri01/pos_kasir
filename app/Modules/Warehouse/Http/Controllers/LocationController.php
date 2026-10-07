<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Blok / rak penyimpanan di gudang aktif, dan barang mana disimpan di mana. */
class LocationController extends Controller
{
    public function __construct(private CurrentOutlet $outlet) {}

    public function index(Request $request): Response
    {
        $outletId = $this->outlet->id($request->user());

        $counts = Stock::query()->where('outlet_id', $outletId)->whereNotNull('location_id')
            ->select('location_id', DB::raw('count(*) as total'))->groupBy('location_id')->pluck('total', 'location_id');

        return Inertia::render('Warehouse/Locations', [
            'locations' => WarehouseLocation::query()->where('outlet_id', $outletId)->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (WarehouseLocation $l) => [
                    'id' => $l->id, 'name' => $l->name, 'note' => $l->note, 'product_count' => (int) ($counts[$l->id] ?? 0),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $outletId = $this->outlet->id($request->user());
        $location = WarehouseLocation::create([...$this->validated($request, $outletId), 'outlet_id' => $outletId]);

        return back()->with('success', "Blok {$location->name} sudah ditambahkan.");
    }

    public function update(Request $request, WarehouseLocation $location): RedirectResponse
    {
        $location->update($this->validated($request, $location->outlet_id, $location));

        return back()->with('success', 'Blok sudah disimpan.');
    }

    public function destroy(WarehouseLocation $location): RedirectResponse
    {
        $location->delete(); // barang di blok ini otomatis jadi "belum ditentukan" (nullOnDelete)

        return back()->with('success', "Blok {$location->name} dihapus. Barangnya tetap ada, bloknya jadi belum ditentukan.");
    }

    /** Tentukan blok penyimpanan satu barang di gudang aktif. */
    public function assign(Request $request, Product $product, StockService $stock): RedirectResponse
    {
        $outletId = $this->outlet->id($request->user());
        $data = $request->validate([
            'location_id' => ['nullable', 'integer', Rule::exists('warehouse_locations', 'id')->where('outlet_id', $outletId)],
        ], ['location_id.exists' => 'Blok tidak ditemukan di gudang ini.']);

        $stock->setLocation($outletId, $product, $data['location_id'] ?? null);

        return back()->with('success', 'Tempat penyimpanan sudah disimpan.');
    }

    private function validated(Request $request, int $outletId, ?WarehouseLocation $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('warehouse_locations', 'name')->where('outlet_id', $outletId)->ignore($location?->id)],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama blok belum diisi. Contoh: Blok A.',
            'name.unique' => 'Nama blok ini sudah ada di gudang ini.',
        ]);
    }
}
