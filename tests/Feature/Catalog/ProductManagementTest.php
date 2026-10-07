<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
});

it('mengisi data contoh sesuai jenis usaha saat mendaftar', function (string $type, string $expectedProduct) {
    $owner = registerTenant($type, name: 'Usaha '.$type);

    $names = asTenant($owner->tenant, fn () => Product::pluck('name'));

    expect($names)->toContain($expectedProduct)
        ->and(asTenant($owner->tenant, fn () => Product::where('is_sample', false)->count()))->toBe(0);
})->with([
    ['toko_kelontong', 'Beras Premium 5 kg'],
    ['toko_bangunan', 'Semen 50 kg'],
    ['coffee_shop', 'Kopi Susu Gula Aren'],
    ['warung_makan', 'Nasi Goreng'],
    ['barbershop', 'Potong Rambut Dewasa'],
    ['laundry', 'Cuci Setrika (per kg)'],
]);

it('stok awal data contoh tercatat di riwayat stok', function () {
    $count = asTenant($this->tenant, fn () => StockMovement::where('type', 'initial')->count());

    expect($count)->toBeGreaterThan(0);
});

it('pemilik bisa menghapus semua data contoh sekaligus', function () {
    $this->actingAs($this->owner)
        ->delete(route('products.samples.destroy'))
        ->assertSessionHas('success');

    expect(asTenant($this->tenant, fn () => Product::withTrashed()->count()))->toBe(0)
        ->and(asTenant($this->tenant, fn () => Category::count()))->toBe(0);
});

it('menampilkan daftar barang dengan stok outlet aktif', function () {
    $this->actingAs($this->owner)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->where('hasSamples', true)
            ->has('products', 12)
            ->where('products.0.name', 'Air Mineral 600 ml')
            ->where('products.0.stock', '48'));
});

it('bisa mencari barang dan menyaring per kategori', function () {
    $category = asTenant($this->tenant, fn () => Category::where('name', 'Minuman')->first());

    $this->actingAs($this->owner)
        ->get(route('products.index', ['q' => 'kopi']))
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.name', 'Kopi Sachet'));

    $this->actingAs($this->owner)
        ->get(route('products.index', ['category' => $category->id]))
        ->assertInertia(fn ($page) => $page->has('products', 3));
});

it('menyaring barang yang stoknya menipis', function () {
    $telur = asTenant($this->tenant, fn () => Product::where('name', 'Telur Ayam')->first());
    asTenant($this->tenant, fn () => app(StockService::class)->change($this->outletId, $telur, '-110', 'adjust_out'));

    $this->actingAs($this->owner)
        ->get(route('products.index', ['filter' => 'low']))
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.name', 'Telur Ayam')->where('products.0.is_low_stock', true));
});

it('menambah barang baru dengan stok awal, kategori baru, dan foto', function () {
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post(route('products.store'), [
            'name' => 'Kecap Manis',
            'new_category' => 'Bumbu Dapur',
            'type' => 'goods',
            'price' => 12000,
            'cost_price' => 9500,
            'track_stock' => true,
            'initial_stock' => '24',
            'min_stock' => '5',
            'image' => UploadedFile::fake()->image('kecap.jpg', 1600, 1200),
        ])
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success', 'Kecap Manis sudah disimpan.');

    $product = asTenant($this->tenant, fn () => Product::where('name', 'Kecap Manis')->with('category')->first());

    expect($product->category->name)->toBe('Bumbu Dapur')
        ->and($product->price)->toBe(12000)
        ->and($product->is_sample)->toBeFalse()
        ->and(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $product)))->toBe('24.000');

    Storage::disk('public')->assertExists($product->image_path);
    [$width] = getimagesizefromstring(Storage::disk('public')->get($product->image_path));
    expect($width)->toBe(800); // dikecilkan supaya ringan
});

