<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\Qty;
use App\Models\Tenant;
use App\Modules\Catalog\Models\ModifierOption;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Promotion;
use App\Modules\Customer\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menentukan harga satu baris penjualan. Rumus yang SAMA ada di resources/js/pos/lib/pricing.js.
 *
 * Urutan:
 *   1. Harga dasar  = harga khusus outlet ?? harga barang
 *   2. Satuan lain  (multi_unit)   : pakai harga & konversi satuan itu (mis. 1 dus = 40 pcs)
 *   3. Harga khusus (price_levels) : harga untuk tipe pelanggan / mulai jumlah tertentu (grosir).
 *                                    Kalau ada beberapa yang cocok, dipilih yang PALING MURAH.
 *   4. Promo        (promotions)   : potongan promo yang berlaku hari itu. Kalau ada beberapa, dipilih yang PALING BESAR.
 *                                    Varian (ukuran x warna) ikut promo modelnya.
 *   5. Tambahan     (variants_modifiers): + harga setiap pilihan (mis. "Besar +Rp5.000")
 */
class PriceResolver
{
    private Collection $outletPrices;

    private Collection $units;

    private Collection $tiers;

    private Collection $options;

    private ?int $levelId = null;

    private bool $useUnits = false;

    private bool $useLevels = false;

    private bool $useModifiers = false;

    private Collection $promotions;

    /**
     * @param  Collection<int, Product>  $products
     */
    /**
     * @param  string|null  $date  tanggal transaksi (Y-m-d, waktu usaha) untuk memilih promo yang berlaku
     */
    public function prepare(Tenant $tenant, int $outletId, Collection $products, ?Customer $customer, ?string $date = null): self
    {
        $ids = $products->keys();

        $this->useUnits = $tenant->hasModule('multi_unit');
        $this->useLevels = $tenant->hasModule('price_levels');
        $this->useModifiers = $tenant->hasModule('variants_modifiers');
        $this->levelId = $this->useLevels ? $customer?->price_level_id : null;

        $this->outletPrices = DB::table('product_outlet')->where('outlet_id', $outletId)->whereIn('product_id', $ids)->whereNotNull('price')->pluck('price', 'product_id');
        $this->units = $this->useUnits ? ProductUnit::query()->whereIn('product_id', $ids)->with('unit:id,name')->get()->keyBy('id') : collect();
        $this->tiers = $this->useLevels ? ProductPrice::query()->whereIn('product_id', $ids)->get()->groupBy('product_id') : collect();
        $this->promotions = $tenant->hasModule('promotions')
            ? Promotion::query()->activeOn($date ?? now($tenant->timezone)->toDateString())->get()
            : collect();
        $this->options = $this->useModifiers
            ? ModifierOption::query()->with('group')->whereHas('group.products', fn ($q) => $q->whereIn('products.id', $ids))->get()->keyBy('id')
            : collect();

        return $this;
    }

    /**
     * @param  list<int>  $modifierIds
     * @return array{unit_price: int, base_price: int, conversion_qty: string, unit_id: int|null, unit_name: string|null, modifiers: list<array>, promo: array{name:string, discount:int}|null}
     */
    public function resolve(Product $product, string $qty, ?int $unitId = null, array $modifierIds = [], bool $strict = true): array
    {
        $price = (int) ($this->outletPrices[$product->id] ?? $product->price);
        $conversion = '1.000';
        $unitName = $product->unit?->name;
        $resolvedUnitId = null;

        if ($unitId && $this->useUnits && ($unit = $this->units->get($unitId)) && $unit->product_id === $product->id) {
            $price = $unit->price;
            $conversion = Qty::normalize($unit->conversion_qty);
            $unitName = $unit->unit?->name;
            $resolvedUnitId = $unit->id;
        }

        // Harga khusus / grosir: cocok satuan, cocok tipe pelanggan (atau untuk semua), dan jumlah cukup.
        $candidates = ($this->tiers[$product->id] ?? collect())->filter(fn (ProductPrice $tier) => $tier->product_unit_id === $resolvedUnitId
            && ($tier->price_level_id === null || $tier->price_level_id === $this->levelId)
            && Qty::cmp($qty, $tier->min_qty) >= 0);

        if ($candidates->isNotEmpty()) {
            $price = min($price, (int) $candidates->min('price'));
        }
        // Promo: potongan terbesar yang berlaku untuk barang ini.
        $promo = null;
        foreach ($this->promotions as $promotion) {
            if ($promotion->appliesTo($product) && ($discount = $promotion->discountFor($price)) > ($promo['discount'] ?? 0)) {
                $promo = ['name' => $promotion->name, 'discount' => $discount];
            }
        }
        $price -= $promo['discount'] ?? 0;
        $basePrice = $price;

        // Pilihan tambahan.
        $modifiers = [];
        if ($this->useModifiers) {
            $chosen = collect($modifierIds)->map(fn ($id) => $this->options->get((int) $id))->filter();
            $groups = $product->relationLoaded('modifierGroups') ? $product->modifierGroups : $product->modifierGroups()->get();

            foreach ($groups as $group) {
                $picked = $chosen->where('modifier_group_id', $group->id);
                if ($strict && $group->is_required && $picked->isEmpty()) {
                    throw ValidationException::withMessages(['items' => "Pilih {$group->name} untuk {$product->name}."]);
                }
                if ($group->selection === 'single') {
                    $picked = $picked->take(1);
                }
                foreach ($picked as $option) {
                    $modifiers[] = [
                        'modifier_option_id' => $option->id,
                        'group_name' => $group->name,
                        'name' => $option->name,
                        'price_delta' => $option->price_delta,
                    ];
                    $price += $option->price_delta;
                }
            }
        }

        return [
            'unit_price' => max(0, $price),
            'base_price' => $basePrice,
            'conversion_qty' => $conversion,
            'unit_id' => $resolvedUnitId,
            'unit_name' => $unitName,
            'modifiers' => $modifiers,
            'promo' => $promo,
        ];
    }
}
