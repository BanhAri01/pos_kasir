<?php

namespace App\Modules\Auth\Services;

use App\Core\Modules\ModuleManager;
use App\Core\Tenancy\TenantContext;
use App\Enums\Role;
use App\Models\BusinessType;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalog\Services\SampleDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat usaha baru lengkap: tenant, akun pemilik, outlet pertama, role, dan modul bawaan.
 */
class RegisterTenantService
{
    public function __construct(
        private ModuleManager $modules,
        private TenantContext $context,
        private SampleDataService $samples,
    ) {}

    /**
     * @param  array{business_type: string, business_name: string, owner_name: string, phone: string, password: string}  $data
     * @param  bool  $withSamples  isi data contoh (barang & kategori) supaya bisa langsung dicoba
     */
    public function register(array $data, bool $withSamples = true): User
    {
        return DB::transaction(function () use ($data, $withSamples) {
            $type = BusinessType::query()
                ->where('code', $data['business_type'])
                ->where('is_active', true)
                ->firstOrFail();

            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'business_type_id' => $type->id,
                'phone' => $data['phone'],
                'timezone' => 'Asia/Jakarta',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(config('hermes.trial_days')),
            ]);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'is_active' => true,
                'preferences' => self::defaultPreferences(),
            ]);
            $owner->assignRole(Role::Owner->value);

            $tenant->update(['owner_id' => $owner->id]);

            $outlet = $this->context->runAs($tenant, function () use ($tenant, $owner) {
                // Kebanyakan UMKM hanya punya satu tempat usaha, jadi outlet pertama
                // diberi nama yang sama dengan nama usaha.
                $outlet = Outlet::create(['name' => $tenant->name, 'is_active' => true]);
                $owner->outlets()->attach($outlet);

                return $outlet;
            });

            $this->modules->enableDefaults($tenant);

            $this->samples->seedDefaults($tenant);
            if ($withSamples) {
                $this->samples->seedSamples($tenant, $outlet);
            }

            return $owner;
        });
    }

    /** Pengaturan awal: Mode Sederhana menyala, suara menyala, ukuran normal, tema ikut HP. */
    public static function defaultPreferences(): array
    {
        return [
            'display_size' => 'normal',
            'theme' => 'system',
            'simple_mode' => true,
            'sound' => true,
            'tour_done' => false,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'usaha';

        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Tenant::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
