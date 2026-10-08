<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

function superAdmin(): User
{
    $admin = new User;
    $admin->forceFill([
        'name' => 'Admin Hermes',
        'phone' => '6289900001111',
        'password' => Hash::make('rahasia-admin'),
        'is_super_admin' => true,
        'is_active' => true,
    ])->save();

    return $admin->fresh();
}

it('menyembunyikan panel admin dari pemilik usaha', function () {
    $owner = registerTenant();

    $this->actingAs($owner)->get(route('admin.tenants.index'))->assertNotFound();
});

it('admin masuk langsung ke panel admin', function () {
    superAdmin();

    $this->post(route('login.store'), ['phone' => '089900001111', 'password' => 'rahasia-admin'])
        ->assertRedirect(route('admin.tenants.index'));
});

it('admin bisa mencari usaha dan melihat detailnya', function () {
    $admin = superAdmin();
    $owner = registerTenant(name: 'Kedai Senja');

    $this->actingAs($admin)->get(route('admin.tenants.index', ['q' => 'Senja']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Tenants')->where('tenants.data.0.name', 'Kedai Senja'));
    $this->actingAs($admin)->get(route('admin.tenants.show', $owner->tenant_id))->assertOk();
});

it('admin memperpanjang langganan manual', function () {
    $admin = superAdmin();
    $owner = registerTenant();

    $this->actingAs($admin)->post(route('admin.tenants.extend', $owner->tenant_id), ['plan' => 'standar', 'months' => 2, 'amount' => 198000, 'note' => 'transfer'])
        ->assertSessionHas('success');

    $tenant = $owner->tenant->fresh();
    expect($tenant->status)->toBe('active')->and($tenant->planKey())->toBe('standar')->and($tenant->paid_until)->not->toBeNull();
});

it('admin membuat kata sandi baru dan pemilik bisa masuk dengan kata sandi itu', function () {
    $admin = superAdmin();
    $owner = registerTenant(phone: '6281299887766');

    $response = $this->actingAs($admin)->post(route('admin.tenants.password', $owner->tenant_id));
    $password = $response->getSession()->get('new_password');
    expect($password)->toBeString()->toHaveLength(8);

    auth()->logout();
    $this->post(route('login.store'), ['phone' => '081299887766', 'password' => $password])->assertRedirect(route('dashboard'));
});

it('admin bisa masuk sebagai pemilik lalu kembali', function () {
    $admin = superAdmin();
    $owner = registerTenant();

    $this->actingAs($admin)->post(route('admin.tenants.impersonate', $owner->tenant_id))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($owner);

    $this->post(route('admin.impersonate.leave'))->assertRedirect(route('admin.tenants.show', $owner->tenant_id));
    $this->assertAuthenticatedAs($admin);
});

it('pemilik tidak bisa memakai tombol kembali ke admin tanpa sesi admin', function () {
    $owner = registerTenant();

    $this->actingAs($owner)->post(route('admin.impersonate.leave'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($owner);
});

it('membekukan usaha mengeluarkan penggunanya', function () {
    $admin = superAdmin();
    $owner = registerTenant();

    $this->actingAs($admin)->post(route('admin.tenants.suspend', $owner->tenant_id), ['suspend' => true]);

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('login'));
});

it('perintah hermes:admin membuat akun admin', function () {
    expect(Artisan::call('hermes:admin', ['phone' => '081377778888']))->toBe(0)
        ->and(User::query()->where('phone', '6281377778888')->value('is_super_admin'))->toBeTrue();
});

it('halaman lupa kata sandi bisa dibuka tanpa masuk', function () {
    $this->get(route('password.forgot'))->assertOk();
});
