<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Services\KasbonBillService;
use App\Modules\Operations\Services\ReceivableService;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Warehouse\Models\CustomerDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseDepositController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private KasbonBillService $bills,
        private ReceivableService $receivables,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->context->get();
        $today = now($tenant->timezone)->startOfDay();
        $tab = $request->query('tab', 'all');
        $search = trim($request->string('q')->toString());

        $receivableRows = Receivable::query()
            ->where('status', 'open')
            ->selectRaw('customer_id, SUM(amount - paid_amount) as gross_balance, MIN(due_date) as nearest_due, COUNT(id) as open_count')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $activeCustomerIds = Customer::query()
            ->where(function ($q) use ($receivableRows) {
                $q->where('deposit_balance', '>', 0)
                    ->orWhereIn('id', $receivableRows->keys());
            })
            ->pluck('id');

        $query = Customer::query()->with([
            'deposits' => fn ($q) => $q->latest()->limit(3),
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        } else {
            $query->whereIn('id', $activeCustomerIds);
        }

        $allMatching = $query->get();

        $date = fn ($d) => $d?->locale('id')->translatedFormat('j M Y');

        $customers = $allMatching->map(function (Customer $c) use ($receivableRows, $today, $date) {
            $rec = $receivableRows->get($c->id);
            $grossDebt = (int) ($rec?->gross_balance ?? 0);
            $deposit = (int) ($c->deposit_balance ?? 0);
            $netDebt = max(0, $grossDebt - $deposit);
            $surplus = max(0, $deposit - $grossDebt);
            $nearestDueDate = $rec?->nearest_due ? Carbon::parse($rec->nearest_due) : null;
            $isOverdue = $nearestDueDate !== null && $nearestDueDate->lt($today);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'formatted_phone' => Phone::display($c->phone),
                'deposit_balance' => $deposit,
                'gross_debt' => $grossDebt,
                'net_debt' => $netDebt,
                'surplus' => $surplus,
                'open_invoices' => (int) ($rec?->open_count ?? 0),
                'nearest_due' => $date($nearestDueDate),
                'is_overdue' => $isOverdue,
                'bill_url' => $this->bills->billUrl($c),
                'wa_billing_url' => $this->bills->waBillingUrl($c),
                'recent_deposits' => $c->deposits->map(fn (CustomerDeposit $d) => [
                    'id' => $d->id,
                    'type' => $d->type,
                    'type_label' => $d->type_label,
                    'amount' => $d->amount,
                    'balance_after' => $d->balance_after,
                    'notes' => $d->notes,
                    'date' => $date($d->created_at),
                ]),
            ];
        });

        $filtered = match ($tab) {
            'has_deposit' => $customers->filter(fn ($c) => $c['deposit_balance'] > 0),
            'has_debt' => $customers->filter(fn ($c) => $c['net_debt'] > 0),
            'overdue' => $customers->filter(fn ($c) => $c['is_overdue']),
            default => $customers,
        };

        $sortedCustomers = $filtered->sortByDesc(fn ($c) => $c['deposit_balance'] * 10 + $c['gross_debt'])->values();

        $totalDeposit = (int) Customer::query()->sum('deposit_balance');
        $totalGrossDebt = (int) Receivable::query()->where('status', 'open')->sum(DB::raw('amount - paid_amount'));
        $totalNetDebt = max(0, $totalGrossDebt - $totalDeposit);
        $countDepositCustomers = Customer::query()->where('deposit_balance', '>', 0)->count();
        $countDebtCustomers = $receivableRows->count();

        $allCustomersList = Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'deposit_balance']);
        $paymentMethods = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type']);

        return Inertia::render('Warehouse/Deposits', [
            'customers' => $sortedCustomers,
            'stats' => [
                'total_deposit' => $totalDeposit,
                'total_gross_debt' => $totalGrossDebt,
                'total_net_debt' => $totalNetDebt,
                'count_deposit_customers' => $countDepositCustomers,
                'count_debt_customers' => $countDebtCustomers,
            ],
            'filters' => [
                'tab' => $tab,
                'q' => $search,
            ],
            'allCustomers' => $allCustomersList,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->context->id();
        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', 'in:deposit,withdrawal,deduction'],
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
        ], [
            'customer_id.required' => 'Pilih pelanggan terlebih dahulu.',
            'amount.required' => 'Masukkan jumlah uang.',
            'amount.min' => 'Nominal minimal Rp 1.',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            $user = $request->user();

            if ($data['type'] === 'deposit') {
                $customer->addDeposit(
                    amount: $data['amount'],
                    type: 'deposit',
                    notes: $data['notes'] ?: 'Titip uang tunai / transfer ke gudang',
                    source: 'warehouse',
                    userId: $user?->id
                );

                return back()->with('success', 'Titip uang sebesar '.Rupiah::format($data['amount'])." untuk {$customer->name} berhasil dicatat.");
            }

            if ($data['type'] === 'withdrawal') {
                if ($customer->deposit_balance < $data['amount']) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo titip uang pelanggan tidak mencukupi (sisa saldo: '.Rupiah::format($customer->deposit_balance).').',
                    ]);
                }

                $customer->addDeposit(
                    amount: $data['amount'],
                    type: 'withdrawal',
                    notes: $data['notes'] ?: 'Penarikan titip uang pelanggan',
                    source: 'warehouse',
                    userId: $user?->id
                );

                return back()->with('success', 'Penarikan titip uang sebesar '.Rupiah::format($data['amount']).' berhasil dicatat.');
            }

            if ($data['type'] === 'deduction') {
                if ($customer->deposit_balance < $data['amount']) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo titip uang pelanggan tidak mencukupi untuk pemotongan utang.',
                    ]);
                }

                $openDebt = $customer->outstandingBalance();
                if ($openDebt <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Pelanggan ini tidak memiliki nota utang aktif untuk dipotong.',
                    ]);
                }

                $deductAmount = min($data['amount'], $openDebt);

                $this->receivables->pay(
                    customer: $customer,
                    amount: $deductAmount,
                    paymentMethodId: null,
                    user: $user,
                    fromDeposit: true,
                );

                $customer->addDeposit(
                    amount: $deductAmount,
                    type: 'deduction',
                    notes: $data['notes'] ?: 'Potong utang nota aktif dari saldo titip uang',
                    source: 'warehouse',
                    userId: $user?->id
                );

                return back()->with('success', "Utang nota {$customer->name} berhasil dipotong ".Rupiah::format($deductAmount).' dari saldo titip uang.');
            }

            return back();
        });
    }

    public function sendWa(Customer $customer): RedirectResponse
    {
        abort_if($customer->tenant_id !== $this->context->id(), 403);

        if (! $customer->phone) {
            return back()->with('error', "Pelanggan {$customer->name} belum memiliki nomor telepon WhatsApp.");
        }

        $sent = $this->bills->send($customer);

        if ($sent) {
            return back()->with('success', "Pesan tagihan berhasil dijadwalkan / dikirim ke WhatsApp {$customer->name}.");
        }

        return back()->with('error', 'Gagal mengirim pesan tagihan ke WhatsApp. Pastikan fitur WhatsApp aktif.');
    }

    public function history(Customer $customer): JsonResponse
    {
        abort_if($customer->tenant_id !== $this->context->id(), 403);

        $tenant = $this->context->get();
        $date = fn ($d) => $d?->timezone($tenant->timezone)->locale('id')->translatedFormat('j M Y H:i');

        $deposits = $customer->deposits()
            ->with('creator:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CustomerDeposit $d) => [
                'id' => $d->id,
                'type' => $d->type,
                'type_label' => $d->type_label,
                'amount' => $d->amount,
                'balance_after' => $d->balance_after,
                'notes' => $d->notes,
                'source' => $d->source,
                'created_by' => $d->creator?->name,
                'date' => $date($d->created_at),
            ]);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'deposit_balance' => (int) $customer->deposit_balance,
            ],
            'deposits' => $deposits,
        ]);
    }
}
