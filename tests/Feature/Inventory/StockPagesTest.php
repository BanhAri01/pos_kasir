<?php

use App\Models\Outlet;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\StockService;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->beras = asTenant($this->tenant, fn () => Product::where('name', 'Beras Premium 5 kg')->first());
});

it('menampilkan stok dengan yang habis/menipis di paling atas', function () {
    asTenant($this->tenant, fn () => app(StockService::class)->change($this->outletId, $this->beras, '-20', 'adjust_out'));

    $this->actingAs($this->owner)
        ->get(route('stock.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Stock/Index')
            ->where('products.0.name', 'Beras Premium 5 kg')
            ->where('products.0.is_out_of_stock', true)
            ->where('summary.out', 1));
});

it('mencatat stok masuk dengan alasan', function () {
    $this->actingAs($this->owner)
        ->post(route('stock.adjust.store'), [
            'type' => 'in',
            'reason' => 'barang_datang',
            'items' => [['product_id' => $this->beras->id, 'qty' => '10', 'unit_cost' => 66000]],
        ])
        ->assertRedirect(route('stock.index'))
        ->assertSessionHas('success', 'Stok masuk sudah dicatat (1 barang).');

    expect(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('30.000');
});

it('mencatat stok keluar (rusak) dan menolak jumlah 0', function () {
    $this->actingAs($this->owner)
        ->post(route('stock.adjust.store'), [
            'type' => 'out', 'reason' => 'rusak', 'items' => [['product_id' => $this->beras->id, 'qty' => '0']],
        ])
        ->assertSessionHasErrors(['items.0.qty' => 'Jumlah harus lebih dari 0.']);

    $this->actingAs($this->owner)
        ->post(route('stock.adjust.store'), [
            'type' => 'out', 'reason' => 'rusak', 'items' => [['product_id' => $this->beras->id, 'qty' => '2']],
        ])
        ->assertSessionHasNoErrors();

    expect(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('18.000');
});

it('menyimpan hasil hitung stok', function () {
    $this->actingAs($this->owner)
        ->post(route('stock.opname.store'), [
            'items' => [['product_id' => $this->beras->id, 'counted_qty' => '17']],
        ])
        ->assertSessionHas('success');

    expect(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('17.000');
});

it('menampilkan riwayat stok per barang', function () {
    $this->actingAs($this->owner)
        ->get(route('stock.history', $this->beras))
        ->assertInertia(fn ($page) => $page
            ->component('Stock/History')
            ->where('movements.0.label', 'Stok awal')
            ->where('movements.0.qty_change', '20'));
});

it('kirim stok ke cabang lewat halaman', function () {
    $second = asTenant($this->tenant, fn () => Outlet::create(['name' => 'Cabang Pasar']));

    $this->actingAs($this->owner)
        ->post(route('stock.transfers.store'), [
            'to_outlet_id' => $second->id,
            'items' => [['product_id' => $this->beras->id, 'qty' => '5']],
        ])
        ->assertRedirect(route('stock.transfers'));

    $transfer = asTenant($this->tenant, fn () => StockTransfer::first());
    $this->actingAs($this->owner)->post(route('stock.transfers.receive', $transfer))->assertSessionHas('success');

    expect(asTenant($this->tenant, fn () => app(StockService::class)->qty($second->id, $this->beras)))->toBe('5.000');
});

it('tidak bisa mencatat stok untuk barang usaha lain', function () {
    $other = registerTenant('toko_kelontong', name: 'Toko Lain');
    $foreign = asTenant($other->tenant, fn () => Product::first());

    $this->actingAs($this->owner)
        ->post(route('stock.adjust.store'), [
            'type' => 'in', 'reason' => 'barang_datang', 'items' => [['product_id' => $foreign->id, 'qty' => '5']],
        ])
        ->assertSessionHasErrors('items.0.product_id');
});

it('kasir tidak bisa membuka halaman stok', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)->get(route('stock.index'))->assertForbidden();
});

it('pemilik bisa berganti outlet aktif, kasir hanya outlet tugasnya', function () {
    $second = asTenant($this->tenant, fn () => Outlet::create(['name' => 'Cabang Pasar']));

    $this->actingAs($this->owner)->post(route('outlets.switch'), ['outlet_id' => $second->id])->assertSessionHas('success');
    $this->actingAs($this->owner)->get(route('stock.index'))
        ->assertInertia(fn ($page) => $page->where('outlet.current.id', $second->id));

    $kasir = addStaff($this->owner, 'kasir'); // hanya di outlet pertama
    $this->actingAs($kasir)->post(route('outlets.switch'), ['outlet_id' => $second->id])->assertSessionHas('error');
});
