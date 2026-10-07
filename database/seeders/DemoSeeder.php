<?php

namespace Database\Seeders;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Auth\Services\RegisterTenantService;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Database\Seeder;

/**
 * Akun contoh untuk mencoba aplikasi (JANGAN dijalankan di server produksi).
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Pemilik : 0812 3456 7890 / rahasia123
 * Manajer : 0812 3456 7891 / rahasia123
 * Kasir   : PIN 1234   ·   Barista : PIN 5678
 */
class DemoSeeder extends Seeder
{
    public function run(RegisterTenantService $register, StaffService $staff, TenantContext $context): void
    {
        if (User::where('phone', '6281234567890')->exists()) {
            $this->command?->warn('Akun contoh sudah ada, dilewati.');

            return;
        }

        $owner = $register->register([
            'business_type' => 'coffee_shop',
            'business_name' => 'Kedai Kopi Bu Sri',
            'owner_name' => 'Sri Wahyuni',
            'phone' => '6281234567890',
            'password' => 'rahasia123',
        ]);
        $owner->setPin('9999');
        $owner->save();

        $context->runAs($owner->tenant, function () use ($owner, $staff) {
            $outletIds = $owner->outlets()->pluck('outlets.id')->all();

            $staff->create([
                'name' => 'Rina', 'role' => 'manager', 'pin' => '4321', 'outlet_ids' => $outletIds,
                'phone' => '6281234567891', 'password' => 'rahasia123',
            ]);
            $staff->create(['name' => 'Budi', 'role' => 'kasir', 'pin' => '1234', 'outlet_ids' => $outletIds]);
            $staff->create([
                'name' => 'Dewi', 'role' => 'karyawan', 'job_title' => 'Barista', 'pin' => '5678', 'outlet_ids' => $outletIds,
            ]);
        });

        $this->command?->info('Akun contoh dibuat. Pemilik: 0812 3456 7890 / rahasia123');
    }
}
