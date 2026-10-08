<?php

namespace App\Modules\Inventory\Services;

use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestockService
{
    public function __construct(private TenantContext $context) {}

    public function lowStock(int $outletId): Collection
    {
        return Product::query()
            ->with(['unit:id,name', 'stocks' => fn ($q) => $q->where('outlet_id', $outletId)])
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where('has_variants', false)
            ->whereNotIn('type', ['service', 'package', 'membership'])
            ->whereNotNull('min_stock')
            ->where('min_stock', '>', 0)
            ->get()
            ->filter(fn (Product $p) => Qty::cmp((string) ($p->stocks->first()?->qty ?? '0'), (string) $p->min_stock) <= 0)
            ->values();
    }

    public function shoppingList(int $outletId): array
    {
        $products = $this->lowStock($outletId);
        if ($products->isEmpty()) {
            return [];
        }

        $last = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->whereIn('purchase_items.product_id', $products->pluck('id'))
            ->where('purchases.tenant_id', $this->context->id())
            ->orderByDesc('purchases.purchased_on')
            ->orderByDesc('purchase_items.id')
            ->get(['purchase_items.product_id', 'purchases.supplier_id', 'purchase_items.unit_cost', 'purchase_items.conversion_qty'])
            ->unique('product_id')
            ->keyBy('product_id');

        $suppliers = DB::table('suppliers')
            ->where('tenant_id', $this->context->id())
            ->whereIn('id', $last->pluck('supplier_id')->filter()->unique())
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'phone'])
            ->keyBy('id');

        return $products
            ->map(function (Product $p) use ($last) {
                $stock = (float) ($p->stocks->first()?->qty ?? 0);
                $min = (float) $p->min_stock;
                $suggest = max(1, (int) ceil($min * 2 - $stock));
                $row = $last->get($p->id);
                $unitCost = $row ? (int) round($row->unit_cost / max(1, (float) $row->conversion_qty)) : (int) $p->cost_price;

                return [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'unit' => $p->unit?->name ?? '',
                    'stock' => Qty::display((string) $stock),
                    'min' => Qty::display((string) $min),
                    'suggest' => $suggest,
                    'unit_cost' => $unitCost,
                    'supplier_id' => $row?->supplier_id,
                ];
            })
            ->groupBy(fn (array $item) => $item['supplier_id'] ?? 0)
            ->map(function (Collection $items, $supplierId) use ($suppliers) {
                $supplier = $supplierId ? $suppliers->get($supplierId) : null;

                return [
                    'supplier_id' => $supplier?->id,
                    'supplier' => $supplier?->name ?? 'Belum ada pemasok',
                    'phone' => $supplier?->phone ? Phone::normalize($supplier->phone) : null,
                    'items' => $items->sortBy('name')->values()->all(),
                ];
            })
            ->sortBy(fn (array $group) => $group['supplier_id'] ? $group['supplier'] : 'zzz')
            ->values()
            ->all();
    }
}
