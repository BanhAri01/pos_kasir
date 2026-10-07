<?php

use App\Models\Outlet;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockDocumentService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->actingAs($this->owner);

    $this->product = asTenant($this->tenant, fn () => Product::create([
        'uuid' => (string) Str::uuid(), 'name' => 'Gula 1 kg', 'type' => 'goods', 'price' => 17000, 'cost_price' => 15000, 'track_stock' => true,
    ]));
});

function stockOf($test): string
{
    return asTenant($test->tenant, fn () => app(StockService::class)->qty($test->outletId, $test->product));
}

it('mencatat setiap perubahan stok di riwayat', function () {
    asTenant($this->tenant, function () {
        $service = app(StockService::class);
        $service->change($this->outletId, $this->product, '10', 'adjust_in');
        $service->change($this->outletId, $this->product, '-3', 'adjust_out');
    });

    expect(stockOf($this))->toBe('7.000');

    $movements = asTenant($this->tenant, fn () => StockMovement::where('product_id', $this->product->id)->orderBy('id')->get());
    expect($movements)->toHaveCount(2)
        ->and($movements[1]->qty_before)->toBe('10.000')
        ->and($movements[1]->qty_change)->toBe('-3.000')
        ->and($movements[1]->qty_after)->toBe('7.000')
        ->and($movements[1]->user_id)->toBe($this->owner->id);
});

it('menghitung desimal dengan tepat (laundry kg, meter)', function () {
    asTenant($this->tenant, function () {
        $service = app(StockService::class);
        $service->change($this->outletId, $this->product, '0.1', 'adjust_in');
        $service->change($this->outletId, $this->product, '0,2', 'adjust_in');
    });

    expect(stockOf($this))->toBe('0.300');
});

it('membolehkan stok minus supaya penjualan yang sudah terjadi tidak ditolak', function () {
    asTenant($this->tenant, fn () => app(StockService::class)->change($this->outletId, $this->product, '-2', 'sale'));

    expect(stockOf($this))->toBe('-2.000');
});

it('menghitung modal rata-rata saat stok masuk dengan harga berbeda', function () {
    asTenant($this->tenant, function () {
        $service = app(StockService::class);
        $service->change($this->outletId, $this->product, '10', 'adjust_in', unitCost: 15000);
        $service->change($this->outletId, $this->product, '10', 'adjust_in', unitCost: 16000);
    });

    $avg = asTenant($this->tenant, fn () => Stock::where('product_id', $this->product->id)->value('avg_cost'));
    expect($avg)->toBe(15500);
});

it('tidak melacak stok untuk layanan', function () {
    $service = asTenant($this->tenant, fn () => Product::create([
        'uuid' => (string) Str::uuid(), 'name' => 'Potong Rambut', 'type' => 'service', 'price' => 25000, 'track_stock' => true,
    ]));

    $movement = asTenant($this->tenant, fn () => app(StockService::class)->change($this->outletId, $service, '-1', 'sale'));

    expect($movement)->toBeNull();
});

it('hitung stok menyamakan stok dengan hasil hitung dan mencatat selisihnya', function () {
    asTenant($this->tenant, function () {
        app(StockService::class)->change($this->outletId, $this->product, '10', 'adjust_in');
        $opname = app(StockDocumentService::class)->opname($this->outletId, [
            ['product_id' => $this->product->id, 'counted_qty' => '8'],
        ]);

        expect($opname->items->first())
            ->system_qty->toBe('10.000')
            ->counted_qty->toBe('8.000')
            ->difference->toBe('-2.000');
    });

    expect(stockOf($this))->toBe('8.000');
});

it('kirim stok mengurangi outlet asal, lalu menambah outlet tujuan saat diterima', function () {
    $second = asTenant($this->tenant, fn () => Outlet::create(['name' => 'Cabang 2']));

    asTenant($this->tenant, function () use ($second) {
        $stock = app(StockService::class);
        $docs = app(StockDocumentService::class);
        $stock->change($this->outletId, $this->product, '10', 'adjust_in');

        $transfer = $docs->sendTransfer($this->outletId, $second->id, [['product_id' => $this->product->id, 'qty' => '4']]);
        expect($stock->qty($this->outletId, $this->product))->toBe('6.000')
            ->and($stock->qty($second->id, $this->product))->toBe('0.000');

        $docs->receiveTransfer($transfer);
        expect($stock->qty($second->id, $this->product))->toBe('4.000');

        // Tidak bisa diterima dua kali.
        expect(fn () => $docs->receiveTransfer($transfer->fresh()))->toThrow(ValidationException::class);
    });
});

it('batal kirim mengembalikan stok ke outlet asal', function () {
    $second = asTenant($this->tenant, fn () => Outlet::create(['name' => 'Cabang 2']));

    asTenant($this->tenant, function () use ($second) {
        $stock = app(StockService::class);
        $docs = app(StockDocumentService::class);
        $stock->change($this->outletId, $this->product, '10', 'adjust_in');

        $transfer = $docs->sendTransfer($this->outletId, $second->id, [['product_id' => $this->product->id, 'qty' => '4']]);
        $docs->cancelTransfer($transfer);

        expect($stock->qty($this->outletId, $this->product))->toBe('10.000')
            ->and($transfer->fresh()->status)->toBe('canceled');
    });
});

it('stok usaha lain tidak terlihat', function () {
    asTenant($this->tenant, fn () => app(StockService::class)->change($this->outletId, $this->product, '5', 'adjust_in'));
    $other = registerTenant('toko_kelontong', name: 'Toko Lain')->tenant;

    $ids = asTenant($other, fn () => Stock::pluck('product_id'));

    expect($ids)->not->toContain($this->product->id);
});
