<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Modules\Expense\Models\Expense;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use App\Modules\Report\Services\ReportFilter;
use App\Modules\Report\Services\ReportService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function summaryFor($owner, ?string $from = null, ?string $to = null): array
{
    $today = now('Asia/Jakarta')->toDateString();

    return asTenant($owner->tenant, fn () => app(ReportService::class)->summary(ReportFilter::make($from ?? $today, $to ?? $today, 'Asia/Jakarta', [outletsOf($owner)->first()->id])));
}

beforeEach(function () {
    $this->owner = registerTenant('warung_makan');
    $this->cash = paymentMethod($this->owner);
    $this->qris = paymentMethod($this->owner, 'qris');
    $this->shift = openShift($this, $this->owner);
    $this->nasgor = productNamed($this->owner, 'Nasi Goreng'); // 15.000, modal 7.000
    $this->teh = productNamed($this->owner, 'Es Teh Manis');   // 5.000, modal 1.200
});

describe('angka laporan', function () {
    it('menghitung uang masuk, modal, untung kotor, pengeluaran, dan untung bersih', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 2], ['product_id' => $this->teh->id, 'qty' => 2]], [['payment_method_id' => $this->cash->id, 'amount' => 40000]])->assertCreated();
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 1]], [['payment_method_id' => $this->qris->id, 'amount' => 15000]])->assertCreated();

        $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => 10000, 'spent_on' => now('Asia/Jakarta')->toDateString(), 'note' => 'gas'])->assertSessionHasNoErrors();

        $s = summaryFor($this->owner);
        expect($s['transactions'])->toBe(2)
            ->and($s['total_collected'])->toBe(55000)
            ->and($s['net_sales'])->toBe(55000)
            ->and($s['cogs'])->toBe(3 * 7000 + 2 * 1200)
            ->and($s['gross_profit'])->toBe(55000 - 23400)
            ->and($s['expenses'])->toBe(10000)
            ->and($s['net_profit'])->toBe(55000 - 23400 - 10000)
            ->and($s['average'])->toBe(27500);
    });

    it('mengurangi diskon dan pengembalian barang (di hari pengembaliannya)', function () {
        $res = sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 2, 'discount_amount' => 2000]], [['payment_method_id' => $this->cash->id, 'amount' => 28000]])->assertCreated();
        $uuid = $res->json('data.uuid');
        $item = asTenant($this->owner->tenant, fn () => Sale::where('uuid', $uuid)->first()->items()->first());

        $this->actingAs($this->owner)->postJson(route('pos.api.sales.refund', $uuid), [
            'uuid' => (string) Str::uuid(), 'reason' => 'salah pesan', 'items' => [['sale_item_id' => $item->id, 'qty' => 1]], 'restock' => false,
        ])->assertSuccessful();

        $s = summaryFor($this->owner);
        expect($s['discounts'])->toBe(2000)
            ->and($s['refunds'])->toBe(14000)
            ->and($s['gross_sales'])->toBe(30000)
            ->and($s['net_sales'])->toBe(14000)
            ->and($s['total_collected'])->toBe(14000)
            ->and($s['cogs'])->toBe(7000);
    });

    it('tidak menghitung transaksi yang dibatalkan', function () {
        $uuid = sell($this, $this->owner, $this->shift, [['product_id' => $this->teh->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 5000]])->json('data.uuid');
        $this->actingAs($this->owner)->postJson(route('pos.api.sales.void', $uuid), ['reason' => 'salah input'])->assertSuccessful();

        expect(summaryFor($this->owner)['transactions'])->toBe(0);
    });

    it('mengelompokkan per hari menurut jam Indonesia (WIB), bukan UTC', function () {
        // 23.30 WIB tanggal 3 = 16.30 UTC tanggal 3; 00.30 WIB tanggal 4 = 17.30 UTC tanggal 3.
        sell($this, $this->owner, $this->shift, [['product_id' => $this->teh->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 5000]]);
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 15000]]);
        asTenant($this->owner->tenant, function () {
            [$a, $b] = Sale::orderBy('id')->get()->all();
            $a->update(['completed_at' => Carbon::parse('2026-10-03 23:30', 'Asia/Jakarta')->utc()]);
            $b->update(['completed_at' => Carbon::parse('2026-10-04 00:30', 'Asia/Jakarta')->utc()]);
        });

        $series = asTenant($this->owner->tenant, fn () => app(ReportService::class)->series(ReportFilter::make('2026-10-03', '2026-10-04', 'Asia/Jakarta')));
        expect(collect($series)->pluck('total', 'key')->all())->toBe(['2026-10-03' => 5000, '2026-10-04' => 15000]);

        $hours = asTenant($this->owner->tenant, fn () => app(ReportService::class)->byHour(ReportFilter::make('2026-10-03', '2026-10-04', 'Asia/Jakarta')));
        expect($hours[23]['transactions'])->toBe(1)->and($hours[0]['transactions'])->toBe(1);
    });

    it('merinci barang terlaris, cara bayar, dan kasir', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->teh->id, 'qty' => 5]], [['payment_method_id' => $this->qris->id, 'amount' => 25000]]);
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 20000]]);

        $f = ReportFilter::make(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString(), 'Asia/Jakarta');
        [$products, $payments, $cashiers] = asTenant($this->owner->tenant, fn () => [
            app(ReportService::class)->products($f, 10, 'qty'),
            app(ReportService::class)->payments($f),
            app(ReportService::class)->cashiers($f),
        ]);

        expect($products[0]['name'])->toBe('Es Teh Manis')->and($products[0]['qty'])->toEqual(5)->and($products[0]['profit'])->toBe(25000 - 6000)
            ->and(collect($payments)->pluck('net', 'type')->all())->toMatchArray(['qris' => 25000, 'cash' => 15000])
            ->and($cashiers[0]['transactions'])->toBe(2);
    });
});

