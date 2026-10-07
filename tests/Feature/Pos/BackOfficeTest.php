<?php

require_once __DIR__.'/PosTestHelpers.php';

use App\Modules\Customer\Models\Customer;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->kasir = addStaff($this->owner, 'kasir', '1111');
    $this->cash = paymentMethod($this->owner, 'cash');
    $this->beras = productNamed($this->owner, 'Beras Premium 5 kg');
    $shift = openShift($this, $this->kasir);
    $this->sale = $this->actingAs($this->kasir)->postJson(route('pos.api.sales.store'), salePayload($shift, [
        ['product_id' => $this->beras->id, 'qty' => 2],
    ], [['payment_method_id' => $this->cash->id, 'amount' => 150000]]))->json('data');
});

it('pemilik melihat transaksi hari ini dengan ringkasan', function () {
    $this->actingAs($this->owner)->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Index')
            ->where('summary.count', 1)
            ->where('summary.total', 144000)
            ->has('sales', 1));
});

it('kasir tidak bisa membuka halaman transaksi back-office', function () {
    $this->actingAs($this->kasir)->get(route('sales.index'))->assertForbidden();
});

it('pemilik bisa membatalkan transaksi dari halaman detail', function () {
    $this->actingAs($this->owner)->get(route('sales.show', $this->sale['uuid']))
        ->assertInertia(fn ($page) => $page->component('Sales/Show')->where('can.void', true));

    $this->actingAs($this->owner)->post(route('sales.void', $this->sale['uuid']), ['reason' => 'Uji coba'])
        ->assertSessionHas('success');

    expect(asTenant($this->tenant, fn () => Sale::first()->status))->toBe('void');
});

it('riwayat buka/tutup kasir menampilkan uang seharusnya', function () {
    $this->actingAs($this->owner)->get(route('shifts.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Shifts')
            ->where('shifts.0.expected_cash', 100000 + 144000)
            ->where('shifts.0.status', 'open'));
});

it('pelanggan: tambah, lihat riwayat, ubah, hapus & batalkan', function () {
    $this->actingAs($this->kasir)->post(route('customers.store'), ['name' => 'Bu Ani', 'phone' => '0813 2222 3333'])
        ->assertRedirect();
    $customer = asTenant($this->tenant, fn () => Customer::where('name', 'Bu Ani')->first());
    expect($customer->phone)->toBe('6281322223333');

    $this->actingAs($this->kasir)->get(route('customers.show', $customer))
        ->assertInertia(fn ($page) => $page->component('Customers/Show')->where('stats.count', 0));

    $this->actingAs($this->kasir)->put(route('customers.update', $customer), ['name' => 'Bu Ani Lestari'])->assertRedirect();
    expect($customer->fresh()->name)->toBe('Bu Ani Lestari');

    $this->actingAs($this->kasir)->delete(route('customers.destroy', $customer))->assertSessionHas('undo');
    $this->actingAs($this->kasir)->post(route('customers.restore', $customer->id));
    expect($customer->fresh()->trashed())->toBeFalse();
});

it('pelanggan usaha lain tidak bisa dibuka', function () {
    $other = registerTenant('toko_kelontong', name: 'Toko Lain');
    $foreign = asTenant($other->tenant, fn () => Customer::create(['uuid' => (string) Str::uuid(), 'name' => 'Rahasia']));

    $this->actingAs($this->owner)->get(route('customers.show', $foreign))->assertNotFound();
});

it('pemilik bisa menambah cara bayar dan mematikannya, tapi tidak semuanya', function () {
    $this->actingAs($this->owner)->post(route('payment-methods.store'), ['name' => 'GoPay', 'type' => 'ewallet'])->assertSessionHas('success');
    $gopay = asTenant($this->tenant, fn () => PaymentMethod::where('name', 'GoPay')->first());

    $this->actingAs($this->owner)->put(route('payment-methods.update', $gopay), ['is_active' => false]);
    expect($gopay->fresh()->is_active)->toBeFalse();

    asTenant($this->tenant, fn () => PaymentMethod::where('id', '!=', $this->cash->id)->update(['is_active' => false]));
    $this->actingAs($this->owner)->put(route('payment-methods.update', $this->cash), ['is_active' => false])
        ->assertSessionHas('error', 'Minimal harus ada satu cara bayar yang menyala.');
});

it('menyimpan pajak & biaya layanan outlet dalam basis point', function () {
    $outlet = outletsOf($this->owner)->first();

    $this->actingAs($this->owner)->put(route('outlets.update', $outlet), [
        'name' => $outlet->name, 'tax_rate' => '11', 'service_charge' => '2,5', 'tax_inclusive' => true,
        'receipt_footer' => 'Terima kasih!', 'receipt_paper' => '80',
    ])->assertSessionHasNoErrors();

    expect($outlet->fresh())
        ->tax_rate_bp->toBe(1100)
        ->service_charge_bp->toBe(250)
        ->tax_inclusive->toBeTrue()
        ->receipt_paper->toBe('80');
});

it('kasir tidak bisa mengubah cara bayar', function () {
    $this->actingAs($this->kasir)->get(route('payment-methods.index'))->assertForbidden();
});
