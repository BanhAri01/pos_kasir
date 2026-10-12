<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivableProof;
use App\Modules\Operations\Services\KasbonBillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicBillController extends Controller
{
    public function __construct(private KasbonBillService $bills, private TenantContext $context) {}

    public function show(string $token): Response
    {
        [$customer, $tenant] = $this->resolve($token);

        return $this->context->runAs($tenant, function () use ($customer, $tenant, $token) {
            $today = now($tenant->timezone)->startOfDay();
            $summary = $this->bills->summary($customer, $today);
            $date = fn ($d) => $d?->locale('id')->translatedFormat('j M Y');

            $ownerWaUrl = null;
            if ($tenant->phone) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $tenant->phone);
                if (str_starts_with($cleanPhone, '08')) {
                    $cleanPhone = '628'.substr($cleanPhone, 2);
                } elseif (str_starts_with($cleanPhone, '0')) {
                    $cleanPhone = '62'.substr($cleanPhone, 1);
                }
                $waText = rawurlencode("Halo {$tenant->name}, saya {$customer->name} ingin konfirmasi pembayaran tagihan saya (Sisa Kewajiban Bersih: ".Rupiah::format($summary['net_balance']).").");
                $ownerWaUrl = "https://wa.me/{$cleanPhone}?text={$waText}";
            }

            $deposits = $customer->deposits()->latest()->limit(5)->get()->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'type_label' => $d->type_label,
                'amount' => $d->amount,
                'balance_after' => $d->balance_after,
                'notes' => $d->notes,
                'date' => $date($d->created_at->timezone($tenant->timezone)),
            ]);

            return Inertia::render('Order/Bill', [
                'token' => $token,
                'business' => [
                    'name' => $tenant->name,
                    'phone' => Phone::display($tenant->phone),
                    'address' => $tenant->address,
                    'logo_url' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null,
                ],
                'customer' => $customer->name,
                'today' => $date($today),
                'balance' => $summary['balance'],
                'due' => $summary['due'],
                'deposit_balance' => $summary['deposit_balance'],
                'net_balance' => $summary['net_balance'],
                'net_due' => $summary['net_due'],
                'owner_phone' => Phone::display($tenant->phone),
                'owner_wa_url' => $ownerWaUrl,
                'deposits' => $deposits,
                'items' => $summary['open']->map(fn (Receivable $r) => [
                    'id' => $r->id,
                    'label' => $r->sale ? 'Nota '.$r->sale->number : ($r->note ?: 'Kasbon'),
                    'date' => $date($r->created_at->timezone($tenant->timezone)),
                    'due_date' => $date($r->due_date),
                    'overdue' => $r->due_date !== null && $r->due_date->lt($today),
                    'amount' => $r->amount,
                    'paid' => $r->paid_amount,
                    'remaining' => $r->amount - $r->paid_amount,
                    'receipt_url' => $r->sale?->receiptUrl(),
                ])->values(),
                'paymentInfo' => KasbonBillService::settings($tenant)['payment_info'],
                'proofs' => ReceivableProof::query()->where('customer_id', $customer->id)->latest()->limit(5)->get()
                    ->map(fn (ReceivableProof $p) => [
                        'id' => $p->id,
                        'amount' => $p->approved_amount ?? $p->amount,
                        'status' => $p->status,
                        'reason' => $p->reject_reason,
                        'date' => $date($p->created_at->timezone($tenant->timezone)),
                    ]),
            ]);
        });
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        [$customer, $tenant] = $this->resolve($token);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            'note' => ['nullable', 'string', 'max:300'],
        ], [
            'amount.required' => 'Isi jumlah yang dibayar.',
            'file.required' => 'Lampirkan foto atau PDF bukti pembayaran.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
            'file.mimes' => 'Kirim foto (JPG/PNG) atau PDF.',
        ]);

        $this->context->runAs($tenant, fn () => $this->bills->submitProof($customer, $data['amount'], $data['file'], $data['note'] ?? null, $request->ip()));

        return back()->with('success', 'Terima kasih! Bukti pembayaran sudah terkirim dan akan dicek oleh '.$tenant->name.'.');
    }

    private function resolve(string $token): array
    {
        $customer = Customer::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('bill_token', $token)->first();
        abort_unless($customer, 404);

        $tenant = Tenant::query()->findOrFail($customer->tenant_id);
        abort_if($tenant->isSuspended() || ! $tenant->hasModule('kasbon'), 404);

        return [$customer, $tenant];
    }
}
