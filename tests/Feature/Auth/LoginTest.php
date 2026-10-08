<?php

use App\Models\Device;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('login:6281234567890|127.0.0.1'));

it('bisa masuk dengan no HP dalam format apa pun', function () {
    $owner = registerTenant(phone: '6281234567890');

    $this->post(route('login.store'), ['phone' => '0812 3456 7890', 'password' => 'rahasia123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($owner);
});

it('menolak kata sandi salah dengan pesan yang jelas', function () {
    registerTenant(phone: '6281234567890');

    $this->post(route('login.store'), ['phone' => '081234567890', 'password' => 'salah'])
        ->assertSessionHasErrors(['password' => 'No HP atau kata sandi salah. Periksa lagi, lalu coba masuk.']);

    $this->assertGuest();
});

it('meminta menunggu setelah 5 kali salah', function () {
    registerTenant(phone: '6281234567890');

    foreach (range(1, 5) as $i) {
        $this->post(route('login.store'), ['phone' => '081234567890', 'password' => 'salah']);
    }

    $this->post(route('login.store'), ['phone' => '081234567890', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('phone');

    $this->assertGuest();
});

it('mengingat HP toko saat pemilik masuk', function () {
    $owner = registerTenant(phone: '6281234567890');

    $this->post(route('login.store'), ['phone' => '081234567890', 'password' => 'rahasia123'])
        ->assertCookie('hermes_device');

    expect(asTenant($owner->tenant, fn () => Device::count()))->toBe(1);
});

it('tidak mengeluarkan akun yang dinonaktifkan dari halaman dalam', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner, 'kasir');
    $kasir->update(['is_active' => false]);

    $this->actingAs($kasir)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

it('mengarahkan tamu ke halaman masuk', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('menampilkan halaman depan dengan daftar harga untuk tamu', function () {
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Landing')->has('catalog.plans', 3));
});

it('bisa keluar', function () {
    $owner = registerTenant();

    $this->actingAs($owner)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});
