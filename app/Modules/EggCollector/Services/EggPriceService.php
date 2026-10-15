<?php

namespace App\Modules\EggCollector\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\EggCollector\Models\EggDailyPrice;
use App\Modules\EggCollector\Models\EggProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Harga Hari Ini: harga beli & jual telur per outlet per hari.
 *
 * Harga jual disimpan juga sebagai harga khusus outlet (product_outlet.price), sehingga kasir, faktur,
 * dan toko online langsung memakainya tanpa perubahan lain. Harga beli menjadi modal acuan barang
 * (cost_price) yang dipakai sebagai harga bawaan saat belanja ke peternak.
 */
class EggPriceService
{
    public function __construct(private TenantContext $context) {}

    public function today(): string
    {
        return Carbon::now($this->context->get()?->timezone ?? config('app.timezone'))->toDateString();
    }

    /** @return Collection<int, EggProduct> */
    public function eggProducts(): Collection
    {
        return EggProduct::query()
            ->whereHas('product')
            ->with('product.unit:id,name')
            ->orderByRaw("CASE WHEN role = 'mixed' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->get();
    }

    /** Harga jual yang berlaku di outlet: harga hari ini, lalu harga khusus outlet, lalu harga umum barang. */
    public function sellPrice(int $outletId, Product $product, ?string $date = null): int
    {
        return $this->sellPrices($outletId, collect([$product]), $date)[$product->id];
    }

    /** @return array<int, int> product_id => harga jual */
    public function sellPrices(int $outletId, Collection $products, ?string $date = null): array
    {
        $ids = $products->pluck('id');
        $daily = EggDailyPrice::query()->where('outlet_id', $outletId)->where('price_date', $date ?? $this->today())
            ->whereIn('product_id', $ids)->pluck('sell_price', 'product_id');
        $outlet = DB::table('product_outlet')->where('outlet_id', $outletId)->whereIn('product_id', $ids)
            ->whereNotNull('price')->pluck('price', 'product_id');

        return $products->mapWithKeys(fn (Product $p) => [$p->id => (int) ($daily[$p->id] ?? $outlet[$p->id] ?? $p->price)])->all();
    }

    /**
     * Daftar untuk halaman Harga Hari Ini: harga hari ini bila sudah diisi, kalau belum harga terakhir sebagai isian awal.
     *
     * @return list<array<string, mixed>>
     */
    public function board(int $outletId): array
    {
        $today = $this->today();
        $eggs = $this->eggProducts();
        $products = $eggs->pluck('product');
        $todayRows = EggDailyPrice::query()->where('outlet_id', $outletId)->where('price_date', $today)->get()->keyBy('product_id');
        $last = EggDailyPrice::query()->where('outlet_id', $outletId)->where('price_date', '<', $today)
            ->whereIn('product_id', $products->pluck('id'))
            ->orderByDesc('price_date')->get()->unique('product_id')->keyBy('product_id');
        $current = $this->sellPrices($outletId, $products, $today);

        return $eggs->map(function (EggProduct $egg) use ($todayRows, $last, $current) {
            $product = $egg->product;
            $row = $todayRows[$product->id] ?? null;
            $previous = $last[$product->id] ?? null;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'role' => $egg->role,
                'unit' => $product->unit?->name ?? 'Kg',
                'filled_today' => $row !== null,
                'buy_price' => $row?->buy_price ?? $previous?->buy_price ?? $product->cost_price,
                'sell_price' => $row?->sell_price ?? $current[$product->id],
                'previous' => $previous ? [
                    'date' => substr((string) $previous->price_date, 0, 10),
                    'buy_price' => $previous->buy_price,
                    'sell_price' => $previous->sell_price,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{product_id:int, buy_price?:int|null, sell_price:int}>  $rows
     */
    public function save(int $outletId, array $rows, User $user): int
    {
        $eggIds = EggProduct::query()->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $today = $this->today();

        return DB::transaction(function () use ($outletId, $rows, $user, $eggIds, $today) {
            $saved = 0;
            foreach ($rows as $row) {
                if (! in_array((int) $row['product_id'], $eggIds, true)) {
                    throw ValidationException::withMessages(['prices' => 'Barang ini bukan jenis telur.']);
                }

                $product = Product::query()->findOrFail($row['product_id']);
                $buy = isset($row['buy_price']) && $row['buy_price'] !== '' ? (int) $row['buy_price'] : null;
                $sell = (int) $row['sell_price'];

                EggDailyPrice::updateOrCreate(
                    ['outlet_id' => $outletId, 'product_id' => $product->id, 'price_date' => $today],
                    ['buy_price' => $buy, 'sell_price' => $sell, 'user_id' => $user->id],
                );

                $product->outlets()->syncWithoutDetaching([$outletId => ['price' => $sell]]);
                if ($buy !== null && $buy !== $product->cost_price) {
                    $product->update(['cost_price' => $buy]);
                }
                $saved++;
            }

            return $saved;
        });
    }
}
