<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Operations\Models\CommissionRule;
use App\Modules\Operations\Models\StaffCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Komisi karyawan: rekap per karyawan per periode, tandai sudah dibayar, dan atur aturan komisi. */
class CommissionController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request): Response
    {
        $timezone = $this->context->get()->timezone;
        $from = Carbon::parse($request->string('dari')->toString() ?: now($timezone)->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $to = Carbon::parse($request->string('sampai')->toString() ?: now($timezone)->toDateString(), $timezone)->endOfDay();

        $summary = StaffCommission::query()
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->where('status', '!=', 'canceled')
            ->selectRaw("user_id, COUNT(*) as jobs, SUM(amount) as total, SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as unpaid")
            ->groupBy('user_id')
            ->get();

        $users = User::sameTenant()->whereIn('id', $summary->pluck('user_id'))->pluck('name', 'id');

        return Inertia::render('Operations/Commissions', [
            'summary' => $summary->map(fn ($row) => [
                'user_id' => $row->user_id, 'name' => $users[$row->user_id] ?? '-', 'jobs' => (int) $row->jobs,
                'total' => (int) $row->total, 'unpaid' => (int) $row->unpaid,
            ])->sortByDesc('total')->values(),
            'range' => ['dari' => $from->toDateString(), 'sampai' => $to->toDateString()],
            'rules' => CommissionRule::query()->with(['user:id,name', 'product:id,name', 'category:id,name'])->get()->map(fn ($r) => [
                'id' => $r->id, 'user' => $r->user?->name, 'product' => $r->product?->name, 'category' => $r->category?->name,
                'type' => $r->type, 'value' => $r->value,
            ]),
            'staff' => User::sameTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'services' => Product::query()->where('type', 'service')->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $tenantId = $this->context->id();
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
        ], ['value.required' => 'Isi besar komisinya.']);

        // Persen diisi biasa (mis. 30), disimpan basis point (3000).
        $data['value'] = $data['type'] === 'percent' ? (int) round((float) $data['value'] * 100) : (int) $data['value'];
        CommissionRule::create($data);

        return back()->with('success', 'Aturan komisi sudah disimpan.');
    }

    public function destroyRule(CommissionRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Aturan komisi dihapus.');
    }

    /** Tandai komisi seorang karyawan di periode ini sudah dibayar. */
    public function pay(Request $request, int $userId): RedirectResponse
    {
        $data = $request->validate(['dari' => ['required', 'date'], 'sampai' => ['required', 'date']]);
        $timezone = $this->context->get()->timezone;

        $count = StaffCommission::query()->where('user_id', $userId)->where('status', 'pending')
            ->whereBetween('created_at', [Carbon::parse($data['dari'], $timezone)->startOfDay()->utc(), Carbon::parse($data['sampai'], $timezone)->endOfDay()->utc()])
            ->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('success', $count ? 'Komisi sudah ditandai dibayar.' : 'Tidak ada komisi yang belum dibayar.');
    }
}
