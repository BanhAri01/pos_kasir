<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Support\Phone;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Services\PlanGuard;
use App\Modules\Billing\Services\Plans;
use App\Modules\Billing\Services\SubscriptionBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminTenantController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('status', 'semua');
        $digits = Phone::normalize($search);

        $tenants = Tenant::query()
            ->with(['owner:id,name,phone', 'businessType:id,name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.($digits ?: $search).'%')
                ->orWhereHas('owner', fn ($o) => $o->where('phone', 'like', '%'.($digits ?: $search).'%')->orWhere('name', 'like', '%'.$search.'%'))))
            ->when($filter === 'coba', fn ($q) => $q->where('status', 'trial'))
            ->when($filter === 'aktif', fn ($q) => $q->where('status', 'active'))
            ->when($filter === 'beku', fn ($q) => $q->where('status', 'suspended'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $tenants->getCollection()->transform(fn (Tenant $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'type' => $t->businessType?->name,
            'owner' => $t->owner?->name,
            'phone' => $t->owner?->phone ? Phone::display($t->owner->phone) : null,
            ...BillingController::status($t),
            'created_at' => $t->created_at?->translatedFormat('j M Y'),
        ]);

        $since = now()->startOfMonth();

        return Inertia::render('Admin/Tenants', [
            'tenants' => $tenants,
            'filters' => ['q' => $search, 'status' => $filter],
            'stats' => [
                'total' => Tenant::query()->count(),
                'trial' => Tenant::query()->where('status', 'trial')->where('trial_ends_at', '>', now())->count(),
                'active' => Tenant::query()->where('status', 'active')->count(),
                'revenue_month' => (int) SubscriptionPayment::query()->where('status', 'paid')->where('paid_at', '>=', $since)->sum('base_amount'),
            ],
        ]);
    }

    public function show(Tenant $tenant, PlanGuard $guard): Response
    {
        $tenant->load(['owner', 'businessType']);

        return Inertia::render('Admin/Tenant', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->businessType?->name,
                'address' => trim(implode(', ', array_filter([$tenant->address, $tenant->city, $tenant->province]))),
                'owner' => $tenant->owner ? [
                    'name' => $tenant->owner->name,
                    'phone' => $tenant->owner->phone ? Phone::display($tenant->owner->phone) : null,
                    'phone_raw' => $tenant->owner->phone,
                    'last_login' => $tenant->owner->last_login_at?->translatedFormat('j M Y H:i'),
                ] : null,
                'created_at' => $tenant->created_at?->translatedFormat('j F Y'),
                ...BillingController::status($tenant),
            ],
            'usage' => $guard->usage($tenant),
            'plans' => collect(Plans::all())->map(fn ($p, $key) => ['value' => $key, 'label' => $p['label'].' ('.number_format($p['price'], 0, ',', '.').'/bln)'])->values(),
            'payments' => SubscriptionPayment::query()->where('tenant_id', $tenant->id)->latest('id')->limit(30)->get()->map->summary()->values(),
            'newPassword' => session('new_password'),
        ]);
    }

    public function extend(Request $request, Tenant $tenant, SubscriptionBilling $billing): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(Plans::keys())],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $payment = $billing->extendManually($tenant, $request->user(), $data['plan'], (int) $data['months'], $data['amount'] ?? null, $data['note'] ?? null);

        return back()->with('success', "Langganan {$tenant->name} diperpanjang sampai ".$payment->period_until->translatedFormat('j F Y').'.');
    }

    public function unlimited(Tenant $tenant): RedirectResponse
    {
        $tenant->forceFill(['status' => 'active', 'paid_until' => null])->save();
        $tenant->flushModuleCache();

        return back()->with('success', "{$tenant->name} sekarang aktif tanpa batas waktu.");
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $suspend = $request->boolean('suspend');

        $status = match (true) {
            $suspend => 'suspended',
            $tenant->paid_until !== null || $tenant->trial_ends_at === null => 'active',
            default => 'trial',
        };
        $tenant->forceFill(['status' => $status])->save();

        return back()->with('success', $suspend ? "{$tenant->name} dibekukan." : "{$tenant->name} aktif kembali.");
    }

    public function resetPassword(Tenant $tenant): RedirectResponse
    {
        $owner = User::query()->findOrFail($tenant->owner_id);
        $password = Str::lower(Str::random(4)).random_int(1000, 9999);

        DB::transaction(function () use ($owner, $password) {
            $owner->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $owner->id)->delete();
        });

        activity('admin')->performedOn($tenant)->log("Admin membuat kata sandi baru untuk pemilik {$tenant->name}");

        return back()->with('new_password', $password)->with('success', 'Kata sandi baru sudah dibuat. Kirim ke pemilik lewat WhatsApp.');
    }

    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        $owner = User::query()->findOrFail($tenant->owner_id);
        $adminId = $request->user()->id;

        activity('admin')->performedOn($tenant)->log("Admin masuk sebagai pemilik {$tenant->name}");

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $adminId);

        return redirect()->route('dashboard')->with('info', "Anda masuk sebagai pemilik {$tenant->name}.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');
        $admin = $adminId ? User::query()->where('is_super_admin', true)->where('is_active', true)->find($adminId) : null;

        if (! $admin) {
            return redirect()->route('dashboard');
        }

        $tenantId = $request->user()?->tenant_id;
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return $tenantId
            ? redirect()->route('admin.tenants.show', $tenantId)
            : redirect()->route('admin.tenants.index');
    }
}