describe('halaman laporan', function () {
    it('menampilkan setiap tab laporan', function (string $tab, string $prop) {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 15000]]);

        $this->actingAs($this->owner)->get(route('reports.index', ['tab' => $tab]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/Index')->where('tab', $tab)->has($prop));
    })->with([
        ['ringkasan', 'summary'], ['barang', 'products'], ['pembayaran', 'payments'], ['karyawan', 'cashiers'], ['outlet', 'outletSales'],
    ]);

    it('kasir tidak bisa membuka laporan', function () {
        $kasir = addStaff($this->owner, 'kasir');

        $this->actingAs($kasir)->get(route('reports.index'))->assertForbidden();
    });

    it('tidak bisa melihat outlet yang bukan haknya', function () {
        Spatie\Permission\Models\Role::findByName('manager')->revokePermissionTo('view_all_outlets');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $manager = addStaff($this->owner, 'manager');
        $other = asTenant($this->owner->tenant, fn () => App\Models\Outlet::create(['name' => 'Cabang Lain', 'code' => 'CL']));

        $this->actingAs($manager)->get(route('reports.index', ['outlet' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.outlet', (string) outletsOf($manager)->first()->id));
    });

    it('mengunduh Excel yang berisi semua sheet', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 15000]]);

        $response = $this->actingAs($this->owner)->get(route('reports.export'));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $zip = $response->getContent();
        expect(substr($zip, 0, 4))->toBe("PK\x03\x04");

        $files = unzipEntries($zip);
        expect(array_keys($files))->toContain('[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml')
            ->and($files['xl/workbook.xml'])->toContain('name="Ringkasan"')->toContain('name="Barang"')
            ->and($files['xl/worksheets/sheet3.xml'])->toContain('Nasi Goreng');
    });

    it('menampilkan versi cetak laporan', function () {
        $this->actingAs($this->owner)->get(route('reports.print'))->assertOk()->assertSee('LAPORAN')->assertSee('Untung bersih');
    });
});

describe('pengeluaran', function () {
    it('mencatat pengeluaran dari laci kasir sebagai uang keluar', function () {
        $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => 25000, 'spent_on' => now('Asia/Jakarta')->toDateString(), 'from_cash_drawer' => true, 'note' => 'beli es batu'])
            ->assertSessionHasNoErrors();

        $shift = asTenant($this->owner->tenant, fn () => Shift::where('uuid', $this->shift)->first());
        $summary = $this->actingAs($this->owner)->getJson(route('pos.api.shifts.summary', $shift->uuid))->json('data');
        expect($summary['cash_out'])->toBe(25000);

        // Hapus pengeluaran → uang keluar di kasir yang masih buka ikut terhapus.
        $expense = asTenant($this->owner->tenant, fn () => Expense::first());
        $this->actingAs($this->owner)->delete(route('expenses.destroy', $expense))->assertSessionHas('success');
        expect($this->actingAs($this->owner)->getJson(route('pos.api.shifts.summary', $shift->uuid))->json('data.cash_out'))->toBe(0);
    });

    it('membuat jenis pengeluaran bawaan dan menolak tanggal masa depan', function () {
        $this->actingAs($this->owner)->get(route('expenses.index'))->assertInertia(fn (Assert $page) => $page->has('categories', 6));

        $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => 1000, 'spent_on' => now()->addWeek()->toDateString()])->assertSessionHasErrors('spent_on');
    });
});

describe('beranda', function () {
    it('menampilkan ringkasan hari ini untuk pemilik', function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->nasgor->id, 'qty' => 2]], [['payment_method_id' => $this->cash->id, 'amount' => 30000]]);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('stats')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.summary.total_collected', 30000)
                    ->where('stats.topProducts.0.name', 'Nasi Goreng')
                    ->has('stats.week', 7)));
    });

    it('menampilkan penjualan sendiri untuk kasir, tanpa angka usaha', function () {
        $kasir = addStaff($this->owner, 'kasir');
        $shift = openShift($this, $kasir);
        sell($this, $kasir, $shift, [['product_id' => $this->teh->id, 'qty' => 1]], [['payment_method_id' => $this->cash->id, 'amount' => 5000]]);

        $this->actingAs($kasir)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('mine.total', 5000)->where('stats', null));
    });
});

/** Baca isi zip (stored / deflate) tanpa ekstensi zip, untuk memeriksa file Excel. */
function unzipEntries(string $zip): array
{
    $files = [];
    $offset = 0;
    while (substr($zip, $offset, 4) === "PK\x03\x04") {
        $h = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr($zip, $offset + 4, 26));
        $name = substr($zip, $offset + 30, $h['nlen']);
        $data = substr($zip, $offset + 30 + $h['nlen'] + $h['elen'], $h['csize']);
        $files[$name] = $h['method'] === 8 ? gzinflate($data) : $data;
        expect(crc32($files[$name]))->toBe($h['crc']);
        $offset += 30 + $h['nlen'] + $h['elen'] + $h['csize'];
    }

    return $files;
}
