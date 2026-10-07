<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Hanya data acuan platform (aman dijalankan berulang di server produksi).
     * Akun contoh untuk mencoba: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ModuleSeeder::class,
            BusinessTypeSeeder::class,
        ]);
    }
}
