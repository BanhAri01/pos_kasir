<?php

use App\Models\Device;
use App\Modules\Auth\Services\DeviceService;

/** Simulasikan HP toko yang sudah pernah dipakai login pemilik. */
function rememberDeviceFor($test, $owner): string
{
    $test->post(route('login.store'), ['phone' => $owner->phone, 'password' => 'rahasia123']);
    $uuid = Device::allTenants()->where('tenant_id', $owner->tenant_id)->value('uuid');
    $test->post(route('logout'));

    return $uuid;
}

it('meminta pemilik masuk dulu bila HP belum dikenal', function () {
    $this->get(route('pin.create'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('info');
});

it('menampilkan daftar karyawan ber-PIN di HP yang sudah dikenal', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner, 'kasir', '1234');
    addStaff(registerTenant(name: 'Usaha Lain'), 'kasir'); // tidak boleh ikut tampil
    $uuid = rememberDeviceFor($this, $owner);

    $this->withCookie(DeviceService::COOKIE, $uuid)
        ->get(route('pin.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/PinLogin')
            ->where('businessName', 'Warung Bu Sri')
            ->has('staff', 1)
            ->where('staff.0.id', $kasir->id));
});

it('bisa masuk dengan PIN yang benar, kasir langsung ke layar kasir', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner, 'kasir', '4321');
    $uuid = rememberDeviceFor($this, $owner);

    $this->withCookie(DeviceService::COOKIE, $uuid)
        ->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '4321'])
        ->assertRedirect(route('pos.show'));

    $this->assertAuthenticatedAs($kasir);
});

it('karyawan non-kasir masuk ke beranda', function () {
    $owner = registerTenant();
    $barista = addStaff($owner, 'karyawan', '5678');
    $uuid = rememberDeviceFor($this, $owner);

    $this->withCookie(DeviceService::COOKIE, $uuid)
        ->post(route('pin.store'), ['user_id' => $barista->id, 'pin' => '5678'])
        ->assertRedirect(route('dashboard'));
});

it('menolak PIN salah dengan pesan yang jelas', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner, 'kasir', '4321');
    $uuid = rememberDeviceFor($this, $owner);

    $this->withCookie(DeviceService::COOKIE, $uuid)
        ->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '0000'])
        ->assertSessionHasErrors(['pin' => 'PIN salah. Coba lagi, atau tanyakan PIN ke pemilik usaha.']);

    $this->assertGuest();
});

it('tidak bisa masuk sebagai karyawan usaha lain lewat HP ini', function () {
    $owner = registerTenant();
    $kasirLain = addStaff(registerTenant(name: 'Usaha Lain'), 'kasir', '1234');
    $uuid = rememberDeviceFor($this, $owner);

    $this->withCookie(DeviceService::COOKIE, $uuid)
        ->post(route('pin.store'), ['user_id' => $kasirLain->id, 'pin' => '1234'])
        ->assertSessionHasErrors('pin');

    $this->assertGuest();
});

it('meminta menunggu setelah PIN salah 5 kali', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner, 'kasir', '4321');
    $uuid = rememberDeviceFor($this, $owner);

    foreach (range(1, 5) as $i) {
        $this->withCookie(DeviceService::COOKIE, $uuid)
            ->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '0000']);
    }

    $this->withCookie(DeviceService::COOKIE, $uuid)
        ->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '4321'])
        ->assertSessionHasErrors('pin');

    $this->assertGuest();
});

it('mengarahkan ke layar PIN saat Ganti Pengguna', function () {
    $owner = registerTenant();

    $this->actingAs($owner)->post(route('switch-user'))->assertRedirect(route('pin.create'));

    $this->assertGuest();
});
