<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\CashMovement;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use App\Modules\Sync\Models\SyncConflict;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->outletId = outletsOf($this->owner)->first()->id;
    $this->kasir = addStaff($this->owner, 'kasir', '1111');
    $this->cash = paymentMethod($this->owner, 'cash');
    $this->beras = productNamed($this->owner, 'Beras Premium 5 kg'); // stok 20
});

/** Antrean seperti yang dibuat HP kasir saat offline. */
function offlineQueue($test, int $berasQty = 2): array
{
    $shiftUuid = (string) Str::uuid();
    $customerUuid = (string) Str::uuid();

    return [
        'shift' => $shiftUuid,
        'items' => [
            ['id' => 1, 'type' => 'shift.open', 'payload' => ['uuid' => $shiftUuid, 'outlet_id' => $test->outletId, 'opening_cash' => 50000, 'opened_at' => now()->subHour()->toIso8601String()]],
            ['id' => 2, 'type' => 'customer.create', 'payload' => ['uuid' => $customerUuid, 'name' => 'Pak Offline', 'phone' => '0812 9999 0000']],
            ['id' => 3, 'type' => 'sale', 'payload' => salePayload($shiftUuid, [
                ['product_id' => $test->beras->id, 'qty' => $berasQty, 'unit_price' => 72000],
            ], [['payment_method_id' => $test->cash->id, 'amount' => 72000 * $berasQty]], ['customer_uuid' => $customerUuid, 'created_at' => now()->subMinutes(30)->toIso8601String()])],
            ['id' => 4, 'type' => 'cash', 'payload' => ['uuid' => (string) Str::uuid(), 'shift_uuid' => $shiftUuid, 'type' => 'out', 'amount' => 5000, 'reason' => 'Beli es']],
        ],
    ];
}

it('memproses antrean offline berurutan: buka kasir, pelanggan, jualan, uang keluar', function () {
    $queue = offlineQueue($this);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'ok')
        ->assertJsonPath('results.1.status', 'ok')
        ->assertJsonPath('results.2.status', 'ok')
        ->assertJsonPath('results.3.status', 'ok')
        ->assertJsonStructure(['results' => [2 => ['data' => ['receipt_url', 'number']]]]);

    $sale = asTenant($this->tenant, fn () => Sale::with('customer')->first());
    expect($sale->customer->name)->toBe('Pak Offline')
        ->and($sale->synced_at)->not->toBeNull()
        ->and($sale->shift->uuid)->toBe($queue['shift'])
        ->and(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('18.000')
        ->and(asTenant($this->tenant, fn () => CashMovement::count()))->toBe(1);
});

it('aman dikirim berkali-kali: tidak ada data dobel', function () {
    $queue = offlineQueue($this);

    foreach (range(1, 3) as $i) {
        $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']])->assertOk();
    }

    expect(asTenant($this->tenant, fn () => Sale::count()))->toBe(1)
        ->and(asTenant($this->tenant, fn () => Shift::count()))->toBe(1)
        ->and(asTenant($this->tenant, fn () => Customer::where('name', 'Pak Offline')->count()))->toBe(1)
        ->and(asTenant($this->tenant, fn () => CashMovement::count()))->toBe(1)
        ->and(asTenant($this->tenant, fn () => app(StockService::class)->qty($this->outletId, $this->beras)))->toBe('18.000');
});

it('kasir offline dengan uuid sendiri tetap dibuat walau ada kasir lain yang sedang buka', function () {
    openShift($this, $this->kasir); // kasir online di HP lain
    $queue = offlineQueue($this);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']])->assertOk();

    expect(asTenant($this->tenant, fn () => Shift::count()))->toBe(2)
        ->and(asTenant($this->tenant, fn () => Sale::first()->shift->uuid))->toBe($queue['shift']);
});

it('penjualan offline yang membuat stok minus tetap diterima dan dicatat untuk dicek pemilik', function () {
    $queue = offlineQueue($this, berasQty: 25);

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']])
        ->assertJsonPath('results.2.status', 'ok');

    $conflict = asTenant($this->tenant, fn () => SyncConflict::first());
    expect($conflict->type)->toBe('stock_negative')
        ->and($conflict->message)->toContain('Beras Premium 5 kg')
        ->and($conflict->message)->toContain('minus -5');

    // Dikirim ulang tidak membuat catatan dobel.
    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']]);
    expect(asTenant($this->tenant, fn () => SyncConflict::count()))->toBe(1);
});

it('data rusak tidak menghentikan antrean dan disimpan untuk pemilik (tidak hilang)', function () {
    $queue = offlineQueue($this);
    $queue['items'][2]['payload']['items'][0]['product_id'] = 999999; // barang tidak ada

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']])
        ->assertJsonPath('results.2.status', 'conflict')
        ->assertJsonPath('results.3.status', 'ok'); // yang lain tetap diproses

    $conflict = asTenant($this->tenant, fn () => SyncConflict::where('type', 'rejected')->first());
    expect($conflict->entity_type)->toBe('sale')
        ->and($conflict->payload['items'][0]['product_id'])->toBe(999999);
});

it('tidak bisa memakai barang atau outlet usaha lain lewat sinkronisasi', function () {
    $other = registerTenant('toko_kelontong', name: 'Toko Lain');
    $foreign = productNamed($other, 'Beras Premium 5 kg');
    $queue = offlineQueue($this);
    $queue['items'][2]['payload']['items'][0]['product_id'] = $foreign->id;
    $queue['items'][0]['payload']['outlet_id'] = outletsOf($other)->first()->id;

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $queue['items']])
        ->assertJsonPath('results.0.status', 'conflict')
        ->assertJsonPath('results.2.status', 'conflict');

    expect(asTenant($other->tenant, fn () => app(StockService::class)->qty(outletsOf($other)->first()->id, $foreign)))->toBe('20.000');
});