it('memberi pesan jelas bila isian barang salah', function () {
    $this->actingAs($this->owner)
        ->post(route('products.store'), ['type' => 'goods', 'price' => -5, 'initial_stock' => 'banyak'])
        ->assertSessionHasErrors([
            'name' => 'Nama barang belum diisi.',
            'price' => 'Harga jual tidak boleh minus.',
            'initial_stock' => 'Stok awal harus berupa angka. Contoh: 10 atau 2,5.',
        ]);
});

it('menolak barcode yang sama di satu usaha, tapi boleh sama di usaha lain', function () {
    $payload = ['name' => 'A', 'type' => 'goods', 'price' => 1000, 'barcode' => '8991234567890'];

    $this->actingAs($this->owner)->post(route('products.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('products.store'), [...$payload, 'name' => 'B'])
        ->assertSessionHasErrors(['barcode' => 'Barcode ini sudah dipakai barang lain. Periksa lagi barcode-nya.']);

    $other = registerTenant('toko_kelontong', name: 'Toko Lain');
    $this->actingAs($other)->post(route('products.store'), $payload)->assertSessionHasNoErrors();
});

it('mencatat perubahan harga di catatan aktivitas', function () {
    $product = asTenant($this->tenant, fn () => Product::where('name', 'Gula Pasir 1 kg')->first());

    $this->actingAs($this->owner)->put(route('products.update', $product), [
        'name' => $product->name, 'type' => 'goods', 'price' => 18000, 'track_stock' => true,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('activity_log', [
        'tenant_id' => $this->tenant->id,
        'log_name' => 'barang',
        'subject_id' => $product->id,
        'event' => 'updated',
    ]);
    expect($product->fresh()->is_sample)->toBeFalse();
});

it('menghapus barang bisa dibatalkan', function () {
    $product = asTenant($this->tenant, fn () => Product::first());

    $this->actingAs($this->owner)->delete(route('products.destroy', $product))->assertSessionHas('undo');
    expect($product->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->owner)->post(route('products.restore', $product->id));
    expect($product->fresh()->trashed())->toBeFalse();
});

it('menandai barang habis hari ini hanya untuk outlet aktif', function () {
    $product = asTenant($this->tenant, fn () => Product::first());

    $this->actingAs($this->owner)
        ->put(route('products.sold-out', $product), ['sold_out' => true])
        ->assertSessionHas('success');

    $this->actingAs($this->owner)
        ->get(route('products.index', ['q' => $product->name]))
        ->assertInertia(fn ($page) => $page->where('products.0.sold_out_today', true));
});

it('kasir tidak bisa mengelola barang', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)->get(route('products.index'))->assertForbidden();
    $this->actingAs($kasir)->post(route('products.store'), ['name' => 'X', 'type' => 'goods', 'price' => 1])->assertForbidden();
});

it('barang usaha lain tidak bisa dibuka atau diubah', function () {
    $other = registerTenant('toko_kelontong', name: 'Toko Lain');
    $foreign = asTenant($other->tenant, fn () => Product::first());

    $this->actingAs($this->owner)->get(route('products.edit', $foreign))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('products.destroy', $foreign))->assertNotFound();
});

it('pencarian barang (JSON) hanya mengembalikan barang usaha sendiri', function () {
    registerTenant('toko_kelontong', name: 'Toko Lain');

    $this->actingAs($this->owner)
        ->getJson(route('products.search', ['q' => 'Beras']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stock', '20');
});

it('kategori bisa ditambah, diganti nama, dan dihapus tanpa menghapus barang', function () {
    $this->actingAs($this->owner)->post(route('categories.store'), ['name' => 'Frozen Food'])->assertSessionHas('success');
    $category = asTenant($this->tenant, fn () => Category::where('name', 'Sembako')->first());

    $this->actingAs($this->owner)->put(route('categories.update', $category), ['name' => 'Bahan Pokok']);
    expect($category->fresh()->name)->toBe('Bahan Pokok');

    $this->actingAs($this->owner)->delete(route('categories.destroy', $category));
    expect(asTenant($this->tenant, fn () => Product::where('name', 'Beras Premium 5 kg')->value('category_id')))->toBeNull();
});
