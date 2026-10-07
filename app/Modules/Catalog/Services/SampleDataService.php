<?php

namespace App\Modules\Catalog\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Warehouse\Services\ProductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengisi satuan bawaan + data contoh sesuai jenis usaha (database/templates/business_types.php).
 */
class SampleDataService
{
    public function __construct(
        private TenantContext $context,
        private StockService $stock,
    ) {}

    /** Satuan & metode pembayaran bawaan untuk setiap usaha (selalu diisi). */
    public function seedDefaults(Tenant $tenant): void
    {
        $this->context->runAs($tenant, function () {
            foreach (Unit::DEFAULTS as [$name, $symbol, $decimal]) {
                Unit::firstOrCreate(['name' => $name], ['symbol' => $symbol, 'allow_decimal' => $decimal]);
            }

            foreach (PaymentMethod::DEFAULTS as $i => [$name, $type]) {
                PaymentMethod::firstOrCreate(['type' => $type, 'name' => $name], ['sort_order' => $i, 'is_active' => true]);
            }
        });
    }

    public function seedSamples(Tenant $tenant, Outlet $outlet): void
    {
        $template = $this->template($tenant->businessType->code);

        $this->context->runAs($tenant, function () use ($template, $outlet) {
            DB::transaction(function () use ($template, $outlet) {
                $units = Unit::pluck('id', 'name');

                $categories = collect($template['categories'])->mapWithKeys(fn ($name, $i) => [
                    $name => Category::create(['name' => $name, 'sort_order' => $i])->id,
                ]);

                foreach ($template['price_levels'] ?? [] as $i => $level) {
                    PriceLevel::firstOrCreate(['name' => $level], ['sort_order' => $i]);
                }

                foreach ($template['products'] as $row) {
                    [$name, $category, $price, $cost, $unit, $stock, $type] = $row;
                    $duration = $row[7] ?? null;
                    $pricingMode = $row[8] ?? 'fixed';
                    $packSize = $row[9] ?? null;

                    $product = Product::create([
                        'uuid' => (string) Str::uuid(),
                        'name' => $name,
                        'category_id' => $categories[$category] ?? null,
                        // Bahan kemas = bahan baku yang ditandai (karung kosong, benang, label).
                        'type' => $type === 'packaging' ? 'ingredient' : $type,
                        'is_packaging' => $type === 'packaging',
                        'track_batch' => in_array($name, $template['track_batch'] ?? [], true),
                        'price' => $price,
                        'cost_price' => $cost,
                        'base_unit_id' => $units[$unit] ?? null,
                        'pricing_mode' => $pricingMode,
                        'track_stock' => $stock !== null,
                        'min_stock' => $stock !== null ? max(1, (int) floor($stock / 5)) : null,
                        'pack_size' => $packSize,
                        'pack_name' => $packSize ? ($row[10] ?? 'karung') : null,
                        'pack_weight_fixed' => $packSize && in_array($name, $template['fixed_packs'] ?? [], true),
                        'duration_minutes' => $duration,
                        'is_sample' => true,
                        'is_active' => true,
                    ]);

                    if ($stock !== null) {
                        $this->stock->change($outlet->id, $product, (string) $stock, 'initial', unitCost: $cost, note: 'Data contoh');
                    }
                }

                foreach ($template['variant_models'] ?? [] as [$modelName, $category, $price, $cost, $options, $stockEach]) {
                    $model = Product::create([
                        'uuid' => (string) Str::uuid(), 'name' => $modelName, 'category_id' => $categories[$category] ?? null,
                        'type' => 'goods', 'price' => $price, 'cost_price' => $cost, 'base_unit_id' => $units['Pcs'] ?? null,
                        'pricing_mode' => 'fixed', 'track_stock' => true, 'is_sample' => true, 'is_active' => true,
                    ]);
                    $options = collect($options)->map(fn ($values, $name) => ['name' => $name, 'values' => $values])->values()->all();
                    $rows = array_map(fn ($values) => ['values' => $values, 'price' => $price, 'min_stock' => '1', 'initial_stock' => (string) $stockEach], VariantService::combinations($options));
                    app(VariantService::class)->sync($model, $options, $rows, $outlet->id, autoBarcode: true);
                    $model->variants()->update(['is_sample' => true]);
                }

                $products = Product::query()->where('is_sample', true)->pluck('id', 'name');
                foreach ($template['formulas'] ?? [] as [$kind, $formulaName, $output, $outputQty, $items]) {
                    app(ProductionService::class)->saveFormula([
                        'kind' => $kind,
                        'name' => $formulaName,
                        'output_product_id' => $products[$output],
                        'output_qty' => (string) $outputQty,
                        'items' => array_map(fn ($row) => ['product_id' => $products[$row[0]], 'qty' => (string) $row[1]], $items),
                    ]);
                }
            });
        });
    }

    /** Hapus semua data contoh (barang & kategori yang kosong). */
    public function removeSamples(): int
    {
        return DB::transaction(function () {
            $count = Product::where('is_sample', true)->count();
            Product::where('is_sample', true)->get()->each->forceDelete();

            Category::query()->whereDoesntHave('products', fn ($q) => $q->withTrashed())->delete();

            return $count;
        });
    }

    public function hasSamples(): bool
    {
        return Product::where('is_sample', true)->exists();
    }

    private function template(string $businessType): array
    {
        $templates = require database_path('templates/business_types.php');

        return $templates[$businessType] ?? $templates['lainnya'];
    }
}
