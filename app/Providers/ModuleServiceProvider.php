<?php

namespace App\Providers;

use App\Models\Outlet;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Operations\Hooks\CommissionHook;
use App\Modules\Operations\Hooks\KitchenHook;
use App\Modules\Operations\Hooks\MembershipHook;
use App\Modules\Operations\Hooks\OrderStatusHook;
use App\Modules\Operations\Hooks\ReceivableHook;
use App\Modules\Outlet\Policies\OutletPolicy;
use App\Modules\Pos\Services\SaleService;
use App\Modules\WhatsApp\Hooks\ReceiptWhatsAppHook;
use App\Modules\Staff\Policies\StaffPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Memuat route, migrasi, dan policy dari setiap folder di app/Modules.
 * Modul baru cukup membuat folder dengan struktur yang sama.
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Langkah tambahan setelah penjualan tersimpan, milik masing-masing modul.
     * Modul baru cukup menambahkan class-nya di sini (urutan = urutan dijalankan).
     */
    public const SALE_HOOKS = [
        ReceivableHook::class,
        CommissionHook::class,
        MembershipHook::class,
        OrderStatusHook::class,
        KitchenHook::class,
        ReceiptWhatsAppHook::class,
    ];

    public function register(): void
    {
        $this->app->tag(self::SALE_HOOKS, 'sale.hooks');
        $this->app->when(SaleService::class)->needs('$hooks')->giveTagged('sale.hooks');
    }

    public function boot(): void
    {
        $this->registerPolicies();

        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $modulePath) {
            if (is_dir($modulePath.'/Database/Migrations')) {
                $this->loadMigrationsFrom($modulePath.'/Database/Migrations');
            }

            if (! $this->app->routesAreCached()) {
                if (is_file($modulePath.'/routes/web.php')) {
                    Route::middleware('web')->group($modulePath.'/routes/web.php');
                }

                if (is_file($modulePath.'/routes/api.php')) {
                    Route::middleware('api')->prefix('api')->group($modulePath.'/routes/api.php');
                }
            }
        }
    }

    private function registerPolicies(): void
    {
        Gate::policy(Outlet::class, OutletPolicy::class);
        Gate::policy(User::class, StaffPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
