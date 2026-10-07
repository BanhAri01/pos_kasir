<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Http\Requests\CustomerRequest;
use App\Modules\Customer\Models\Customer;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Customer\Services\CustomerService;
use App\Modules\Operations\Models\CustomerServiceNote;
use App\Modules\Operations\Services\MembershipService;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    public function index(Request $request): Response
    {
        $this->ensureAllowed($request);
        $term = trim($request->string('q')->toString());
        $phone = Phone::normalize($term);

        $customers = Customer::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->when($phone, fn ($w2) => $w2->orWhere('phone', $phone))))
            ->withCount(['sales' => fn ($q) => $q->where('status', 'completed')])
            ->withSum(['sales' => fn ($q) => $q->where('status', 'completed')], 'total')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers->getCollection()->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => Phone::display($c->phone),
                'sales_count' => (int) $c->sales_count,
                'sales_total' => (int) $c->sales_sum_total,
            ]),
            'pagination' => ['current' => $customers->currentPage(), 'last' => $customers->lastPage(), 'total' => $customers->total()],
            'q' => $term,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureAllowed($request);

        return Inertia::render('Customers/Form', ['customer' => null, ...$this->formOptions()]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = $this->service->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', "{$customer->name} sudah disimpan.");
    }

    public function show(Request $request, Customer $customer, TenantContext $context): Response
    {
        $this->ensureAllowed($request);
        $timezone = $context->get()->timezone;

        $sales = Sale::query()->where('customer_id', $customer->id)->latest('completed_at')->limit(50)->get();
        $tenant = $context->get();
        $membership = $tenant->hasModule('membership')
            ? app(MembershipService::class)->activeFor($customer, $timezone)?->load('plan.product:id,name')
            : null;

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => Phone::display($customer->phone),
                'phone_raw' => $customer->phone,
                'address' => $customer->address,
                'notes' => $customer->notes,
            ],
            'stats' => [
                'count' => $sales->where('status', 'completed')->count(),
                'total' => (int) $sales->where('status', 'completed')->sum('total'),
            ],
            'sales' => $sales->map(fn (Sale $s) => [
                'uuid' => $s->uuid,
                'number' => $s->number,
                'total' => $s->total,
                'status' => $s->status,
                'date' => $s->completed_at?->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
            ]),
            'canSeeSales' => $request->user()->can(Permission::ViewReports->value),
            'priceLevel' => $customer->priceLevel?->name,
            'balance' => $tenant->hasModule('kasbon') || $tenant->hasModule('credit_sales') ? $customer->outstandingBalance() : null,
            'creditLimit' => $customer->credit_limit,
            'membership' => $membership ? [
                'plan' => $membership->plan?->product?->name,
                'ends_on' => $membership->ends_on->locale('id')->translatedFormat('j M Y'),
                'sessions_remaining' => $membership->sessions_remaining,
            ] : null,
            'serviceNotes' => $tenant->hasModule('service_history')
                ? CustomerServiceNote::query()->where('customer_id', $customer->id)->with(['staff:id,name', 'author:id,name'])->latest()->limit(50)->get()
                    ->map(fn ($n) => [
                        'id' => $n->id, 'note' => $n->note, 'staff' => $n->staff?->name, 'author' => $n->author?->name,
                        'date' => $n->created_at->timezone($timezone)->locale('id')->translatedFormat('j M Y'),
                    ])
                : null,
            'staff' => $tenant->hasModule('service_history') ? \App\Models\User::sameTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->ensureAllowed($request);

        return Inertia::render('Customers/Form', ['customer' => [
            'id' => $customer->id, 'name' => $customer->name, 'phone' => Phone::display($customer->phone),
            'address' => $customer->address, 'notes' => $customer->notes,
            'price_level_id' => $customer->price_level_id, 'credit_limit' => $customer->credit_limit,
        ], ...$this->formOptions()]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Data pelanggan sudah disimpan.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureAllowed($request);
        $customer->delete();

        return redirect()->route('customers.index')->with('undo', [
            'message' => "{$customer->name} sudah dihapus.",
            'url' => route('customers.restore', $customer->id),
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $this->ensureAllowed($request);
        $customer = Customer::onlyTrashed()->findOrFail($id);
        $customer->restore();

        return redirect()->route('customers.show', $customer)->with('success', "{$customer->name} sudah dikembalikan.");
    }

    /** Isian tambahan sesuai modul yang aktif (tipe harga, batas kasbon). */
    private function formOptions(): array
    {
        $tenant = app(TenantContext::class)->get();

        return [
            'priceLevels' => $tenant->hasModule('price_levels') ? PriceLevel::query()->orderBy('sort_order')->get(['id', 'name']) : [],
            'showCreditLimit' => $tenant->hasModule('kasbon') || $tenant->hasModule('credit_sales'),
        ];
    }

    private function ensureAllowed(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ManageCustomers->value), 403);
    }
}
