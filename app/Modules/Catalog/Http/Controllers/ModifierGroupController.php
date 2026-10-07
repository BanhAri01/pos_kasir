<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pilihan & Tambahan: kelompok (Ukuran, Gula, Topping) + pilihannya + menu yang memakainya. */
class ModifierGroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Catalog/Modifiers', [
            'groups' => ModifierGroup::query()->with(['options', 'products:id,name'])->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ModifierGroup $g) => [
                    'id' => $g->id, 'name' => $g->name, 'selection' => $g->selection, 'is_required' => $g->is_required, 'max_select' => $g->max_select,
                    'options' => $g->options->map->only(['id', 'name', 'price_delta', 'is_active'])->values(),
                    'product_ids' => $g->products->pluck('id'),
                    'product_names' => $g->products->pluck('name'),
                ]),
            'products' => Product::query()->whereIn('type', ['goods', 'service'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $this->validated($request, $context->id());
        DB::transaction(function () use ($data) {
            $group = ModifierGroup::create([...collect($data)->only(['name', 'selection', 'is_required', 'max_select'])->all(), 'sort_order' => (int) ModifierGroup::query()->max('sort_order') + 1]);
            $this->syncChildren($group, $data);
        });

        return back()->with('success', "Pilihan \"{$data['name']}\" sudah dibuat.");
    }

    public function update(Request $request, ModifierGroup $group, TenantContext $context): RedirectResponse
    {
        $data = $this->validated($request, $context->id());
        DB::transaction(function () use ($group, $data) {
            $group->update(collect($data)->only(['name', 'selection', 'is_required', 'max_select'])->all());
            $this->syncChildren($group, $data);
        });

        return back()->with('success', 'Pilihan sudah disimpan.');
    }

    public function destroy(ModifierGroup $group): RedirectResponse
    {
        $group->delete();

        return back()->with('success', "Pilihan \"{$group->name}\" dihapus.");
    }

    private function validated(Request $request, int $tenantId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'selection' => ['required', Rule::in(['single', 'multiple'])],
            'is_required' => ['boolean'],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:20'],
            'options' => ['required', 'array', 'min:1', 'max:30'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['required', 'string', 'max:60'],
            'options.*.price_delta' => ['required', 'integer', 'min:-10000000', 'max:100000000'],
            'options.*.is_active' => ['boolean'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
        ], [
            'name.required' => 'Nama pilihan belum diisi, mis. "Ukuran".',
            'options.required' => 'Tambahkan minimal satu pilihan, mis. "Besar".',
            'options.*.name.required' => 'Nama pilihan belum diisi.',
        ]);
    }

    private function syncChildren(ModifierGroup $group, array $data): void
    {
        $keep = [];
        foreach (array_values($data['options']) as $i => $option) {
            $attributes = ['name' => $option['name'], 'price_delta' => $option['price_delta'], 'is_active' => $option['is_active'] ?? true, 'sort_order' => $i];
            $model = ! empty($option['id']) ? $group->options()->whereKey($option['id'])->first() : null;
            $model ? $model->update($attributes) : $model = $group->options()->create($attributes);
            $keep[] = $model->id;
        }
        $group->options()->whereNotIn('id', $keep)->delete();
        $group->products()->sync($data['product_ids'] ?? []);
    }
}
