<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\PriceLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tipe harga (Grosir, Tukang, Kontraktor). Harga per barang diisi di formulir barang. */
class PriceLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Catalog/PriceLevels', [
            'levels' => PriceLevel::query()->withCount('customers')->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50']], ['name.required' => 'Nama tipe harga belum diisi.']);
        PriceLevel::create([...$data, 'sort_order' => (int) PriceLevel::query()->max('sort_order') + 1]);

        return back()->with('success', "Tipe harga \"{$data['name']}\" sudah dibuat.");
    }

    public function update(Request $request, PriceLevel $level): RedirectResponse
    {
        $level->update($request->validate(['name' => ['required', 'string', 'max:50']]));

        return back()->with('success', 'Tipe harga sudah disimpan.');
    }

    public function destroy(PriceLevel $level): RedirectResponse
    {
        $level->delete();

        return back()->with('success', "Tipe harga \"{$level->name}\" dihapus.");
    }
}
