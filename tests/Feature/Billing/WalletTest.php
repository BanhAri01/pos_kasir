<?php

use App\Models\Tenant;
use App\Modules\Billing\Models\TenantWallet;
use App\Modules\Billing\Models\WalletMutation;
use App\Modules\Billing\Models\WithdrawalRequest;
use App\Modules\Billing\Services\WalletService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    app(\App\Core\Tenancy\TenantContext::class)->set($this->tenant);
    $this->walletService = app(WalletService::class);
});


describe('uji fitur dompet toko & tarik saldo (model moka pay)', function () {
    it('membuat dompet otomatis saat pertama kali diakses', function () {
        $wallet = $this->tenant->getOrCreateWallet();

        expect($wallet)->toBeInstanceOf(TenantWallet::class)
            ->and($wallet->balance)->toBe(0)
            ->and($wallet->total_qris_income)->toBe(0)
            ->and($wallet->pending_withdrawal)->toBe(0);
    });

    it('mencatat penerimaan qris dan memotong mdr 0.7 persen dengan benar', function () {
        // Pembayaran QRIS Rp 100.000
        $gross = 100000;
        $expectedFee = 700; // 0.7% (70 bps)
        $expectedNet = 99300;

        $mutation = $this->walletService->creditQris(
            tenant: $this->tenant,
            grossAmount: $gross,
            reference: 'POS-QRIS-001',
            description: 'Penjualan Kopi Susu via QRIS'
        );

        $wallet = $this->tenant->wallet()->first();

        expect($mutation)->toBeInstanceOf(WalletMutation::class)
            ->and($mutation->gross_amount)->toBe($gross)
            ->and($mutation->fee_amount)->toBe($expectedFee)
            ->and($mutation->net_amount)->toBe($expectedNet)
            ->and($wallet->balance)->toBe($expectedNet)
            ->and($wallet->total_qris_income)->toBe($expectedNet);
    });

    it('dapat memperbarui informasi rekening bank tujuan pencairan', function () {
        $this->actingAs($this->owner)->post(route('wallet.bank'), [
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Budi Santoso',
        ])->assertSessionHasNoErrors();

        $wallet = $this->tenant->wallet()->first();

        expect($wallet->bank_name)->toBe('BCA')
            ->and($wallet->bank_account_number)->toBe('1234567890')
            ->and($wallet->bank_account_name)->toBe('Budi Santoso');
    });

    it('menolak penarikan saldo jika melebihi saldo tersedia atau rekening belum lengkap', function () {
        // Rekening belum diisi
        $this->actingAs($this->owner)->post(route('wallet.withdraw'), [
            'amount' => 50000,
        ])->assertSessionHasErrors(['bank']);

        // Isi rekening
        $wallet = $this->tenant->getOrCreateWallet();
        $wallet->update([
            'bank_name' => 'Mandiri',
            'bank_account_number' => '987654321',
            'bank_account_name' => 'Warung Kopi',
            'balance' => 30000,
        ]);

        // Minta tarik Rp 50.000 (saldo hanya 30.000)
        $this->actingAs($this->owner)->post(route('wallet.withdraw'), [
            'amount' => 50000,
        ])->assertSessionHasErrors(['amount']);
    });

    it('memproses pengajuan penarikan dana dan mengunci saldo ke pending_withdrawal', function () {
        $wallet = $this->tenant->getOrCreateWallet();
        $wallet->update([
            'bank_name' => 'BRI',
            'bank_account_number' => '0011223344',
            'bank_account_name' => 'Owner Kopi',
            'balance' => 200000,
        ]);

        $this->actingAs($this->owner)->post(route('wallet.withdraw'), [
            'amount' => 150000,
        ])->assertSessionHasNoErrors();

        $wallet->refresh();

        expect($wallet->balance)->toBe(50000)
            ->and($wallet->pending_withdrawal)->toBe(150000);

        $request = WithdrawalRequest::where('tenant_id', $this->tenant->id)->first();
        expect($request)->not->toBeNull()
            ->and($request->amount)->toBe(150000)
            ->and($request->status)->toBe('pending');
    });

    it('dapat menyetujui penarikan saldo dan mencatat mutasi debet', function () {
        $wallet = $this->tenant->getOrCreateWallet();
        $wallet->update([
            'bank_name' => 'BNI',
            'bank_account_number' => '55667788',
            'bank_account_name' => 'Warung',
            'balance' => 100000,
        ]);

        $req = $this->walletService->requestWithdrawal($this->tenant, 100000, $this->owner);
        expect($wallet->fresh()->balance)->toBe(0)
            ->and($wallet->fresh()->pending_withdrawal)->toBe(100000);

        $this->walletService->approveWithdrawal($req, $this->owner, 'TRX-TRANSFER-999');

        $wallet->refresh();
        $req->refresh();

        expect($wallet->pending_withdrawal)->toBe(0)
            ->and($wallet->balance)->toBe(0)
            ->and($req->status)->toBe('approved')
            ->and($req->transfer_reference)->toBe('TRX-TRANSFER-999');

        $mutation = WalletMutation::where('category', 'withdrawal')->first();
        expect($mutation)->not->toBeNull()
            ->and($mutation->type)->toBe('debit')
            ->and($mutation->net_amount)->toBe(100000);
    });

    it('mengembalikan saldo saat penarikan ditolak', function () {
        $wallet = $this->tenant->getOrCreateWallet();
        $wallet->update([
            'bank_name' => 'BCA',
            'bank_account_number' => '112233',
            'bank_account_name' => 'Budi',
            'balance' => 100000,
        ]);

        $req = $this->walletService->requestWithdrawal($this->tenant, 75000, $this->owner);
        expect($wallet->fresh()->balance)->toBe(25000)
            ->and($wallet->fresh()->pending_withdrawal)->toBe(75000);

        $this->walletService->rejectWithdrawal($req, 'Nama rekening dan KTP tidak cocok', $this->owner);

        $wallet->refresh();
        $req->refresh();

        expect($wallet->balance)->toBe(100000)
            ->and($wallet->pending_withdrawal)->toBe(0)
            ->and($req->status)->toBe('rejected')
            ->and($req->reject_reason)->toBe('Nama rekening dan KTP tidak cocok');
    });

    it('dapat memuat halaman dompet dengan komponen inertia yang lengkap', function () {
        $this->actingAs($this->owner)->get(route('wallet.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/Wallet')
                ->has('wallet')
                ->has('mutations')
                ->has('withdrawals')
            );
    });
});
