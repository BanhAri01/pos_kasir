<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', 'Asia/Jakarta'));
    $this->owner = registerTenant('coffee_shop', phone: '6281211112222');
    $this->tenant = $this->owner->tenant;
    $this->product = asTenant($this->tenant, fn () => Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Es Teh', 'type' => 'goods', 'price' => 5000, 'cost_price' => 1000, 'track_stock' => false]));
});

afterEach(fn () => Carbon::setTestNow());

function sellTea($test, int $qty = 3): string
{
    $shift = openShift($test, $test->owner);
    $test->actingAs($test->owner)->postJson(route('pos.api.sales.store'), salePayload($shift, [['product_id' => $test->product->id, 'qty' => $qty]], [['payment_method_id' => paymentMethod($test->owner)->id, 'amount' => 5000 * $qty]]))->assertCreated();

    return $shift;
}

function reportMessages($tenant)
{
    return asTenant($tenant, fn () => WhatsappMessage::where('template_code', 'daily_report')->get());
}

it('mengirim laporan harian sekali saja setelah jam yang dipilih', function () {
    sellTea($this);

    Artisan::call('hermes:owner-reports');
    expect(reportMessages($this->tenant))->toHaveCount(0);

    Carbon::setTestNow(Carbon::parse('2026-10-09 21:05:00', 'Asia/Jakarta'));
    Artisan::call('hermes:owner-reports');
    Artisan::call('hermes:owner-reports');

    $messages = reportMessages($this->tenant);
    expect($messages)->toHaveCount(1)
        ->and($messages->first()->to_phone)->toBe('6281211112222')
        ->and($messages->first()->body)->toContain('Rp15.000')->toContain('Es Teh')->toContain('Untung');
});

it('tidak mengirim laporan kalau tidak ada penjualan atau dimatikan', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-09 22:00:00', 'Asia/Jakarta'));
    Artisan::call('hermes:owner-reports');
    expect(reportMessages($this->tenant))->toHaveCount(0);

    $this->actingAs($this->owner)->put(route('owner-report.update'), ['daily' => false, 'daily_at' => '21:00', 'on_close' => false, 'phone' => ''])->assertSessionHas('success');
    sellTea($this);
    $this->tenant->refresh();
    $settings = $this->tenant->settings;
    unset($settings['owner_report_sent_on']);
    $this->tenant->forceFill(['settings' => $settings])->save();
    Artisan::call('hermes:owner-reports');
    expect(reportMessages($this->tenant))->toHaveCount(0);
});

it('mengirim ringkasan saat tutup kasir kalau dinyalakan', function () {
    $this->actingAs($this->owner)->put(route('owner-report.update'), ['daily' => true, 'daily_at' => '21:00', 'on_close' => true, 'phone' => '0813 9999 8888']);
    $shift = sellTea($this);

    $this->actingAs($this->owner)->postJson(route('pos.api.shifts.close', $shift), ['counted_cash' => 100000 + 15000 - 2000])->assertOk();

    $message = reportMessages($this->tenant)->first();
    expect($message->to_phone)->toBe('6281399998888')->and($message->body)->toContain('Uang kurang')->toContain('Rp2.000');
});

it('pemilik bisa melihat contoh dan mengirim laporan sekarang', function () {
    sellTea($this);

    $this->actingAs($this->owner)->get(route('owner-report.edit'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('preview', fn ($p) => str_contains($p, 'Es Teh')));
    $this->actingAs($this->owner)->post(route('owner-report.test'))->assertSessionHas('success');

    expect(reportMessages($this->tenant))->toHaveCount(1);
});

it('kasir tidak bisa mengubah pengaturan laporan', function () {
    $this->actingAs(addStaff($this->owner))->get(route('owner-report.edit'))->assertForbidden();
});
