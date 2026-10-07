<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pemasok: nama, no HP, alamat + total utang ke pemasok. */
class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Purchasing/Suppliers', [
            'suppliers' => Supplier::query()
                ->withSum(['purchases as debt' => fn ($q) => $q->where('payment_status', '!=', 'paid')], \Illuminate\Support\Facades\DB::raw('total - paid_amount'))
                ->orderBy('name')->get()
                ->map(fn (Supplier $s) => [
                    'id' => $s->id, 'name' => $s->name, 'phone' => Phone::display($s->phone), 'phone_raw' => $s->phone,
                    'address' => $s->address, 'notes' => $s->notes, 'debt' => (int) $s->debt,
                    'pack_weight' => $s->default_pack_weight !== null ? Qty::display($s->default_pack_weight) : '',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request));

        return back()->with('success', "Pemasok {$supplier->name} sudah ditambahkan.");
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Data pemasok sudah disimpan.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('success', "Pemasok {$supplier->name} dihapus.");
    }

    private function validated(Request $request): array
    {
        $request->merge(['pack_weight' => Qty::fromInput($request->input('pack_weight'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'pack_weight' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
        ], ['name.required' => 'Nama pemasok belum diisi.', 'pack_weight.numeric' => 'Berat per karung harus berupa angka. Contoh: 50']);
        $data['phone'] = Phone::normalize($data['phone'] ?? null);
        // Hanya diubah kalau kolomnya dikirim (form tanpa modul timbangan tidak mengirimnya).
        if ($request->has('pack_weight')) {
            $data['default_pack_weight'] = $data['pack_weight'] ?? null;
        }
        unset($data['pack_weight']);

        return $data;
    }
}