it('pemilik melihat dan menyelesaikan daftar "Perlu Dicek"', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => offlineQueue($this, 25)['items']]);
    $conflict = asTenant($this->tenant, fn () => SyncConflict::first());

    $this->actingAs($this->owner)->get(route('conflicts.index'))
        ->assertInertia(fn ($page) => $page->component('Sync/Conflicts')->has('conflicts', 1));

    $this->actingAs($this->owner)->post(route('conflicts.resolve', $conflict))->assertSessionHas('success');
    expect($conflict->fresh()->resolved_at)->not->toBeNull();
});

it('bootstrap kasir menyertakan pelanggan untuk pencarian offline', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => offlineQueue($this)['items']]);

    $this->actingAs($this->kasir)->getJson(route('pos.api.bootstrap'))
        ->assertJsonPath('customers.0.name', 'Pak Offline');
});

it('sinkronisasi butuh login dan izin kasir', function () {
    $this->postJson(route('pos.api.sync'), ['items' => []])->assertUnauthorized();

    $staff = addStaff($this->owner, 'karyawan', '2222');
    $this->actingAs($staff)->postJson(route('pos.api.sync'), ['items' => offlineQueue($this)['items']])->assertForbidden();
});

function offlineSaleAt(int $price, $test): array
{
    $shiftUuid = (string) Str::uuid();

    return [
        ['id' => 1, 'type' => 'shift.open', 'payload' => ['uuid' => $shiftUuid, 'outlet_id' => $test->outletId, 'opening_cash' => 0]],
        ['id' => 2, 'type' => 'sale', 'payload' => salePayload($shiftUuid, [
            ['product_id' => $test->beras->id, 'qty' => 2, 'unit_price' => $price],
        ], [['payment_method_id' => $test->cash->id, 'amount' => $price * 2]])],
    ];
}

it('kasir tanpa izin ubah harga yang mengirim harga lain lewat sinkronisasi masuk daftar "Perlu Dicek"', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => offlineSaleAt(1000, $this)])
        ->assertOk()
        ->assertJsonPath('results.1.status', 'ok');

    $sale = asTenant($this->tenant, fn () => Sale::first());
    $conflict = asTenant($this->tenant, fn () => SyncConflict::where('type', 'price_changed')->first());

    expect($conflict)->not->toBeNull()
        ->and($conflict->entity_uuid)->toBe($sale->uuid)
        ->and($conflict->outlet_id)->toBe($this->outletId)
        ->and($conflict->message)->toContain($sale->number)
        ->and($conflict->message)->toContain($this->kasir->name)
        ->and($conflict->message)->toContain('Beras Premium 5 kg');
});

it('harga normal atau pemilik yang berhak ubah harga tidak memicu "Perlu Dicek"', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => offlineSaleAt(72000, $this)])->assertOk();
    $this->actingAs($this->owner)->postJson(route('pos.api.sync'), ['items' => offlineSaleAt(1000, $this)])->assertOk();

    expect(asTenant($this->tenant, fn () => Sale::count()))->toBe(2)
        ->and(asTenant($this->tenant, fn () => SyncConflict::where('type', 'price_changed')->count()))->toBe(0);
});

it('transaksi bertanggal masa depan dicatat dengan waktu sekarang dan masuk "Perlu Dicek"', function () {
    $items = offlineSaleAt(72000, $this);
    $items[1]['payload']['created_at'] = now()->addDays(3)->toIso8601String();

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $items])->assertJsonPath('results.1.status', 'ok');

    $sale = asTenant($this->tenant, fn () => Sale::first());
    expect($sale->completed_at->isAfter(now()->addMinutes(1)))->toBeFalse()
        ->and(asTenant($this->tenant, fn () => SyncConflict::where('type', 'sale_date')->where('entity_uuid', $sale->uuid)->exists()))->toBeTrue();
});

it('transaksi yang tanggalnya mundur lebih dari 2 hari masuk "Perlu Dicek"', function () {
    $items = offlineSaleAt(72000, $this);
    $items[1]['payload']['created_at'] = now()->subDays(5)->toIso8601String();

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $items])->assertJsonPath('results.1.status', 'ok');

    $conflict = asTenant($this->tenant, fn () => SyncConflict::where('type', 'sale_date')->first());
    expect($conflict)->not->toBeNull()
        ->and($conflict->message)->toContain($this->kasir->name);
});

it('transaksi offline yang wajar tidak memicu peringatan tanggal', function () {
    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => offlineQueue($this)['items']])->assertOk();

    expect(asTenant($this->tenant, fn () => SyncConflict::where('type', 'sale_date')->count()))->toBe(0);
});

it('jam buka kasir di masa depan diganti waktu sekarang', function () {
    $items = offlineSaleAt(72000, $this);
    $items[0]['payload']['opened_at'] = now()->addDays(2)->toIso8601String();

    $this->actingAs($this->kasir)->postJson(route('pos.api.sync'), ['items' => $items])->assertOk();

    expect(asTenant($this->tenant, fn () => Shift::first())->opened_at->isAfter(now()->addMinutes(1)))->toBeFalse();
});
