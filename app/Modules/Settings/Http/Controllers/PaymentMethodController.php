<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Cara bayar yang muncul di kasir: nyalakan/matikan, atau tambah (misal "GoPay", "BCA"). */
class PaymentMethodController extends Controller
{
    public const TYPES = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'transfer' => 'Transfer bank',
        'ewallet' => 'E-wallet (GoPay, OVO, DANA)',
        'card' => 'Kartu debit / kredit',
        'other' => 'Lainnya',
    ];

    public function index(Request $request): Response
    {
        $this->ensureAllowed($request);

        return Inertia::render('Settings/PaymentMethods', [
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'type', 'is_active']),
            'types' => collect(self::TYPES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAllowed($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
        ], ['name.required' => 'Nama cara bayar belum diisi. Contoh: GoPay.']);

        PaymentMethod::create([...$data, 'is_active' => true, 'sort_order' => (int) PaymentMethod::max('sort_order') + 1]);

        return back()->with('success', "Cara bayar {$data['name']} sudah ditambahkan.");
    }

    public function update(Request $request, PaymentMethod $method): RedirectResponse
    {
        $this->ensureAllowed($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if (! $data['is_active'] && PaymentMethod::where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Minimal harus ada satu cara bayar yang menyala.');
        }

        $method->update($data);

        return back()->with('success', $data['is_active'] ? "{$method->name} dinyalakan." : "{$method->name} dimatikan.");
    }

    private function ensureAllowed(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ManageBusiness->value), 403);
    }
}
