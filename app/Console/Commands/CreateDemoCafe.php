<?php

namespace App\Console\Commands;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Enums\Role;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Auth\Services\RegisterTenantService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\SaleService;
use App\Modules\Pos\Services\ShiftService;
use App\Modules\Staff\Services\StaffService;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDemoCafe extends Command
{
    protected $signature = 'hermes:demo-kafe {phone=081100000001 : No HP login pemilik demo} {--days=7 : Jumlah hari riwayat penjualan}';

    protected $description = 'Buat akun demo Kedai Kopi lengkap (menu, resep, pilihan, meja, karyawan, pelanggan, riwayat penjualan)';

    private const INGREDIENTS = [
        ['Biji Kopi Arabika', 'Gram', 3000, 250],
        ['Susu Segar', 'Liter', 25, 20000],
        ['Gula Aren Cair', 'Liter', 5, 60000],
        ['Bubuk Matcha', 'Gram', 500, 600],
        ['Bubuk Cokelat', 'Gram', 1000, 150],
        ['Teh Celup', 'Pcs', 100, 500],
        ['Lemon', 'Pcs', 40, 2000],
        ['Gelas Plastik', 'Pcs', 300, 700],
    ];

    private const MENU = [
        ['Kopi Susu Gula Aren', 'Kopi', 20000, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 0.15, 'Gula Aren Cair' => 0.03, 'Gelas Plastik' => 1], ['Ukuran', 'Gula', 'Es', 'Tambahan'], 10],
        ['Americano', 'Kopi', 18000, ['Biji Kopi Arabika' => 18, 'Gelas Plastik' => 1], ['Ukuran', 'Es', 'Tambahan'], 5],
        ['Cappuccino', 'Kopi', 25000, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 0.15], ['Ukuran', 'Gula', 'Tambahan'], 4],
        ['Caffe Latte', 'Kopi', 25000, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 0.2, 'Gelas Plastik' => 1], ['Ukuran', 'Gula', 'Es', 'Tambahan'], 5],
        ['Kopi Tubruk', 'Kopi', 10000, ['Biji Kopi Arabika' => 12], ['Gula'], 3],
        ['V60 Manual Brew', 'Kopi', 28000, ['Biji Kopi Arabika' => 15], [], 2],
        ['Matcha Latte', 'Non Kopi', 25000, ['Bubuk Matcha' => 10, 'Susu Segar' => 0.2, 'Gelas Plastik' => 1], ['Ukuran', 'Gula', 'Es'], 4],
        ['Cokelat', 'Non Kopi', 22000, ['Bubuk Cokelat' => 25, 'Susu Segar' => 0.2, 'Gelas Plastik' => 1], ['Ukuran', 'Gula', 'Es'], 3],
        ['Es Teh Lemon', 'Non Kopi', 12000, ['Teh Celup' => 1, 'Lemon' => 0.5, 'Gelas Plastik' => 1], ['Gula'], 4],
        ['Roti Bakar Cokelat Keju', 'Makanan', 18000, null, [], 3],
        ['Kentang Goreng', 'Makanan', 18000, null, [], 3],
        ['Pisang Goreng Keju', 'Makanan', 15000, null, [], 3],
        ['Croissant Butter', 'Makanan', 20000, 18, [], 2],
    ];

    private const FOOD_COST = [
        'Roti Bakar Cokelat Keju' => 7000,
        'Kentang Goreng' => 6500,
        'Pisang Goreng Keju' => 5500,
        'Croissant Butter' => 9000,
    ];

    private const MODIFIERS = [
        ['Ukuran', 'single', true, null, [['Reguler', 0], ['Large', 5000]]],
        ['Gula', 'single', false, null, [['Normal', 0], ['Sedikit', 0], ['Tanpa Gula', 0]]],
        ['Es', 'single', false, null, [['Dingin', 0], ['Panas', 0]]],
        ['Tambahan', 'multiple', false, 3, [['Extra Shot', 5000], ['Oat Milk', 7000], ['Sirup Vanila', 4000]]],
    ];

    private const TABLES = [
        ['Dalam', ['1', '2', '3', '4']],
        ['Teras', ['5', '6', '7']],
        ['Bar', ['8']],
    ];

    private const CUSTOMERS = ['Putu Ayu', 'Made Wirawan', 'Komang Sari', 'Kadek Dwi', 'Nyoman Arta', 'Gede Pratama', 'Luh Indah', 'Wayan Surya'];

    public function handle(RegisterTenantService $register, TenantContext $context, StockService $stock, SaleService $sales, ShiftService $shifts, StaffService $staffService): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('No HP tidak valid.');

            return self::FAILURE;
        }
        if (User::withTrashed()->where('phone', $phone)->exists()) {
            $this->error('No HP ini sudah dipakai. Akun demo tidak dibuat ulang supaya data yang ada tidak tertimpa.');

            return self::FAILURE;
        }

        $password = Str::password(12, symbols: false);
        mt_srand(20261017);

        $owner = $register->register([
            'business_type' => 'coffee_shop',
            'business_name' => 'Kopi Senja (Demo)',
            'owner_name' => 'Pemilik Kopi Senja',
            'phone' => $phone,
            'password' => $password,
        ], withSamples: false);

        $tenant = Tenant::query()->findOrFail($owner->tenant_id);
        $tenant->forceFill([
            'status' => 'active',
            'plan' => 'pro',
            'paid_until' => null,
            'qr_payment' => 'cashier',
            'onboarded_at' => now(),
            'address' => 'Jl. Contoh No. 1, Bangli, Bali',
            'settings' => [
                ...($tenant->settings ?? []),
                'wa_off' => array_keys(WhatsAppService::LABELS),
                'owner_report' => ['daily' => false, 'on_close' => false],
            ],
        ])->save();
        $tenant->flushModuleCache();

        $pins = $context->runAs($tenant, function () use ($tenant, $owner, $stock, $sales, $shifts, $staffService) {
            return DB::transaction(function () use ($tenant, $owner, $stock, $sales, $shifts, $staffService) {
                $outlet = Outlet::query()->firstOrFail();
                $outlet->update([
                    'address' => 'Jl. Contoh No. 1, Bangli, Bali',
                    'receipt_header' => 'Kopi Senja',
                    'receipt_footer' => 'Terima kasih, sampai jumpa lagi!',
                ]);

                $units = Unit::query()->pluck('id', 'name');
                $categories = collect(['Kopi', 'Non Kopi', 'Makanan', 'Bahan Baku'])
                    ->mapWithKeys(fn ($name, $i) => [$name => Category::create(['name' => $name, 'sort_order' => $i])->id]);

                $ingredients = [];
                foreach (self::INGREDIENTS as [$name, $unit, $qty, $cost]) {
                    $product = Product::create([
                        'uuid' => (string) Str::uuid(), 'name' => $name, 'category_id' => $categories['Bahan Baku'],
                        'type' => 'ingredient', 'is_packaging' => $name === 'Gelas Plastik', 'price' => 0, 'cost_price' => $cost,
                        'base_unit_id' => $units[$unit], 'pricing_mode' => 'fixed', 'track_stock' => true,
                        'min_stock' => max(1, (int) floor($qty / 5)), 'is_active' => true,
                    ]);
                    $stock->change($outlet->id, $product, (string) $qty, 'initial', unitCost: $cost, note: 'Stok awal demo', userId: $owner->id);
                    $ingredients[$name] = $product;
                }

                $groups = [];
                foreach (self::MODIFIERS as $i => [$name, $selection, $required, $max, $options]) {
                    $group = ModifierGroup::create(['name' => $name, 'selection' => $selection, 'is_required' => $required, 'max_select' => $max, 'sort_order' => $i]);
                    foreach ($options as $j => [$label, $delta]) {
                        $group->options()->create(['name' => $label, 'price_delta' => $delta, 'is_active' => true, 'sort_order' => $j]);
                    }
                    $groups[$name] = $group->load('options');
                }

                $menu = [];
                foreach (self::MENU as [$name, $category, $price, $recipe, $groupNames, $weight]) {
                    $stockQty = is_int($recipe) ? $recipe : null;
                    $cost = self::FOOD_COST[$name] ?? 0;
                    $product = Product::create([
                        'uuid' => (string) Str::uuid(), 'name' => $name, 'category_id' => $categories[$category],
                        'type' => 'goods', 'price' => $price, 'cost_price' => $cost,
                        'base_unit_id' => $units[$category === 'Makanan' ? 'Porsi' : 'Gelas'], 'pricing_mode' => 'fixed',
                        'track_stock' => $stockQty !== null, 'min_stock' => $stockQty !== null ? 5 : null, 'is_active' => true,
                    ]);
                    if ($stockQty !== null) {
                        $stock->change($outlet->id, $product, (string) ($stockQty * 6), 'initial', unitCost: $cost, note: 'Stok awal demo', userId: $owner->id);
                    }
                    foreach (is_array($recipe) ? $recipe : [] as $ingredient => $qty) {
                        $product->recipeItems()->create(['ingredient_id' => $ingredients[$ingredient]->id, 'qty' => (string) $qty]);
                    }
                    foreach ($groupNames as $groupName) {
                        $groups[$groupName]->products()->attach($product->id);
                    }
                    $menu[] = ['product' => $product, 'groups' => $groupNames, 'weight' => $weight];
                }

                $tables = [];
                foreach (self::TABLES as $i => [$area, $names]) {
                    foreach ($names as $j => $name) {
                        $tables[] = DiningTable::create(['outlet_id' => $outlet->id, 'name' => $name, 'area' => $area, 'capacity' => $area === 'Bar' ? 6 : 4, 'sort_order' => $i * 10 + $j, 'is_active' => true]);
                    }
                }

                $pins = ['Dewi (Kasir)' => (string) random_int(1000, 9999), 'Komang (Barista)' => (string) random_int(1000, 9999)];
                $cashier = $staffService->create(['name' => 'Dewi', 'job_title' => 'Kasir', 'role' => Role::Cashier->value, 'pin' => $pins['Dewi (Kasir)'], 'outlet_ids' => [$outlet->id]]);
                $staffService->create(['name' => 'Komang', 'job_title' => 'Barista', 'role' => Role::Staff->value, 'pin' => $pins['Komang (Barista)'], 'outlet_ids' => [$outlet->id]]);

                $customers = collect(self::CUSTOMERS)->map(fn ($name, $i) => Customer::create([
                    'uuid' => (string) Str::uuid(), 'name' => $name, 'phone' => '6281100001'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                ]));

                $methods = PaymentMethod::query()->pluck('id', 'type');
                $this->seedHistory($tenant, $outlet, $cashier, $menu, $groups, $tables, $customers->all(), $methods->all(), $sales, $shifts);

                return $pins;
            });
        });

        $this->info('Akun demo Kopi Senja siap. Masuk dengan:');
        $this->line('  No HP      : '.Phone::display($phone));
        $this->line('  Kata sandi : '.$password);
        foreach ($pins as $name => $pin) {
            $this->line("  PIN {$name}: {$pin}");
        }
        $this->warn('Simpan kata sandi dan PIN ini sekarang. Tidak ditampilkan lagi.');

        return self::SUCCESS;
    }

    private function seedHistory(Tenant $tenant, Outlet $outlet, User $cashier, array $menu, array $groups, array $tables, array $customers, array $methods, SaleService $sales, ShiftService $shifts): void
    {
        $days = max(1, min(30, (int) $this->option('days')));
        $pool = [];
        foreach ($menu as $index => $row) {
            array_push($pool, ...array_fill(0, $row['weight'], $index));
        }

        for ($d = $days; $d >= 1; $d--) {
            $day = Carbon::now($tenant->timezone)->subDays($d)->startOfDay();
            $opened = $day->copy()->setTime(8, 0);
            $shift = Shift::create([
                'outlet_id' => $outlet->id, 'uuid' => (string) Str::uuid(), 'user_id' => $cashier->id,
                'opened_at' => $opened, 'opening_cash' => 200000, 'status' => 'open',
            ]);

            $count = random_int(18, 30) + ($day->isWeekend() ? 10 : 0);
            $times = collect(range(1, $count))->map(fn () => $opened->copy()->addMinutes(random_int(30, 13 * 60)))->sort()->values();
            $queue = 0;

            foreach ($times as $at) {
                $items = [];
                foreach (range(1, random_int(1, 3)) as $_) {
                    $row = $menu[$pool[array_rand($pool)]];
                    $modifiers = [];
                    foreach ($row['groups'] as $groupName) {
                        $group = $groups[$groupName];
                        $options = $group->options;
                        if ($group->selection === 'single' && ($group->is_required || random_int(0, 2) > 0)) {
                            $modifiers[] = $options[random_int(0, 9) < 7 ? 0 : random_int(1, $options->count() - 1)]->id;
                        } elseif ($group->selection === 'multiple' && random_int(0, 4) === 0) {
                            $modifiers[] = $options->random()->id;
                        }
                    }
                    $items[] = ['product_id' => $row['product']->id, 'qty' => random_int(0, 5) === 0 ? 2 : 1, 'modifiers' => $modifiers];
                }

                $dineIn = random_int(0, 9) < 6;
                $payload = [
                    'uuid' => (string) Str::uuid(),
                    'shift_uuid' => $shift->uuid,
                    'customer_uuid' => random_int(0, 3) === 0 ? $customers[array_rand($customers)]->uuid : null,
                    'order_type' => $dineIn ? 'dine_in' : 'take_away',
                    'table_id' => $dineIn ? $tables[array_rand($tables)]->id : null,
                    'queue_number' => $dineIn ? null : (string) ++$queue,
                    'created_at' => $at->toIso8601String(),
                    'send_to_kitchen' => false,
                    'send_whatsapp' => false,
                    'items' => $items,
                    'payments' => [['uuid' => (string) Str::uuid(), 'payment_method_id' => $methods['cash'], 'amount' => 500000]],
                ];
                $sale = $sales->record($payload, $cashier, strict: false);
                if (random_int(0, 9) < 5) {
                    $sale->payments()->update(['payment_method_id' => $methods['qris']]);
                    $sale->update(['paid_amount' => $sale->total, 'change_amount' => 0]);
                } else {
                    $tendered = (int) (ceil($sale->total / 50000) * 50000);
                    $sale->update(['paid_amount' => $tendered, 'change_amount' => $tendered - $sale->total]);
                }
            }

            $expected = $shifts->summary($shift->fresh())['expected_cash'];
            $shift->update([
                'closed_at' => $day->copy()->setTime(21, 30), 'expected_cash' => $expected, 'counted_cash' => $expected,
                'cash_difference' => 0, 'closed_by' => $cashier->id, 'status' => 'closed',
            ]);
        }
    }
}
