<?php

namespace App\Providers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "scoped": otomatis direset di setiap request / job antrean.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(CurrentOutlet::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        // Alamat halaman ikut berbahasa Indonesia: /karyawan/tambah, /outlet/5/ubah
        Route::resourceVerbs(['create' => 'tambah', 'edit' => 'ubah']);

        // Saat development, tangkap N+1 query dan atribut yang salah ketik lebih awal.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
