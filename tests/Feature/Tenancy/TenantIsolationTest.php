<?php

use App\Core\Tenancy\MissingTenantException;
use App\Core\Tenancy\TenantContext;
use App\Models\Device;
use App\Models\Outlet;

/*
 * Data satu usaha TIDAK BOLEH terlihat atau bisa diubah oleh usaha lain.
 */

beforeEach(function () {
    $this->ownerA = registerTenant(name: 'Warung A');
    $this->ownerB = registerTenant(name: 'Warung B');
    $this->outletA = Outlet::allTenants()->where('tenant_id', $this->ownerA->tenant_id)->first();
    $this->outletB = Outlet::allTenants()->where('tenant_id', $this->ownerB->tenant_id)->first();
});

describe('di level model', function () {
    it('hanya mengembalikan data milik tenant aktif', function () {
        $names = asTenant($this->ownerA->tenant, fn () => Outlet::pluck('name')->all());

        expect($names)->toBe(['Warung A']);
    });

    it('tidak mengembalikan data apa pun bila tidak ada tenant aktif (fail closed)', function () {
        app(TenantContext::class)->forget();

        expect(Outlet::count())->toBe(0)
            ->and(Outlet::allTenants()->count())->toBe(2);
    });

    it('tidak bisa menemukan data tenant lain lewat find()', function () {
        $found = asTenant($this->ownerA->tenant, fn () => Outlet::find($this->outletB->id));

        expect($found)->toBeNull();
    });

    it('mengisi tenant_id otomatis saat membuat data', function () {
        $outlet = asTenant($this->ownerA->tenant, fn () => Outlet::create(['name' => 'Cabang 2']));

        expect($outlet->tenant_id)->toBe($this->ownerA->tenant_id);
    });

    it('menolak membuat data tanpa tenant aktif', function () {
        app(TenantContext::class)->forget();

        Outlet::create(['name' => 'Yatim']);
    })->throws(MissingTenantException::class);

    it('tidak bisa mengubah data tenant lain lewat query update massal', function () {
        asTenant($this->ownerA->tenant, fn () => Outlet::query()->update(['name' => 'Diretas']));

        expect($this->outletB->fresh()->name)->toBe('Warung B');
    });

    it('mengembalikan tenant sebelumnya setelah runAs selesai', function () {
        $context = app(TenantContext::class);
        $context->set($this->ownerA->tenant);

        $context->runAs($this->ownerB->tenant, fn () => null);

        expect($context->id())->toBe($this->ownerA->tenant_id);
    });

    it('membatasi perangkat (device) per tenant', function () {
        asTenant($this->ownerA->tenant, fn () => Device::create(['uuid' => fake()->uuid(), 'code' => 'A']));

        expect(asTenant($this->ownerB->tenant, fn () => Device::count()))->toBe(0);
    });
});

describe('lewat halaman (HTTP)', function () {
    it('tidak menampilkan outlet usaha lain di daftar outlet', function () {
        $this->actingAs($this->ownerA)
            ->get(route('outlets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Outlets/Index')
                ->has('outlets', 1)
                ->where('outlets.0.name', 'Warung A'));
    });

    it('memberi 404 saat membuka outlet usaha lain', function () {
        $this->actingAs($this->ownerA)
            ->get(route('outlets.edit', $this->outletB))
            ->assertNotFound();
    });

    it('memberi 404 saat mengubah atau menghapus outlet usaha lain', function () {
        $this->actingAs($this->ownerA)
            ->put(route('outlets.update', $this->outletB), ['name' => 'Diretas'])
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->delete(route('outlets.destroy', $this->outletB))
            ->assertNotFound();

        expect($this->outletB->fresh())->name->toBe('Warung B')->deleted_at->toBeNull();
    });

    it('memberi 404 saat memulihkan outlet terhapus milik usaha lain', function () {
        asTenant($this->ownerB->tenant, fn () => Outlet::create(['name' => 'Cabang B2']))->delete();
        $trashed = Outlet::allTenants()->onlyTrashed()->first();

        $this->actingAs($this->ownerA)
            ->post(route('outlets.restore', $trashed->id))
            ->assertNotFound();
    });

    it('tidak menampilkan karyawan usaha lain', function () {
        addStaff($this->ownerB, 'kasir');

        $this->actingAs($this->ownerA)
            ->get(route('staff.index'))
            ->assertInertia(fn ($page) => $page->has('staff', 1)); // hanya pemilik A sendiri
    });

    it('memberi 404 saat membuka atau menghapus karyawan usaha lain', function () {
        $kasirB = addStaff($this->ownerB, 'kasir');

        $this->actingAs($this->ownerA)->get(route('staff.edit', $kasirB))->assertNotFound();
        $this->actingAs($this->ownerA)->delete(route('staff.destroy', $kasirB))->assertNotFound();

        expect($kasirB->fresh()->deleted_at)->toBeNull();
    });

    it('tidak bisa menugaskan karyawan ke outlet usaha lain', function () {
        $this->actingAs($this->ownerA)
            ->post(route('staff.store'), [
                'name' => 'Penyusup',
                'role' => 'kasir',
                'pin' => '1234',
                'outlet_ids' => [$this->outletB->id],
            ])
            ->assertSessionHasErrors('outlet_ids.0');
    });
});
