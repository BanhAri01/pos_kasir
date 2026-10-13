<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\TenantWallet;
use App\Modules\Billing\Models\WalletMutation;
use App\Modules\Billing\Models\WithdrawalRequest;
use App\Modules\Billing\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $tenant = $context->get();
        $wallet = $tenant->getOrCreateWallet();

        $mutations = WalletMutation::query()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->take(30)
            ->get()
            ->map(fn (WalletMutation $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'category' => $m->category,
                'amount' => $m->amount,
                'fee' => $m->fee,
                'net_amount' => $m->net_amount,
                'balance_after' => $m->balance_after,
                'reference' => $m->reference,
                'description' => $m->description,
                'created_at' => $m->created_at?->translatedFormat('d M Y, H:i'),
            ]);

        $withdrawals = WithdrawalRequest::query()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn (WithdrawalRequest $w) => [
                'id' => $w->id,
                'uuid' => $w->uuid,
                'amount' => $w->amount,
                'admin_fee' => $w->admin_fee,
                'net_amount' => $w->net_amount,
                'bank_name' => $w->bank_name,
                'bank_account_number' => $w->bank_account_number,
                'bank_account_holder' => $w->bank_account_holder,
                'status' => $w->status,
                'rejection_reason' => $w->rejection_reason,
                'transfer_proof' => $w->transfer_proof,
                'created_at' => $w->created_at?->translatedFormat('d M Y, H:i'),
                'processed_at' => $w->processed_at?->translatedFormat('d M Y, H:i'),
            ]);

        return Inertia::render('Billing/Wallet', [
            'wallet' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'total_qris_income' => $wallet->total_qris_income,
                'pending_withdrawal' => $wallet->pending_withdrawal,
                'bank_name' => $wallet->bank_name,
                'bank_account_number' => $wallet->bank_account_number,
                'bank_account_holder' => $wallet->bank_account_holder,
                'has_bank' => $wallet->hasBankDetails(),
            ],
            'mutations' => $mutations,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function updateBank(Request $request, TenantContext $context): RedirectResponse
    {
        $holder = $request->input('bank_account_holder') ?? $request->input('bank_account_name');
        if (blank($holder)) {
            throw ValidationException::withMessages([
                'bank_account_holder' => 'Nama pemilik rekening wajib diisi.',
                'bank_account_name' => 'Nama pemilik rekening wajib diisi.',
            ]);
        }

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:64'],
            'bank_account_number' => ['required', 'string', 'max:64'],
        ], [
            'bank_name.required' => 'Pilih atau tulis nama bank tujuan.',
            'bank_account_number.required' => 'Nomor rekening wajib diisi.',
        ]);

        $wallet = $context->get()->getOrCreateWallet();
        $wallet->update([
            'bank_name' => $data['bank_name'],
            'bank_account_number' => $data['bank_account_number'],
            'bank_account_holder' => $holder,
        ]);

        return back()->with('success', 'Rekening bank pencairan berhasil disimpan.');
    }

    public function withdraw(Request $request, TenantContext $context, WalletService $wallets): RedirectResponse
    {
        $wallet = $context->get()->getOrCreateWallet();

        if (! $wallet->hasBankDetails()) {
            throw ValidationException::withMessages([
                'bank' => 'Lengkapi data rekening bank pencairan terlebih dahulu.',
            ]);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:20000', "max:{$wallet->balance}"],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.min' => 'Minimal penarikan dana adalah Rp 20.000.',
            'amount.max' => 'Jumlah penarikan melebihi saldo dompet aktif.',
        ]);

        $wallets->requestWithdrawal(
            tenant: $context->get(),
            requester: $request->user(),
            amount: (int) $data['amount'],
            adminFee: 0,
            notes: $data['notes'] ?? null
        );

        return back()->with('success', 'Pengajuan penarikan dana berhasil dibuat dan sedang diproses.');
    }

}
