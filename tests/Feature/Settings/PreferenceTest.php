<?php

beforeEach(function () {
    $this->owner = registerTenant();
});

it('pengguna baru memakai pengaturan awal yang ramah', function () {
    expect($this->owner->preferences)->toMatchArray([
        'display_size' => 'normal',
        'theme' => 'system',
        'sound' => true,
        'simple_mode' => true,
        'tour_done' => false,
    ]);
});

it('menampilkan halaman Tampilan & Suara untuk semua role', function (string $role) {
    $user = $role === 'owner' ? $this->owner : addStaff($this->owner, $role);

    $this->actingAs($user)
        ->get(route('preferences.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Display'));
})->with(['owner', 'kasir', 'karyawan']);

it('menyimpan sebagian pengaturan tanpa mengubah yang lain', function () {
    $this->actingAs($this->owner)
        ->put(route('preferences.update'), ['theme' => 'dark'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->owner->fresh()->preferences)->toMatchArray([
        'theme' => 'dark',
        'display_size' => 'normal',
        'sound' => true,
    ]);
});

it('kasir bisa mengubah ukuran tulisan miliknya sendiri saja', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)->put(route('preferences.update'), ['display_size' => 'sangat-besar']);

    expect($kasir->fresh()->preferences['display_size'])->toBe('sangat-besar')
        ->and($this->owner->fresh()->preferences['display_size'])->toBe('normal');
});

it('menolak nilai yang tidak dikenal dengan pesan yang jelas', function () {
    $this->actingAs($this->owner)
        ->put(route('preferences.update'), ['display_size' => 'raksasa', 'theme' => 'ungu'])
        ->assertSessionHasErrors([
            'display_size' => 'Pilih ukuran tampilan: Normal, Besar, atau Sangat Besar.',
            'theme' => 'Pilih tema: Ikuti HP, Terang, atau Gelap.',
        ]);
});

it('mengabaikan kunci yang tidak dikenal', function () {
    $this->actingAs($this->owner)->put(route('preferences.update'), ['sound' => false, 'is_super_admin' => true]);

    expect($this->owner->fresh())
        ->preferences->not->toHaveKey('is_super_admin')
        ->is_super_admin->toBeFalse();
});

it('menerapkan tema & ukuran langsung dari server (tanpa kedipan)', function () {
    $this->actingAs($this->owner)->put(route('preferences.update'), ['theme' => 'dark', 'display_size' => 'besar']);

    $this->actingAs($this->owner->fresh())
        ->get(route('dashboard'))
        ->assertSee('data-theme="dark"', false)
        ->assertSee('data-size="besar"', false);
});

it('tur pengenalan bisa diselesaikan lalu diulang', function () {
    $this->actingAs($this->owner)->put(route('preferences.update'), ['tour_done' => true]);
    expect($this->owner->fresh()->preferences['tour_done'])->toBeTrue();

    $this->actingAs($this->owner)
        ->post(route('tour.restart'))
        ->assertRedirect(route('dashboard'));
    expect($this->owner->fresh()->preferences['tour_done'])->toBeFalse();
});

it('tamu tidak bisa mengubah pengaturan', function () {
    $this->put(route('preferences.update'), ['theme' => 'dark'])->assertRedirect(route('login'));
});

it('katalog komponen tidak tersedia di luar mode development', function () {
    $this->actingAs($this->owner)->get('/dev/komponen')->assertNotFound();
});
