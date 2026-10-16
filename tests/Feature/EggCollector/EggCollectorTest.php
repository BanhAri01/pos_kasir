<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\EggCollector\Models\EggDailyPrice;
use App\Modules\EggCollector\Models\EggProduct;
use App\Modules\EggCollector\Models\EggSorting;
use App\Modules\EggCollector\Services\EggSortingService;
use App\Modules\Inventory\Models\Stock;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = registerTenant('pengepul_telur', name: 'Pengepul Telur Jaya');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->p = fn (string $name) => asTenant($this->tenant, fn () => Product::where('name', $name)->first());
    $this->stockOf = fn (string $name) => asTenant($this->tenant, fn () => Stock::where('outlet_id', $this->outletId)->where('product_id', ($this->p)($name)->id)->first());
    $this->sortPayload = fn (array $outputs, string $input = '40') => [
        'input_qty' => $input,
        'outputs' => collect($outputs)->map(fn ($qty, $name) => ['product_id' => ($this->p)($name)->id, 'qty' => $qty])->values()->all(),
    ];
});

it('menyiapkan jenis usaha pengepul telur lengkap dengan modul dan jenis telur', function () {
    expect($this->tenant->hasModule('egg_collector'))->toBeTrue()
        ->and($this->tenant->hasModule('supplier_purchase'))->toBeTrue()
        ->and($this->tenant->hasModule('kasbon'))->toBeTrue();

    $roles = asTenant($this->tenant, fn () => EggProduct::with('product')->get()->mapWithKeys(fn ($e) => [$e->product->name => $e->role])->all());
    expect($roles)->toBe(['Telur Campur' => 'mixed', 'Telur Super' => 'grade', 'Telur Besar' => 'grade', 'Telur Sedang' => 'grade', 'Telur Kecil' => 'grade', 'Telur Retak' => 'grade'])
        ->and(($this->stockOf)('Telur Campur')->qty)->toBe('100.000');
});

it('harga hari ini langsung dipakai kasir dan menjadi modal acuan belanja', function () {
    $besar = ($this->p)('Telur Besar');
    $campur = ($this->p)('Telur Campur');

    $this->actingAs($this->owner)->post(route('egg.prices.store'), ['prices' => [
        ['product_id' => $campur->id, 'buy_price' => 23500, 'sell_price' => 26500],
        ['product_id' => $besar->id, 'buy_price' => null, 'sell_price' => 29000],
    ]])->assertSessionHasNoErrors()->assertRedirect();

    expect(asTenant($this->tenant, fn () => EggDailyPrice::count()))->toBe(2)
        ->and(DB::table('product_outlet')->where('outlet_id', $this->outletId)->where('product_id', $besar->id)->value('price'))->toBe(29000)
        ->and(($this->p)('Telur Campur')->cost_price)->toBe(23500)
        ->and(($this->p)('Telur Besar')->cost_price)->toBe(24000);

    $this->actingAs($this->owner)->get(route('egg.prices'))->assertInertia(fn (Assert $page) => $page
        ->component('Pengepul/Prices')
        ->where('rows.0.name', 'Telur Campur')
        ->where('rows.0.filled_today', true)
        ->where('rows.0.buy_price', 23500)
        ->where('rows.2.sell_price', 29000));

    $this->actingAs($this->owner)->post(route('egg.prices.store'), ['prices' => [['product_id' => $besar->id, 'sell_price' => 28500]]])->assertSessionHasNoErrors();
    expect(asTenant($this->tenant, fn () => EggDailyPrice::where('product_id', $besar->id)->count()))->toBe(1)
        ->and(DB::table('product_outlet')->where('product_id', $besar->id)->value('price'))->toBe(28500);
});

it('sortir membagi modal menurut nilai jual dan mencatat susut sebagai rugi', function () {
    $this->actingAs($this->owner)
        ->post(route('egg.sortings.store'), ($this->sortPayload)(['Telur Besar' => '30', 'Telur Kecil' => '8', 'Telur Retak' => '1,5']))
        ->assertSessionHasNoErrors()->assertRedirect(route('egg.sortings.create'));

    $sorting = asTenant($this->tenant, fn () => EggSorting::with('items.product')->first());
    // 40 kg x 24.000 = 960.000; susut 0,5 kg = 12.000; sisa 948.000 dibagi 840.000 : 200.000 : 30.000
    expect($sorting->input_cost)->toBe(960_000)
        ->and($sorting->shrinkage_qty)->toBe('0.500')
        ->and($sorting->shrinkage_cost)->toBe(12_000)
        ->and($sorting->items->sum('total_cost'))->toBe(948_000)
        ->and($sorting->items->mapWithKeys(fn ($i) => [$i->product->name => $i->total_cost])->all())
        ->toBe(['Telur Besar' => 744_224, 'Telur Kecil' => 177_196, 'Telur Retak' => 26_580])
        ->and($sorting->items->firstWhere('product.name', 'Telur Retak')->unit_cost)->toBe(17_720);

    expect(($this->stockOf)('Telur Campur')->qty)->toBe('60.000')
        ->and(($this->stockOf)('Telur Besar')->qty)->toBe('30.000')
        ->and(($this->stockOf)('Telur Besar')->avg_cost)->toBe(24_807)
        ->and(($this->stockOf)('Telur Retak')->qty)->toBe('1.500');

    $this->actingAs($this->owner)->get(route('egg.sortings.show', $sorting->uuid))->assertInertia(fn (Assert $page) => $page
        ->component('Pengepul/SortingShow')
        ->where('sorting.shrinkage_qty', '0,5')
        ->where('sorting.shrinkage_cost', 12_000));
});

