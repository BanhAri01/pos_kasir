<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\RegisterTenantService;
use App\Modules\Settings\Http\Requests\PreferenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Tampilan & Suara": ukuran huruf, tema terang/gelap, suara.
 */
class PreferenceController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Display');
    }

    public function update(PreferenceRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Gabungkan dengan nilai lama: pengaturan yang tidak dikirim tetap seperti semula.
        $user->preferences = [
            ...RegisterTenantService::defaultPreferences(),
            ...($user->preferences ?? []),
            ...$request->validated(),
        ];
        $user->save();

        // Tanpa pesan flash: halaman menampilkan tanda "Tersimpan" sendiri,
        // supaya tidak muncul pesan setiap kali sakelar diketuk.
        return back();
    }

    /** Tampilkan lagi tur pengenalan (dari menu Lainnya). */
    public function restartTour(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->preferences = [...($user->preferences ?? []), 'tour_done' => false];
        $user->save();

        return redirect()->route('dashboard');
    }
}
