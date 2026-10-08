<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Auth\Services\RegisterTenantService;
use App\Modules\Billing\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(Request $request, ReferralService $referrals): Response
    {
        $referrer = $referrals->findReferrer($request->query('ref'));

        $types = BusinessType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'category', 'icon', 'description']);

        return Inertia::render('Auth/Register', [
            'categories' => collect(BusinessType::CATEGORIES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'types' => $types->where('category', $key)->values(),
            ])->values(),
            'referral' => $referrer ? [
                'code' => $referrer->referral_code,
                'name' => $referrer->name,
                'bonus_days' => (int) config('hermes.referral.new_tenant_bonus_days'),
            ] : null,
            'trialDays' => (int) config('hermes.trial_days'),
        ]);
    }

    public function store(RegisterRequest $request, RegisterTenantService $service, DeviceService $devices): RedirectResponse
    {
        $owner = $service->register($request->validated());

        Auth::login($owner, remember: true);
        $request->session()->regenerate();

        $owner->forceFill(['last_login_at' => now()])->save();
        $devices->remember($request, $owner);

        return redirect()->route('dashboard')
            ->with('success', 'Selamat datang! Usaha Anda sudah siap dipakai.');
    }
}
