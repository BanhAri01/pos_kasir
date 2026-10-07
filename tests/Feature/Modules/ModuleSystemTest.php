<?php

use App\Core\Modules\ModuleException;
use App\Core\Modules\ModuleManager;
use App\Models\Module;
use Illuminate\Support\Facades\Route;

// "Pesan lewat QR" belum dirilis (disembunyikan), tapi tetap contoh terbaik untuk modul yang bergantung pada modul lain.
beforeEach(fn () => Module::where('code', 'qr_order')->update(['is_active' => true]));

it('menyalakan modul bawaan sesuai jenis usaha', function (string $type, array $on, array $off) {
    $tenant = registerTenant($type)->tenant;

    foreach ($on as $code) {
        expect($tenant->hasModule($code))->toBeTrue("{$type} seharusnya punya {$code}");
    }
    foreach ($off as $code) {
        expect($tenant->hasModule($code))->toBeFalse("{$type} seharusnya tidak punya {$code}");
    }
})->with([
    'toko kelontong' => ['toko_kelontong', ['barcode', 'kasbon', 'price_levels'], ['tables', 'membership']],
    'toko bangunan' => ['toko_bangunan', ['multi_unit', 'credit_sales', 'delivery'], ['kasbon', 'recipe']],
    'warung makan' => ['warung_makan', ['tables'], ['barcode']],
    'tempat cukur' => ['barbershop', ['queue', 'booking', 'staff_commission'], ['tables']],
    'gym' => ['gym', ['membership', 'staff_commission'], ['queue']],
    'laundry' => ['laundry', ['order_status', 'whatsapp_notification'], ['tables']],
    'lainnya' => ['lainnya', [], ['tables', 'barcode', 'booking']],
]);

it('menganggap modul core selalu menyala', function () {
    $tenant = registerTenant('lainnya')->tenant;

    expect($tenant->hasModule('pos'))->toBeTrue()
        ->and($tenant->hasModule('reports'))->toBeTrue();
});

it('memperbarui cache saat modul dinyalakan atau dimatikan', function () {
    $tenant = registerTenant('warung_makan')->tenant;
    $manager = app(ModuleManager::class);

    expect($tenant->hasModule('kasbon'))->toBeFalse(); // cache terisi

    asTenant($tenant, fn () => $manager->enable($tenant, 'kasbon'));
    expect($tenant->hasModule('kasbon'))->toBeTrue();

    asTenant($tenant, fn () => $manager->disable($tenant, 'kasbon'));
    expect($tenant->hasModule('kasbon'))->toBeFalse();
});

it('ikut menyalakan modul yang dibutuhkan', function () {
    $tenant = registerTenant('lainnya')->tenant;

    $also = asTenant($tenant, fn () => app(ModuleManager::class)->enable($tenant, 'qr_order'));

    expect($also)->toBe(['Meja & Bayar Belakangan'])
        ->and($tenant->hasModule('tables'))->toBeTrue()
        ->and($tenant->hasModule('qr_order'))->toBeTrue();
});

it('menolak mematikan modul yang masih dibutuhkan modul lain', function () {
    $tenant = registerTenant('warung_makan')->tenant;
    $manager = app(ModuleManager::class);
    asTenant($tenant, fn () => $manager->enable($tenant, 'qr_order'));

    asTenant($tenant, fn () => $manager->disable($tenant, 'tables'));
})->throws(ModuleException::class, 'Matikan fitur Pesan Lewat QR di Meja dulu.');

it('menolak mematikan modul core', function () {
    $tenant = registerTenant()->tenant;

    app(ModuleManager::class)->disable($tenant, 'pos');
})->throws(ModuleException::class);

it('modul satu usaha tidak memengaruhi usaha lain', function () {
    $a = registerTenant('warung_makan')->tenant;
    $b = registerTenant('warung_makan', name: 'Warung B')->tenant;

    asTenant($a, fn () => app(ModuleManager::class)->enable($a, 'kasbon'));

    expect($a->hasModule('kasbon'))->toBeTrue()
        ->and($b->hasModule('kasbon'))->toBeFalse();
});

describe('middleware module:{code}', function () {
    beforeEach(function () {
        Route::middleware(['web', 'auth', 'tenant', 'module:booking'])
            ->get('/_test/booking', fn () => 'boleh masuk');
    });

    it('mengizinkan bila modul menyala', function () {
        $this->actingAs(registerTenant('barbershop'))
            ->get('/_test/booking')
            ->assertOk()
            ->assertSee('boleh masuk');
    });

    it('mengarahkan ke beranda dengan pesan bila modul mati', function () {
        $this->actingAs(registerTenant('warung_makan'))
            ->get('/_test/booking')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Fitur Janji Temu belum dinyalakan. Nyalakan dulu di menu Lainnya, lalu Atur Fitur.');
    });

    it('memberi 403 JSON untuk permintaan API', function () {
        $this->actingAs(registerTenant('warung_makan'))
            ->getJson('/_test/booking')
            ->assertForbidden()
            ->assertJsonPath('message', 'Fitur Janji Temu belum dinyalakan. Nyalakan dulu di menu Lainnya, lalu Atur Fitur.');
    });
});

describe('halaman Atur Fitur', function () {
    it('pemilik bisa menyalakan dan mematikan fitur', function () {
        $owner = registerTenant('warung_makan');

        $this->actingAs($owner)
            ->put(route('modules.update', 'kasbon'), ['enabled' => true])
            ->assertSessionHas('success', 'Fitur Kasbon / Utang Pelanggan sudah menyala.');
        expect($owner->tenant->hasModule('kasbon'))->toBeTrue();

        $this->actingAs($owner)
            ->put(route('modules.update', 'kasbon'), ['enabled' => false])
            ->assertSessionHas('success');
        expect($owner->tenant->hasModule('kasbon'))->toBeFalse();
    });

    it('menampilkan pesan ramah bila fitur tidak bisa dimatikan', function () {
        $owner = registerTenant('warung_makan');
        $this->actingAs($owner)->put(route('modules.update', 'qr_order'), ['enabled' => true]);

        $this->actingAs($owner)
            ->put(route('modules.update', 'tables'), ['enabled' => false])
            ->assertSessionHas('error');
    });

    it('manajer dan kasir tidak bisa mengatur fitur', function (string $role) {
        $owner = registerTenant();
        $staff = addStaff($owner, $role, extra: $role === 'manager'
            ? ['phone' => '6289900000001', 'password' => 'rahasia123'] : []);

        $this->actingAs($staff)->get(route('modules.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('modules.update', 'kasbon'), ['enabled' => true])->assertForbidden();
    })->with(['manager', 'kasir']);

    it('mencatat aktivitas saat fitur diubah', function () {
        $owner = registerTenant('warung_makan');

        $this->actingAs($owner)->put(route('modules.update', 'kasbon'), ['enabled' => true]);

        $this->assertDatabaseHas('activity_log', [
            'tenant_id' => $owner->tenant_id,
            'log_name' => 'fitur',
            'description' => 'Menyalakan fitur Kasbon / Utang Pelanggan',
        ]);
    });
});

it('tidak bisa menyalakan fitur yang belum tersedia', function () {
    Module::where('code', 'qr_order')->update(['is_active' => false]);
    $tenant = registerTenant('warung_makan')->tenant;

    asTenant($tenant, fn () => app(ModuleManager::class)->enable($tenant, 'qr_order'));
})->throws(ModuleException::class);
