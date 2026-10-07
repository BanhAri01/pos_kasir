<?php

namespace App\Modules\Expense\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Services\ExpenseService;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pengeluaran: catat cepat (jumlah + jenis), lihat per bulan, hapus kalau salah. */
class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $service, private CurrentOutlet $outlet, private TenantContext $context) {}

    public function index(Request $request): Response
    {
        $this->service->ensureDefaultCategories();
        $timezone = $this->context->get()->timezone;
        $month = Carbon::parse(($request->string('bulan')->toString() ?: now($timezone)->format('Y-m')).'-01', $timezone);
        $outletId = $this->outlet->id($request->user());

        $expenses = Expense::query()
            ->where('outlet_id', $outletId)
            ->whereDate('spent_on', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('spent_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->with(['category:id,name', 'user:id,name', 'paymentMethod:id,name'])
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses->map(fn (Expense $e) => [
                'id' => $e->id,
                'date' => $e->spent_on->locale('id')->translatedFormat('D, j M'),
                'category' => $e->category?->name ?? 'Lain-lain',
                'amount' => $e->amount,
                'note' => $e->note,
                'method' => $e->cash_movement_id ? 'Laci kasir' : $e->paymentMethod?->name,
                'user' => $e->user?->name,
            ]),
            'byCategory' => $expenses->groupBy(fn ($e) => $e->category?->name ?? 'Lain-lain')
                ->map(fn ($rows, $name) => ['name' => $name, 'total' => (int) $rows->sum('amount')])
                ->sortByDesc('total')->values(),
            'total' => (int) $expenses->sum('amount'),
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
            'categories' => ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->where('type', '!=', 'kasbon')->orderBy('sort_order')->get(['id', 'name']),
            'drawerOpen' => Shift::query()->where('outlet_id', $outletId)->where('status', 'open')->exists(),
            'today' => now($timezone)->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->context->id();
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'new_category' => ['nullable', 'string', 'max:60'],
            'spent_on' => ['required', 'date', 'before_or_equal:'.now($this->context->get()->timezone)->addDay()->toDateString()],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)],
            'from_cash_drawer' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Isi jumlah uang yang dikeluarkan.',
            'amount.min' => 'Jumlah harus lebih dari 0.',
            'spent_on.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
        ]);

        $expense = $this->service->create([...$data, 'outlet_id' => $this->outlet->id($request->user())], $request->user());

        return back()->with('success', 'Pengeluaran '.\App\Core\Support\Rupiah::format($expense->amount).' sudah dicatat.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->service->delete($expense);

        return back()->with('success', 'Pengeluaran dihapus.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('expense_categories', 'name')->where('tenant_id', $this->context->id())]], [
            'name.unique' => 'Jenis pengeluaran ini sudah ada.',
        ]);
        ExpenseCategory::create([...$data, 'sort_order' => (int) ExpenseCategory::query()->max('sort_order') + 1]);

        return back()->with('success', "Jenis \"{$data['name']}\" ditambahkan.");
    }
}
