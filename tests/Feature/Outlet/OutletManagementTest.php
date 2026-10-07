<?php

use App\Models\Outlet;

beforeEach(function () {
    $this->owner = registerTenant();
});

it('pemilik bisa menambah outlet dan otomatis bertugas di sana', function () {
    $this->actingAs($this->owner)
        ->post(route('outlets.store'), ['name' => 'Cabang Pasar', 'address' => 'Jl. Pasar 1', 'phone' => '0812 5555 6666'])
        ->assertRedirect(route('outlets.index'))
        ->assertSessionHas('success', 'Outlet Cabang Pasar sudah disimpan.');

    $outlet = asTenant($this->owner->tenant, fn () => Outlet::where('name', 'Cabang Pasar')->first());
    expect($outlet->phone)->toBe('6281255556666')
        ->and($outlet->users()->pluck('users.id')->all())->toContain($this->owner->id);
});

it('pemilik bisa mengubah outlet', function () {
    $outlet = outletsOf($this->owner)->first();

    $this->actingAs($this->owner)
        ->put(route('outlets.update', $outlet), ['name' => 'Warung Bu Sri Pusat'])
        ->assertSessionHasNoErrors();

    expect($outlet->fresh()->name)->toBe('Warung Bu Sri Pusat');
});

it('tidak bisa menghapus satu-satunya outlet', function () {
    $outlet = outletsOf($this->owner)->first();

    $this->actingAs($this->owner)
        ->delete(route('outlets.destroy', $outlet))
        ->assertSessionHasErrors('outlet');

    expect($outlet->fresh()->trashed())->toBeFalse();
});

it('menghapus outlet bisa dibatalkan', function () {
    $second = asTenant($this->owner->tenant, fn () => Outlet::create(['name' => 'Cabang 2']));

    $this->actingAs($this->owner)->delete(route('outlets.destroy', $second))->assertSessionHas('undo');
    expect($second->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->owner)->post(route('outlets.restore', $second->id));
    expect($second->fresh()->trashed())->toBeFalse();
});

it('kasir tidak bisa mengatur outlet', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)->get(route('outlets.index'))->assertForbidden();
    $this->actingAs($kasir)->post(route('outlets.store'), ['name' => 'X'])->assertForbidden();
});

it('mencatat perubahan outlet di catatan aktivitas', function () {
    $outlet = outletsOf($this->owner)->first();

    $this->actingAs($this->owner)->put(route('outlets.update', $outlet), ['name' => 'Nama Baru']);

    $this->assertDatabaseHas('activity_log', [
        'tenant_id' => $this->owner->tenant_id,
        'log_name' => 'outlet',
        'subject_id' => $outlet->id,
        'event' => 'updated',
    ]);
});
