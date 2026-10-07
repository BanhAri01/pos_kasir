<?php

it('menampilkan halaman ramah saat tidak punya izin (403)', function () {
    $kasir = addStaff(registerTenant(), 'kasir');

    $this->actingAs($kasir)
        ->get(route('staff.index'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

it('menampilkan halaman ramah saat halaman tidak ada (404)', function () {
    $this->actingAs(registerTenant())
        ->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

it('memakai pesan validasi bahasa Indonesia', function () {
    expect(__('validation.integer', ['attribute' => 'jumlah']))->toBe('Jumlah harus berupa angka bulat.');
});