it('menolak hasil sortir yang lebih berat dari telur campur, minus, atau bukan jenis telur', function () {
    $this->actingAs($this->owner)->post(route('egg.sortings.store'), ($this->sortPayload)(['Telur Besar' => '35', 'Telur Kecil' => '6'], '40'))
        ->assertSessionHasErrors('outputs');
    $this->actingAs($this->owner)->post(route('egg.sortings.store'), ($this->sortPayload)(['Telur Besar' => '-1']))
        ->assertSessionHasErrors();
    $this->actingAs($this->owner)->post(route('egg.sortings.store'), ['input_qty' => '10', 'outputs' => [['product_id' => ($this->p)('Telur Campur')->id, 'qty' => '5']]])
        ->assertSessionHasErrors('outputs.0.product_id');
    $this->actingAs($this->owner)->post(route('egg.sortings.store'), ($this->sortPayload)(['Telur Besar' => '0']))
        ->assertSessionHasErrors('outputs');

    expect(asTenant($this->tenant, fn () => EggSorting::count()))->toBe(0)
        ->and(($this->stockOf)('Telur Campur')->qty)->toBe('100.000');
});

it('kasir boleh sortir dan kembali ke kasir, tapi tidak boleh mengubah harga', function () {
    $kasir = addStaff($this->owner, 'kasir');

    $this->actingAs($kasir)->post(route('egg.sortings.store'), ($this->sortPayload)(['Telur Besar' => '10'], '10') + ['kembali' => 'kasir'])
        ->assertSessionHasNoErrors()->assertRedirect(route('pos.show'));
    $this->actingAs($kasir)->get(route('egg.sortings.create'))->assertInertia(fn (Assert $page) => $page
        ->component('Pengepul/Sorting')->where('can_see_cost', false)->has('grades', 5));

    $this->actingAs($kasir)->post(route('egg.prices.store'), ['prices' => [['product_id' => ($this->p)('Telur Besar')->id, 'sell_price' => 1]]])
        ->assertForbidden();

    $karyawan = addStaff($this->owner, 'karyawan');
    $this->actingAs($karyawan)->get(route('egg.sortings.create'))->assertForbidden();
});

it('data pengepul tidak bisa dibaca atau diubah usaha lain', function () {
    $this->actingAs($this->owner)->post(route('egg.sortings.store'), ($this->sortPayload)(['Telur Besar' => '10'], '10'))->assertSessionHasNoErrors();
    $sorting = asTenant($this->tenant, fn () => EggSorting::first());

    $other = registerTenant('pengepul_telur', name: 'Pengepul Lain');
    $mine = ($this->p)('Telur Besar');

    $this->actingAs($other)->get(route('egg.sortings.show', $sorting->uuid))->assertNotFound();
    $this->actingAs($other)->post(route('egg.prices.store'), ['prices' => [['product_id' => $mine->id, 'sell_price' => 1]]])
        ->assertSessionHasErrors('prices.0.product_id');
    $this->actingAs($other)->post(route('egg.sortings.store'), ['input_qty' => '5', 'outputs' => [['product_id' => $mine->id, 'qty' => '5']]])
        ->assertSessionHasErrors('outputs.0.product_id');
    $this->actingAs($other)->put(route('egg.types.update'), ['mixed' => [($this->p)('Telur Campur')->id], 'grades' => [$mine->id]])
        ->assertSessionHasErrors(['mixed.0', 'grades.0']);
    $this->actingAs($other)->get(route('egg.sortings.create'))->assertInertia(fn (Assert $page) => $page->has('recent', 0));

    expect(DB::table('product_outlet')->where('product_id', $mine->id)->value('price'))->toBeNull()
        ->and(asTenant($this->tenant, fn () => EggProduct::count()))->toBe(6);
});

it('usaha tanpa modul pengepul tidak bisa membuka halaman sortir', function () {
    $warung = registerTenant('warung_makan');

    $this->actingAs($warung)->get(route('egg.sortings.create'))->assertRedirect(route('dashboard'));
    $this->actingAs($warung)->post(route('egg.prices.store'), ['prices' => []])->assertRedirect(route('dashboard'));
});

it('pemilik bisa mengatur barang mana telur campur dan mana hasil sortir', function () {
    $campur = ($this->p)('Telur Campur');
    $besar = ($this->p)('Telur Besar');

    $this->actingAs($this->owner)->put(route('egg.types.update'), ['mixed' => [$campur->id], 'grades' => [$campur->id]])
        ->assertSessionHasErrors('grades.0');
    $this->actingAs($this->owner)->put(route('egg.types.update'), ['mixed' => [$campur->id], 'grades' => [$besar->id]])
        ->assertSessionHasNoErrors()->assertRedirect(route('egg.prices'));

    expect(asTenant($this->tenant, fn () => EggProduct::where('role', 'grade')->count()))->toBe(1);
});

it('membagi rupiah tanpa selisih pembulatan', function () {
    expect(EggSortingService::allocate(100, [1, 1, 1]))->toBe([34, 33, 33])
        ->and(EggSortingService::allocate(948_000, [840_000, 200_000, 30_000]))->toBe([744_224, 177_196, 26_580])
        ->and(EggSortingService::allocate(0, [1, 2]))->toBe([0, 0]);
});
