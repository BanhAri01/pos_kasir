<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivablePayment;
use App\Modules\Operations\Models\ReceivableProof;
use App\Modules\Operations\Services\KasbonBillService;
use App\Modules\Operations\Services\ReceivableService;
use App\Modules\Pos\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kasbon & piutang: siapa saja yang masih berutang, catat utang manual, terima cicilan, kirim pengingat. */
class ReceivableController extends Controller
{
    public function __construct(private ReceivableService $service, private TenantContext $context, private KasbonBillService $bills) {}

    public function index(Request $request): Response
    {
        $canReview = $request->user()->can('view_reports');

        $term = trim($request->string('q')->toString());

        $rows = Receivable::query()->where('status', 'open')
            ->selectRaw('customer_id, SUM(amount - paid_amount) as balance, MIN(due_date) as nearest_due, MAX(last_reminded_at) as reminded_at, MIN(created_at) as oldest')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $customers = Customer::query()->whereIn('id', $rows->keys())
            ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => Phone::display($c->phone),
                'has_phone' => (bool) $c->phone,
                'balance' => (int) $rows[$c->id]->balance,
                'credit_limit' => $c->credit_limit,
                'nearest_due' => $rows[$c->id]->nearest_due,
                'overdue' => $rows[$c->id]->nearest_due && $rows[$c->id]->nearest_due < now($this->context->get()->timezone)->toDateString(),
            ])
            ->sortByDesc('balance')
            ->values();

        return Inertia::render('Operations/Receivables', [
            'customers' => $customers,
            'total' => (int) $customers->sum('balance'),
            'q' => $term,
            'allCustomers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'pendingProofs' => $canReview ? $this->bills->pending()->map(fn (ReceivableProof $p) => $this->proofRow($p)) : [],
            'billSettings' => KasbonBillService::settings($this->context->get()),
            'canConfigure' => $request->user()->can('manage_business'),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $canReview = $request->user()->can('view_reports');

        $timezone = $this->context->get()->timezone;
        $format = fn ($d) => $d?->timezone($timezone)->locale('id')->translatedFormat('j M Y');

        $receivables = Receivable::query()->where('customer_id', $customer->id)->whereIn('status', ['open', 'paid'])->with('sale:id,uuid,number')->latest()->limit(100)->get();
        $payments = ReceivablePayment::query()->whereIn('receivable_id', $receivables->pluck('id'))->with('user:id,name')->latest('paid_at')->limit(100)->get();

        return Inertia::render('Operations/ReceivableShow', [
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'phone' => Phone::display($customer->phone), 'has_phone' => (bool) $customer->phone, 'credit_limit' => $customer->credit_limit],
            'balance' => $customer->outstandingBalance(),
            'receivables' => $receivables->map(fn ($r) => [
                'id' => $r->id, 'type' => $r->type, 'amount' => $r->amount, 'paid_amount' => $r->paid_amount, 'status' => $r->status,
                'note' => $r->note, 'sale_uuid' => $r->sale?->uuid, 'date' => $format($r->created_at), 'due_date' => $r->due_date?->locale('id')->translatedFormat('j M Y'),
            ]),
            'payments' => $payments->map(fn ($p) => ['amount' => $p->amount, 'date' => $format($p->paid_at), 'user' => $p->user?->name, 'method' => $p->method_type]),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'billing' => [
                'day' => $customer->billing_day,
                'link' => $this->bills->billUrl($customer),
                'auto' => KasbonBillService::settings($this->context->get())['auto'],
            ],
            'proofs' => $canReview
                ? ReceivableProof::query()->where('customer_id', $customer->id)->with(['reviewer:id,name', 'customer:id,name'])->latest()->limit(20)->get()
                    ->map(fn (ReceivableProof $p) => $this->proofRow($p))
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ], ['customer_id.required' => 'Pilih pelanggan dulu.', 'amount.required' => 'Isi jumlah utangnya.']);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $this->service->recordManual($customer, $data['amount'], $data['note'] ?? null, $data['due_date'] ?? null, $this->context->get()->timezone);

        return redirect()->route('receivables.show', $customer)->with('success', "Utang {$customer->name} sudah dicatat.");
    }

    public function pay(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer'],
        ], ['amount.required' => 'Isi jumlah yang dibayar.']);

        $remaining = $this->service->pay($customer, $data['amount'], $data['payment_method_id'] ?? null, $request->user());

        return back()->with('success', $remaining > 0 ? 'Pembayaran dicatat. Sisa utang '.\App\Core\Support\Rupiah::format($remaining).'.' : 'Lunas! Utang sudah dibayar semua.');
    }

    public function remind(Customer $customer): RedirectResponse
    {
        if ($customer->outstandingBalance() <= 0 || ! $customer->phone) {
            return back()->with('error', 'Pelanggan ini belum punya no HP, atau tidak punya utang.');
        }
        if (! \App\Modules\WhatsApp\Services\WhatsAppService::isEnabled($this->context->get(), 'kasbon_reminder')) {
            return back()->with('error', 'Pesan "Tagihan kasbon" sedang dimatikan. Nyalakan di Pengaturan > WhatsApp.');
        }

        return $this->bills->send($customer)
            ? back()->with('success', "Tagihan sudah dikirim ke WhatsApp {$customer->name}.")
            : back()->with('error', 'Tagihan belum terkirim. Kuota WhatsApp bulan ini mungkin sudah habis.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto' => ['boolean'],
            'payment_info' => ['nullable', 'string', 'max:500'],
        ], ['payment_info.max' => 'Info pembayaran maksimal 500 huruf.']);

        $this->bills->saveSettings($this->context->get(), (bool) ($data['auto'] ?? false), $data['payment_info'] ?? null);

        return back()->with('success', 'Pengaturan tagihan disimpan.');
    }

    public function billingDay(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'billing_day' => ['nullable', 'integer', 'between:1,28'],
        ], ['billing_day.between' => 'Pilih tanggal 1 sampai 28.']);

        $this->bills->setBillingDay($customer, $data['billing_day'] ?? null);

        return back()->with('success', isset($data['billing_day'])
            ? "Tagihan {$customer->name} jatuh tempo tiap tanggal {$data['billing_day']}."
            : "Tanggal tagih {$customer->name} dihapus.");
    }

    public function resetLink(Customer $customer): RedirectResponse
    {
        $this->bills->resetLink($customer);

        return back()->with('success', 'Link tagihan lama sudah tidak berlaku. Link baru sudah dibuat.');
    }

    public function proofFile(ReceivableProof $proof): StreamedResponse
    {
        abort_unless(Storage::disk(KasbonBillService::DISK)->exists($proof->file_path), 404);

        return Storage::disk(KasbonBillService::DISK)->response($proof->file_path, 'bukti-'.$proof->id.($proof->isPdf() ? '.pdf' : '.jpg'), [
            'Content-Type' => $proof->file_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function approveProof(Request $request, ReceivableProof $proof): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer'],
        ], ['amount.required' => 'Isi jumlah yang diterima.']);

        $remaining = $this->bills->approve($proof, $request->user(), $data['amount'], $data['payment_method_id'] ?? null);

        return back()->with('success', $remaining > 0 ? 'Pembayaran diterima. Sisa utang '.\App\Core\Support\Rupiah::format($remaining).'.' : 'Pembayaran diterima. Utang sudah lunas!');
    }

    public function rejectProof(Request $request, ReceivableProof $proof): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ], ['reason.required' => 'Tulis alasannya supaya pelanggan tahu harus apa.']);

        $this->bills->reject($proof, $request->user(), trim($data['reason']));

        return back()->with('success', 'Bukti ditolak dan pelanggan sudah diberi tahu.');
    }

    private function proofRow(ReceivableProof $proof): array
    {
        return [
            'id' => $proof->id,
            'customer_id' => $proof->customer_id,
            'customer' => $proof->customer?->name,
            'amount' => $proof->amount,
            'approved_amount' => $proof->approved_amount,
            'note' => $proof->note,
            'status' => $proof->status,
            'reject_reason' => $proof->reject_reason,
            'is_pdf' => $proof->isPdf(),
            'file_url' => route('receivables.proofs.file', $proof),
            'date' => $proof->created_at->timezone($this->context->get()->timezone)->locale('id')->translatedFormat('j M Y, H:i'),
            'reviewer' => $proof->relationLoaded('reviewer') ? $proof->reviewer?->name : null,
        ];
    }
}
