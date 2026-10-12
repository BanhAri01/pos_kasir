<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Services\KasbonBillService;
use App\Modules\Operations\Services\ReceivableService;
use App\Modules\Warehouse\Models\CustomerDeposit;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->cash = paymentMethod($this->owner);
    $this->beras = productNamed($this->owner, 'Beras Premium 5 kg');
    $this->shift = openShift($this, $this->owner);
    $this->joko = makeCustomer($this->owner, ['name' => 'Pak Joko', 'phone' => '081234567890']);
});

describe('fitur titip uang dan tagihan wa di modul gudang', function () {
    it('menampilkan halaman gudang titip uang dengan metric dan daftar pelanggan', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(200_000, 'deposit', 'Titipan awal', 'warehouse');
        });

        $this->actingAs($this->owner)
            ->get(route('warehouse.deposits'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Deposits')
                ->has('stats')
                ->where('stats.total_deposit', 200_000)
                ->has('customers', 1)
                ->where('customers.0.deposit_balance', 200_000)
                ->where('customers.0.name', 'Pak Joko')
            );
    });

    it('bisa mencatat titip uang baru dari gudang', function () {
        $this->actingAs($this->owner)
            ->post(route('warehouse.deposits.store'), [
                'customer_id' => $this->joko->id,
                'type' => 'deposit',
                'amount' => 500_000,
                'notes' => 'Titip uang beli bibit ayam',
            ])
            ->assertRedirect();

        $this->joko->refresh();
        expect($this->joko->deposit_balance)->toBe(500_000);

        $deposit = CustomerDeposit::query()->where('customer_id', $this->joko->id)->first();
        expect($deposit->type)->toBe('deposit')
            ->and($deposit->amount)->toBe(500_000)
            ->and($deposit->balance_after)->toBe(500_000)
            ->and($deposit->notes)->toBe('Titip uang beli bibit ayam');
    });

    it('bisa menarik saldo titip uang bila saldo mencukupi', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(300_000, 'deposit', 'Saldo awal', 'warehouse');
        });

        $this->actingAs($this->owner)
            ->post(route('warehouse.deposits.store'), [
                'customer_id' => $this->joko->id,
                'type' => 'withdrawal',
                'amount' => 100_000,
                'notes' => 'Tarik tunai',
            ])
            ->assertRedirect();

        $this->joko->refresh();
        expect($this->joko->deposit_balance)->toBe(200_000);
    });

    it('gagal menarik saldo titip uang bila melebihi saldo tersedia', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(50_000, 'deposit', 'Saldo awal', 'warehouse');
        });

        $this->actingAs($this->owner)
            ->post(route('warehouse.deposits.store'), [
                'customer_id' => $this->joko->id,
                'type' => 'withdrawal',
                'amount' => 100_000,
            ])
            ->assertSessionHasErrors(['amount']);

        $this->joko->refresh();
        expect($this->joko->deposit_balance)->toBe(50_000);
    });

    it('kelebihan bayar nota otomatis masuk ke saldo deposit pelanggan (overpayment)', function () {
        // Buat utang kasbon 300.000
        asTenant($this->owner->tenant, function () {
            Receivable::forceCreate([
                'tenant_id' => $this->owner->tenant_id,
                'customer_id' => $this->joko->id,
                'type' => 'kasbon',
                'amount' => 300_000,
                'paid_amount' => 0,
                'status' => 'open',
                'note' => 'Beli pakan 3 karung',
            ]);
        });

        expect($this->joko->outstandingBalance())->toBe(300_000);
        expect((int) $this->joko->deposit_balance)->toBe(0);

        // Pelanggan transfer 500.000 (lebih 200.000)
        $service = app(ReceivableService::class);
        $remainingDebt = asTenant($this->owner->tenant, fn () => $service->pay($this->joko, 500_000, $this->cash->id, $this->owner));

        expect($remainingDebt)->toBe(0);
        $this->joko->refresh();
        expect($this->joko->outstandingBalance())->toBe(0);
        expect($this->joko->deposit_balance)->toBe(200_000);

        // Mutasi overpayment tercatat
        $overpay = CustomerDeposit::query()->where('customer_id', $this->joko->id)->where('type', 'overpayment')->first();
        expect($overpay)->not->toBeNull()
            ->and($overpay->amount)->toBe(200_000)
            ->and($overpay->balance_after)->toBe(200_000);
    });

    it('bisa memotong utang nota aktif menggunakan saldo titip uang (deduction)', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(250_000, 'deposit', 'Titip uang', 'warehouse');
            Receivable::forceCreate([
                'tenant_id' => $this->owner->tenant_id,
                'customer_id' => $this->joko->id,
                'type' => 'kasbon',
                'amount' => 400_000,
                'paid_amount' => 0,
                'status' => 'open',
                'note' => 'Utang konsentrat',
            ]);
        });

        expect($this->joko->deposit_balance)->toBe(250_000);
        expect($this->joko->outstandingBalance())->toBe(400_000);

        // Potong utang sebesar 200.000 dari deposit
        $this->actingAs($this->owner)
            ->post(route('warehouse.deposits.store'), [
                'customer_id' => $this->joko->id,
                'type' => 'deduction',
                'amount' => 200_000,
                'notes' => 'Potong nota dari deposit',
            ])
            ->assertRedirect();

        $this->joko->refresh();
        expect($this->joko->deposit_balance)->toBe(50_000);
        expect($this->joko->outstandingBalance())->toBe(200_000);
    });

    it('menghasilkan tautan wa.me dan pesan tagihan dengan potongan titip uang dan sisa bersih', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(150_000, 'deposit', 'Titip uang', 'warehouse');
            Receivable::forceCreate([
                'tenant_id' => $this->owner->tenant_id,
                'customer_id' => $this->joko->id,
                'type' => 'kasbon',
                'amount' => 460_000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => now()->toDateString(),
            ]);
        });

        $billService = app(KasbonBillService::class);
        $summary = asTenant($this->owner->tenant, fn () => $billService->summary($this->joko, now()));

        expect($summary['balance'])->toBe(460_000);
        expect($summary['deposit_balance'])->toBe(150_000);
        expect($summary['net_balance'])->toBe(310_000);

        $waMsg = asTenant($this->owner->tenant, fn () => $billService->waBillingMessage($this->joko));
        expect($waMsg)->toContain('Pak Joko')
            ->and($waMsg)->toContain('460.000')
            ->and($waMsg)->toContain('150.000')
            ->and($waMsg)->toContain('Sisa Kewajiban Bersih Anda: Rp310.000');

        $waUrl = asTenant($this->owner->tenant, fn () => $billService->waBillingUrl($this->joko));
        expect($waUrl)->toStartWith('https://wa.me/6281234567890?text=');
    });

    it('halaman public bill menampilkan deposit_balance dan net_balance serta tombol konfirmasi WA', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(150_000, 'deposit', 'Titip uang transfer', 'warehouse');
            Receivable::forceCreate([
                'tenant_id' => $this->owner->tenant_id,
                'customer_id' => $this->joko->id,
                'type' => 'kasbon',
                'amount' => 460_000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => now()->toDateString(),
            ]);
        });

        $billService = app(KasbonBillService::class);
        $url = asTenant($this->owner->tenant, fn () => $billService->billUrl($this->joko));

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Order/Bill')
                ->where('customer', 'Pak Joko')
                ->where('balance', 460_000)
                ->where('deposit_balance', 150_000)
                ->where('net_balance', 310_000)
                ->has('owner_wa_url')
                ->has('deposits', 1)
            );
    });

    it('endpoint riwayat mutasi mengembalikan json audit trail', function () {
        asTenant($this->owner->tenant, function () {
            $this->joko->addDeposit(200_000, 'deposit', 'Setoran 1', 'warehouse');
            $this->joko->addDeposit(50_000, 'withdrawal', 'Tarik 1', 'warehouse');
        });

        $this->actingAs($this->owner)
            ->get(route('warehouse.deposits.history', $this->joko))
            ->assertOk()
            ->assertJsonPath('customer.deposit_balance', 150_000)
            ->assertJsonCount(2, 'deposits');
    });
});

