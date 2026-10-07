<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Promo / diskon musiman: potongan persen atau rupiah, untuk semua barang, kategori, atau barang tertentu. */
class PromotionController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): Response
    {
        $today = now($this->context->get()->timezone)->toDateString();
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $products = Product::query()->whereNull('parent_id')->whereIn('type', ['goods', 'service'])->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Catalog/Promotions', [
            'promotions' => Promotion::query()->orderByDesc('ends_on')->get()->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'value' => $p->type === 'percent' ? rtrim(rtrim(number_format($p->value / 100, 2, ',', ''), '0'), ',') : $p->value,
                'scope' => $p->scope,
                'category_ids' => $p->category_ids ?? [],
                'product_ids' => $p->product_ids ?? [],
                'starts_on' => $p->starts_on->toDateString(),
                'ends_on' => $p->ends_on->toDateString(),
                'period' => $p->starts_on->locale('id')->translatedFormat('j M').' – '.$p->ends_on->locale('id')->translatedFormat('j M Y'),
                'status' => ! $p->is_active ? 'off' : ($p->ends_on->toDateString() < $today ? 'ended' : ($p->starts_on->toDateString() > $today ? 'upcoming' : 'running')),
                'is_active' => $p->is_active,
                'targets' => match ($p->scope) {
                    'categories' => $categories->whereIn('id', $p->category_ids ?? [])->pluck('name')->join(', '),
                    'products' => $products->whereIn('id', $p->product_ids ?? [])->pluck('name')->join(', '),
                    default => 'Semua barang',
                },
            ]),
            'categories' => $categories,
            'products' => $products,
            'today' => $today,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $promotion = Promotion::create($this->validated($request));

        return back()->with('success', "Promo {$promotion->name} sudah disimpan. Kasir otomatis memakai harga promo selama tanggalnya.");
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update($this->validated($request));

        return back()->with('success', "Promo {$promotion->name} sudah disimpan.");
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();

        return back()->with('success', "Promo {$promotion->name} dihapus.");
    }

    private function validated(Request $request): array
    {
        $tenantId = $this->context->id();
        // Persen boleh desimal ("12,5"); rupiah boleh pakai titik ribuan ("10.000").
        $value = (string) $request->input('value');
        $request->merge(['value' => $request->input('type') === 'percent' ? str_replace(',', '.', $value) : preg_replace('/\D/', '', $value)]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['percent', 'amount'])],
            'value' => ['required', 'numeric', 'gt:0', $request->input('type') === 'percent' ? 'max:100' : 'max:999999999'],
            'scope' => ['required', Rule::in(['all', 'categories', 'products'])],
            'category_ids' => ['exclude_unless:scope,categories', 'required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'product_ids' => ['exclude_unless:scope,products', 'required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'Beri nama promonya. Contoh: Diskon Lebaran.',
            'value.gt' => 'Besar potongan harus lebih dari 0.',
            'value.max' => 'Potongan persen maksimal 100.',
            'category_ids.required' => 'Pilih minimal satu kategori.',
            'product_ids.required' => 'Pilih minimal satu barang.',
            'ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            // Persen disimpan sebagai basis point (10% = 1000).
            'value' => $data['type'] === 'percent' ? (int) round((float) $data['value'] * 100) : (int) $data['value'],
            'scope' => $data['scope'],
            'category_ids' => $data['scope'] === 'categories' ? array_map('intval', $data['category_ids']) : null,
            'product_ids' => $data['scope'] === 'products' ? array_map('intval', $data['product_ids']) : null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
