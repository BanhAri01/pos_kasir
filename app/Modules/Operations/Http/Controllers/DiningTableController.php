<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Http\Controllers\Controller;
use App\Modules\Operations\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Daftar meja per outlet. Tambah cepat beberapa meja sekaligus (Meja 1 s/d 10). */
class DiningTableController extends Controller
{
    public function __construct(private CurrentOutlet $outlet) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Operations/Tables', [
            'tables' => DiningTable::query()->where('outlet_id', $this->outlet->id($request->user()))
                ->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'area', 'capacity', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required_without:count', 'nullable', 'string', 'max:30'],
            'area' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], ['name.required_without' => 'Isi nama meja, atau jumlah meja yang mau dibuat.']);

        $outletId = $this->outlet->id($request->user());
        $order = (int) DiningTable::query()->where('outlet_id', $outletId)->max('sort_order');

        if (! empty($data['count'])) {
            $start = DiningTable::query()->where('outlet_id', $outletId)->count() + 1;
            for ($i = 0; $i < $data['count']; $i++) {
                DiningTable::create(['outlet_id' => $outletId, 'name' => 'Meja '.($start + $i), 'area' => $data['area'] ?? null, 'capacity' => $data['capacity'] ?? null, 'sort_order' => ++$order]);
            }

            return back()->with('success', "{$data['count']} meja sudah ditambahkan.");
        }

        DiningTable::create(['outlet_id' => $outletId, 'name' => $data['name'], 'area' => $data['area'] ?? null, 'capacity' => $data['capacity'] ?? null, 'sort_order' => $order + 1]);

        return back()->with('success', "{$data['name']} sudah ditambahkan.");
    }

    public function update(Request $request, DiningTable $table): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'area' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ]);
        $table->update($data);

        return back()->with('success', 'Meja sudah disimpan.');
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        $table->delete();

        return back()->with('success', "{$table->name} dihapus.");
    }
}
