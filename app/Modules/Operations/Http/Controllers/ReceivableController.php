<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivablePayment;
use App\Modules\Operations\Services\ReceivableService;
use App\Modules\Pos\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Kasbon & piutang: siapa saja yang masih berutang, catat utang manual, terima cicilan, kirim pengingat. */
class ReceivableController extends Controller
{
    public function __construct(private ReceivableService $service, private TenantContext $context) {}

    public function index(Request $request): Response
    {
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
        ]);
    }

    public function show(Customer $customer): Response
    {
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
        $this->service->recordManual($customer, $data['amount'], $data['note'] ?? null, $data['due_date'] ?? null);

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
        return $this->service->remind($customer)
            ? back()->with('success', "Pengingat sudah dikirim ke WhatsApp {$customer->name}.")
            : back()->with('error', 'Pelanggan ini belum punya no HP, atau tidak punya utang.');
    }
}
