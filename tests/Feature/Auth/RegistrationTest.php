<?php

use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Auth\Services\RegisterTenantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('menampilkan halaman daftar dengan jenis usaha per kategori', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Register')
            ->has('categories', 3)
            ->where('categories.0.label', 'Jual Barang'));
});

it('membuat usaha lengkap saat mendaftar', function () {
    $response = $this->post(route('register.store'), [
        'business_type' => 'coffee_shop',
        'business_name' => 'Kopi Kampung',
        'owner_name' => 'Pak Darto',
        'phone' => '0812-3456-7890',
        'password' => 'rahasia123',
    ]);

    $response->assertRedirect(route('dashboard'))->assertSessionHas('success');

    $owner = User::where('phone', '6281234567890')->first();
    $tenant = $owner->tenant;

    expect($owner)->not->toBeNull()
        ->and($owner->isOwner())->toBeTrue()
        ->and($owner->preferences['simple_mode'])->toBeTrue()
        ->and($tenant->name)->toBe('Kopi Kampung')
        ->and($tenant->owner_id)->toBe($owner->id)
        ->and($tenant->status)->toBe('trial')
        ->and($tenant->trialDaysLeft())->toBe(14)
        ->and($tenant->timezone)->toBe('Asia/Jakarta');

    // Outlet pertama otomatis dibuat dengan nama usaha, pemilik ditugaskan di sana.
    $outlet = Outlet::allTenants()->where('tenant_id', $tenant->id)->sole();
    expect($outlet->name)->toBe('Kopi Kampung')
        ->and(outletsOf($owner)->pluck('id')->all())->toBe([$outlet->id]);

    // Modul bawaan Kedai Kopi menyala.
    expect($tenant->hasModule('tables'))->toBeTrue()
        ->and($tenant->hasModule('recipe'))->toBeTrue()
        ->and($tenant->hasModule('kasbon'))->toBeFalse();

    $this->assertAuthenticatedAs($owner);
    $response->assertCookie('hermes_device');
});

it('menerima berbagai format no HP Indonesia', function (string $input) {
    $this->post(route('register.store'), [
        'business_type' => 'laundry',
        'business_name' => 'Laundry Bersih',
        'owner_name' => 'Bu Ani',
        'phone' => $input,
        'password' => 'rahasia123',
    ])->assertSessionHasNoErrors();

    expect(User::where('phone', '6285712345678')->exists())->toBeTrue();
})->with(['085712345678', '+62 857-1234-5678', '6285712345678', '0857 1234 5678']);

it('menolak no HP yang sudah terdaftar dengan pesan yang jelas', function () {
    registerTenant(phone: '6281111111111');

    $this->post(route('register.store'), [
        'business_type' => 'laundry',
        'business_name' => 'Laundry Lain',
        'owner_name' => 'Bu Ani',
        'phone' => '081111111111',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors([
        'phone' => 'No HP ini sudah terdaftar. Silakan masuk, atau pakai no HP lain.',
    ]);

    expect(Tenant::count())->toBe(1);
});

it('memberi pesan bahasa sehari-hari bila isian kosong atau salah', function () {
    $this->post(route('register.store'), ['phone' => '123'])
        ->assertSessionHasErrors([
            'business_type' => 'Pilih dulu jenis usaha Anda.',
            'business_name' => 'Nama usaha belum diisi.',
            'owner_name' => 'Nama Anda belum diisi.',
            'phone' => 'No HP sepertinya salah. Contoh yang benar: 0812 3456 7890.',
            'password' => 'Kata sandi belum diisi.',
        ]);
});

it('tidak menyimpan apa pun bila pendaftaran gagal di tengah jalan', function () {
    // Jenis usaha tidak ada -> transaksi dibatalkan seluruhnya.
    expect(fn () => app(RegisterTenantService::class)->register([
        'business_type' => 'tidak_ada',
        'business_name' => 'X',
        'owner_name' => 'Y',
        'phone' => '6281299999999',
        'password' => 'rahasia123',
    ]))->toThrow(ModelNotFoundException::class);

    expect(Tenant::count())->toBe(0)->and(User::count())->toBe(0);
});
