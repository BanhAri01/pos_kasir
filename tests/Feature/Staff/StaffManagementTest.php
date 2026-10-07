<?php

use App\Models\User;

beforeEach(function () {
    $this->owner = registerTenant();
    $this->outletId = outletsOf($this->owner)->first()->id;
});

it('pemilik bisa menambah kasir dengan PIN', function () {
    $this->actingAs($this->owner)
        ->post(route('staff.store'), [
            'name' => 'Budi',
            'role' => 'kasir',
            'job_title' => '',
            'pin' => '5678',
            'outlet_ids' => [$this->outletId],
        ])
        ->assertRedirect(route('staff.index'))
        ->assertSessionHas('success', 'Budi sudah ditambahkan.');

    $budi = User::where('name', 'Budi')->first();
    expect($budi->tenant_id)->toBe($this->owner->tenant_id)
        ->and($budi->roleLabel())->toBe('Kasir')
        ->and($budi->checkPin('5678'))->toBeTrue()
        ->and($budi->password)->toBeNull()
        ->and(outletsOf($budi)->pluck('id')->all())->toBe([$this->outletId]);
});

it('mewajibkan no HP dan kata sandi untuk manajer', function () {
    $this->actingAs($this->owner)
        ->post(route('staff.store'), [
            'name' => 'Rina',
            'role' => 'manager',
            'pin' => '5678',
            'outlet_ids' => [$this->outletId],
        ])
        ->assertSessionHasErrors([
            'phone' => 'Manajer perlu no HP untuk masuk.',
            'password' => 'Manajer perlu kata sandi untuk masuk.',
        ]);
});

it('memvalidasi PIN 4-6 angka', function (string $pin) {
    $this->actingAs($this->owner)
        ->post(route('staff.store'), [
            'name' => 'Budi', 'role' => 'kasir', 'pin' => $pin, 'outlet_ids' => [$this->outletId],
        ])
        ->assertSessionHasErrors(['pin' => 'PIN harus berupa 4 sampai 6 angka.']);
})->with(['123', '1234567', 'abcd']);

it('tidak mengganti PIN bila kolom PIN dikosongkan saat mengubah', function () {
    $kasir = addStaff($this->owner, 'kasir', '1111');

    $this->actingAs($this->owner)
        ->put(route('staff.update', $kasir), [
            'name' => 'Nama Baru', 'role' => 'kasir', 'pin' => '', 'outlet_ids' => [$this->outletId],
        ])
        ->assertSessionHasNoErrors();

    expect($kasir->fresh())->name->toBe('Nama Baru')
        ->and($kasir->fresh()->checkPin('1111'))->toBeTrue();
});

it('kasir dan karyawan tidak bisa membuka halaman karyawan', function (string $role) {
    $staff = addStaff($this->owner, $role);

    $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
    $this->actingAs($staff)->post(route('staff.store'), [])->assertForbidden();
})->with(['kasir', 'karyawan']);

it('manajer tidak bisa membuat manajer lain atau mengubah pemilik', function () {
    $manager = addStaff($this->owner, 'manager', extra: ['phone' => '6289900000001', 'password' => 'rahasia123']);

    $this->actingAs($manager)
        ->post(route('staff.store'), [
            'name' => 'Manajer 2', 'role' => 'manager', 'pin' => '1234', 'outlet_ids' => [$this->outletId],
            'phone' => '6289900000002', 'password' => 'rahasia123',
        ])
        ->assertSessionHasErrors(['role' => 'Anda tidak bisa memberi jabatan ini.']);

    $this->actingAs($manager)->get(route('staff.edit', $this->owner))->assertForbidden();
    $this->actingAs($manager)->delete(route('staff.destroy', $this->owner))->assertForbidden();
});

it('manajer bisa menambah kasir', function () {
    $manager = addStaff($this->owner, 'manager', extra: ['phone' => '6289900000001', 'password' => 'rahasia123']);

    $this->actingAs($manager)
        ->post(route('staff.store'), [
            'name' => 'Kasir Baru', 'role' => 'kasir', 'pin' => '1234', 'outlet_ids' => [$this->outletId],
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('name', 'Kasir Baru')->exists())->toBeTrue();
});

it('tidak bisa menghapus diri sendiri', function () {
    $this->actingAs($this->owner)->delete(route('staff.destroy', $this->owner))->assertForbidden();
});

it('bisa mengubah data diri sendiri tanpa mengganti jabatan', function () {
    $this->actingAs($this->owner)
        ->put(route('staff.update', $this->owner), [
            'name' => 'Bu Sri', 'pin' => '9999', 'outlet_ids' => [$this->outletId],
        ])
        ->assertSessionHasNoErrors();

    expect($this->owner->fresh())->name->toBe('Bu Sri')
        ->and($this->owner->fresh()->isOwner())->toBeTrue()
        ->and($this->owner->fresh()->checkPin('9999'))->toBeTrue();
});

it('menghapus karyawan bisa dibatalkan', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($this->owner)
        ->delete(route('staff.destroy', $kasir))
        ->assertSessionHas('undo.url', route('staff.restore', $kasir->id));
    expect($kasir->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->owner)->post(route('staff.restore', $kasir->id))->assertSessionHas('success');
    expect($kasir->fresh()->trashed())->toBeFalse();
});
