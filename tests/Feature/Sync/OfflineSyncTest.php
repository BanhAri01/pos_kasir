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
