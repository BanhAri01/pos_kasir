<?php

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Models\TenantWallet;
use App\Modules\Billing\Models\WalletMutation;
use App\Modules\Billing\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /** MDR standard rate for QRIS (0.7% = 70 basis points). */
    public const DEFAULT_MDR_BPS = 70;

    /**
     * Catat dana masuk dari transaksi QRIS kasir / online order.
     */
    public function creditQris(
        Tenant $tenant,
        int $grossAmount,
        string $reference,
        ?string $description = null,
        ?int $customFee = null,
        array $meta = []
    ): WalletMutation {
        return DB::transaction(function () use ($tenant, $grossAmount, $reference, $description, $customFee, $meta) {
            $wallet = $tenant->getOrCreateWallet();
            // Lock wallet row for update to prevent race conditions
            $wallet = TenantWallet::withoutGlobalScopes()->where('id', $wallet->id)->lockForUpdate()->first();

            // Hitung potongan biaya MDR (default 0.7%)
            $fee = $customFee ?? (int) round(($grossAmount * self::DEFAULT_MDR_BPS) / 10000);
            $netAmount = max(0, $grossAmount - $fee);

            $newBalance = $wallet->balance + $netAmount;
            $newTotalIncome = $wallet->total_qris_income + $netAmount;

            $wallet->update([
                'balance' => $newBalance,
                'total_qris_income' => $newTotalIncome,
            ]);

            return WalletMutation::create([
                'tenant_id' => $tenant->id,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'category' => 'qris_sale',
                'amount' => $grossAmount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'balance_after' => $newBalance,
                'reference' => $reference,
                'description' => $description ?? "Pembayaran QRIS ({$reference})",
                'meta' => array_merge($meta, ['mdr_fee' => $fee, 'gross' => $grossAmount]),
            ]);
        });
    }

    /**
     * Toko mengajukan penarikan dana (Withdrawal).
     */
    public function requestWithdrawal(
        Tenant $tenant,
        User|int $requester,
        User|int $amount = 0,
        int $adminFee = 0,
        ?string $notes = null
    ): WithdrawalRequest {
        $actualRequester = $requester instanceof User ? $requester : ($amount instanceof User ? $amount : null);
        $actualAmount = is_numeric($requester) ? (int) $requester : (is_numeric($amount) ? (int) $amount : 0);

        if ($actualAmount < 20000) {
            throw ValidationException::withMessages([
                'amount' => 'Minimal penarikan dana adalah Rp 20.000.',
            ]);
        }

        return DB::transaction(function () use ($tenant, $actualRequester, $actualAmount, $adminFee, $notes) {
            $wallet = $tenant->getOrCreateWallet();
            $wallet = TenantWallet::withoutGlobalScopes()->where('id', $wallet->id)->lockForUpdate()->first();

            if (! $wallet->hasBankDetails()) {
                throw ValidationException::withMessages([
                    'bank' => 'Lengkapi data rekening bank pencairan terlebih dahulu.',
                ]);
            }

            if ($wallet->balance < $actualAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo dompet tidak mencukupi untuk penarikan ini.',
                ]);
            }

            $netAmount = max(0, $actualAmount - $adminFee);

            // Kurangi saldo aktif, pindahkan ke pending withdrawal
            $wallet->update([
                'balance' => $wallet->balance - $actualAmount,
                'pending_withdrawal' => $wallet->pending_withdrawal + $actualAmount,
            ]);

            $withdrawal = WithdrawalRequest::create([
                'tenant_id' => $tenant->id,
                'wallet_id' => $wallet->id,
                'amount' => $actualAmount,
                'admin_fee' => $adminFee,
                'net_amount' => $netAmount,
                'bank_name' => (string) $wallet->bank_name,
                'bank_account_number' => (string) $wallet->bank_account_number,
                'bank_account_holder' => (string) $wallet->bank_account_holder,
                'status' => 'pending',
                'requested_by' => $actualRequester?->id,
                'notes' => $notes,
            ]);

            WalletMutation::create([
                'tenant_id' => $tenant->id,
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'category' => 'withdrawal',
                'amount' => $actualAmount,
                'fee' => $adminFee,
                'net_amount' => $netAmount,
                'balance_after' => $wallet->balance,
                'reference' => $withdrawal->uuid,
                'description' => "Pengajuan penarikan dana ke {$wallet->bank_name} - {$wallet->bank_account_number}",
                'meta' => ['withdrawal_uuid' => $withdrawal->uuid],
            ]);

            return $withdrawal;
        });
    }


    /**
     * Admin platform menyetujui / menyelesaikan penarikan dana.
     */
    public function approveWithdrawal(WithdrawalRequest $withdrawal, User $admin, ?string $proof = null): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $admin, $proof) {
            $withdrawal = WithdrawalRequest::withoutGlobalScopes()->where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw new \RuntimeException('Pengajuan penarikan ini sudah diproses.');
            }

            $wallet = TenantWallet::withoutGlobalScopes()->where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();

            $wallet->update([
                'pending_withdrawal' => max(0, $wallet->pending_withdrawal - $withdrawal->amount),
            ]);

            $withdrawal->update([
                'status' => 'approved',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'transfer_proof' => $proof,
            ]);

            return $withdrawal;
        });
    }

    public function rejectWithdrawal(
        WithdrawalRequest $withdrawal,
        User|string $admin,
        User|string $reason = ''
    ): WithdrawalRequest {
        $actualAdmin = $admin instanceof User ? $admin : ($reason instanceof User ? $reason : null);
        $actualReason = is_string($admin) ? $admin : (string) $reason;

        return DB::transaction(function () use ($withdrawal, $actualAdmin, $actualReason) {


            $withdrawal = WithdrawalRequest::withoutGlobalScopes()->where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw new \RuntimeException('Pengajuan penarikan ini sudah diproses.');
            }

            $wallet = TenantWallet::withoutGlobalScopes()->where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();

            // Kembalikan saldo dari pending ke balance
            $newBalance = $wallet->balance + $withdrawal->amount;
            $wallet->update([
                'balance' => $newBalance,
                'pending_withdrawal' => max(0, $wallet->pending_withdrawal - $withdrawal->amount),
            ]);

            $withdrawal->update([
                'status' => 'rejected',
                'rejection_reason' => $actualReason,
                'processed_by' => $actualAdmin?->id,
                'processed_at' => now(),
            ]);

            WalletMutation::create([
                'tenant_id' => $withdrawal->tenant_id,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'category' => 'withdrawal_refund',
                'amount' => $withdrawal->amount,
                'fee' => 0,
                'net_amount' => $withdrawal->amount,
                'balance_after' => $newBalance,
                'reference' => $withdrawal->uuid,
                'description' => "Pengembalian dana penarikan ditolak: {$actualReason}",
                'meta' => ['withdrawal_uuid' => $withdrawal->uuid, 'reason' => $actualReason],
            ]);


            return $withdrawal;
        });
    }
}
