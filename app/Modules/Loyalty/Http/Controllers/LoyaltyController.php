<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyEntry;
use App\Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    public function __construct(private TenantContext $context, private LoyaltyService $loyalty) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('Loyalty/Index', [
            'settings' => LoyaltyService::settings($this->context->get()),
            'canManage' => $request->user()->can('manage_business'),
            'filters' => ['q' => $search],
            'customers' => Customer::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', '%'.(Phone::normalize($search) ?? $search).'%')))
                ->where('loyalty_points', '!=', 0)
                ->orderByDesc('loyalty_points')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'loyalty_points'])
                ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'phone' => Phone::display($c->phone), 'points' => $c->loyalty_points]),
            'recent' => LoyaltyEntry::query()->with('customer:id,name')->latest('id')->limit(30)->get()
                ->map(fn (LoyaltyEntry $e) => [
                    'id' => $e->id,
                    'customer' => $e->customer?->name,
                    'points' => $e->points,
                    'note' => $e->note,
                    'time' => $e->created_at?->timezone($this->context->get()->timezone)->translatedFormat('j M H:i'),
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_business'), 403);

        $data = $request->validate([
            'spend_per_point' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'points_for_reward' => ['required', 'integer', 'min:1', 'max:1000'],
            'reward_value' => ['required', 'integer', 'min:500', 'max:10000000'],
            'notify' => ['boolean'],
        ], [
            'spend_per_point.min' => 'Minimal Rp1.000 per poin.',
            'reward_value.min' => 'Nilai potongan minimal Rp500.',
        ]);

        $tenant = $this->context->get();
        $settings = $tenant->settings ?? [];
        $settings['loyalty'] = [
            'spend_per_point' => (int) $data['spend_per_point'],
            'points_for_reward' => (int) $data['points_for_reward'],
            'reward_value' => (int) $data['reward_value'],
            'notify' => (bool) ($data['notify'] ?? false),
        ];
        $tenant->forceFill(['settings' => $settings])->save();

        return back()->with('success', 'Aturan poin disimpan. '.LoyaltyService::describe(LoyaltyService::settings($tenant)));
    }

    public function adjust(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->can('manage_business'), 403);

        $data = $request->validate([
            'points' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'note' => ['required', 'string', 'max:150'],
        ], ['note.required' => 'Tulis alasan, contoh: hadiah ulang tahun.']);

        $this->loyalty->adjust($customer, (int) $data['points'], $data['note'], $request->user());

        return back()->with('success', "Poin {$customer->name} sudah diubah.");
    }
}
