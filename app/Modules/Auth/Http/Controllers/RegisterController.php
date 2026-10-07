<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Auth\Services\RegisterTenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
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
