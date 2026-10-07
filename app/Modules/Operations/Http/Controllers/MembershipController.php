<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\MemberCheckin;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\MembershipPlan;
use App\Modules\Operations\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Member: daftar member + status (aktif / habis), paket member, dan absen masuk (check-in). */
class MembershipController extends Controller
{
    public function __construct(private TenantContext $context, private MembershipService $memberships) {}

    public function index(Request $request): Response
    {
        $timezone = $this->context->get()->timezone;
        $today = Carbon::now($timezone)->toDateString();
        $term = trim($request->string('q')->toString());

        $members = Customer::query()
            ->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('member_code', $term)->orWhere('phone', Phone::normalize($term))))
            ->with(['memberships' => fn ($q) => $q->where('status', 'active')->with('plan.product:id,name')->orderByDesc('ends_on')])
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(function (Customer $c) use ($today, $timezone) {
                $latest = $c->memberships->first();
                $active = $latest && $latest->starts_on->toDateString() <= $today && $latest->ends_on->toDateString() >= $today;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => Phone::display($c->phone),
                    'member_code' => $c->member_code,
                    'plan' => $latest?->plan?->product?->name,
                    'ends_on' => $latest?->ends_on->locale('id')->translatedFormat('j M Y'),
                    'days_left' => $latest ? (int) Carbon::now($timezone)->startOfDay()->diffInDays($latest->ends_on, false) : null,
                    'sessions_remaining' => $latest?->sessions_remaining,
                    'active' => $active,
                ];
            });

        return Inertia::render('Operations/Members', [
            'members' => $members,
            'plans' => MembershipPlan::query()->with('product:id,name,price')->get()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->product?->name, 'price' => $p->product?->price, 'kind' => $p->kind,
                'duration_value' => $p->duration_value, 'duration_unit' => $p->duration_unit, 'session_quota' => $p->session_quota,
            ]),
            'todayCheckins' => MemberCheckin::query()->where('checked_in_at', '>=', Carbon::now($timezone)->startOfDay()->utc())->count(),
            'q' => $term,
        ]);
    }

    /** Paket member baru: otomatis dibuat juga sebagai barang di kasir. */
    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'kind' => ['required', Rule::in(['gym', 'pt_session', 'package'])],
            'duration_value' => ['required', 'integer', 'min:1', 'max:120'],
            'duration_unit' => ['required', Rule::in(['day', 'week', 'month', 'year'])],
            'session_quota' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], ['name.required' => 'Nama paket belum diisi.', 'price.required' => 'Harga paket belum diisi.']);

        DB::transaction(function () use ($data) {
            $product = Product::create([
                'uuid' => (string) Str::uuid(), 'name' => $data['name'], 'type' => 'membership', 'price' => $data['price'],
                'track_stock' => false, 'is_active' => true,
            ]);
            MembershipPlan::create([
                'product_id' => $product->id, 'kind' => $data['kind'], 'duration_value' => $data['duration_value'],
                'duration_unit' => $data['duration_unit'], 'session_quota' => $data['session_quota'] ?? null,
            ]);
        });

        return back()->with('success', "Paket {$data['name']} sudah dibuat. Paket ini bisa dijual di kasir.");
    }

    public function checkin(Request $request, CurrentOutlet $outlet): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'pt_session' => ['boolean'],
            'method' => ['nullable', Rule::in(['qr', 'manual'])],
        ]);
        $customer = Customer::query()->findOrFail($data['customer_id']);

        $this->memberships->checkIn($customer, $outlet->id($request->user()), $request->user(), $this->context->get()->timezone, $data['method'] ?? 'manual', $data['pt_session'] ?? false);

        return back()->with('success', "Selamat datang, {$customer->name}! Absen masuk tercatat.");
    }

    /** Beri kode member (untuk kartu / QR) bila belum ada. */
    public function assignCode(Customer $customer): RedirectResponse
    {
        if (! $customer->member_code) {
            $customer->update(['member_code' => 'M'.str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT)]);
        }

        return back()->with('success', "Kode member {$customer->member_code} siap dicetak di kartu.");
    }

    public function cancel(Membership $membership): RedirectResponse
    {
        $membership->update(['status' => 'canceled']);

        return back()->with('success', 'Keanggotaan dibatalkan.');
    }
}