describe('keamanan dan pencegahan idor multi-tenancy', function () {
    it('menolak titip uang untuk pelanggan milik usaha (tenant) lain', function () {
        $otherTenant = registerTenant('toko_kelontong');
        $otherCustomer = makeCustomer($otherTenant, ['name' => 'Pelanggan Toko Sebelah']);

        $this->actingAs($this->owner)
            ->post(route('warehouse.deposits.store'), [
                'customer_id' => $otherCustomer->id,
                'type' => 'deposit',
                'amount' => 100_000,
            ])
            ->assertSessionHasErrors(['customer_id']);

        $otherCustomer->refresh();
        expect((int) $otherCustomer->deposit_balance)->toBe(0);
    });

    it('tidak bisa melihat riwayat mutasi pelanggan usaha lain (IDOR protection)', function () {
        $otherTenant = registerTenant('toko_kelontong');
        $otherCustomer = makeCustomer($otherTenant, ['name' => 'Pelanggan Usaha Lain']);

        $this->actingAs($this->owner)
            ->get(route('warehouse.deposits.history', $otherCustomer))
            ->assertStatus(404);
    });

    it('tidak bisa mengirim wa tagihan untuk pelanggan usaha lain', function () {
        $otherTenant = registerTenant('toko_kelontong');
        $otherCustomer = makeCustomer($otherTenant, ['name' => 'Pelanggan Usaha Lain']);

        $this->actingAs($this->owner)
            ->post(route('warehouse.deposits.wa', $otherCustomer))
            ->assertStatus(404);
    });

    it('karyawan tanpa izin stok (manage_stock) dilarang mengakses modul titip uang gudang', function () {
        $kasir = addStaff($this->owner, 'kasir');

        $this->actingAs($kasir)
            ->get(route('warehouse.deposits'))
            ->assertForbidden();

        $this->actingAs($kasir)
            ->post(route('warehouse.deposits.store'), [
                'customer_id' => $this->joko->id,
                'type' => 'deposit',
                'amount' => 100_000,
            ])
            ->assertForbidden();
    });
});
