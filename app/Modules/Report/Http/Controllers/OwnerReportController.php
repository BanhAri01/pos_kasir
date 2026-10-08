<?php

namespace App\Modules\Report\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Report\Services\OwnerReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerReportController extends Controller
{
    public function __construct(private TenantContext $context, private OwnerReportService $reports) {}

    public function edit(): Response
    {
        $tenant = $this->context->get();
        $settings = OwnerReportService::settings($tenant);

        return Inertia::render('Reports/WhatsAppReport', [
            'settings' => [...$settings, 'phone' => $settings['phone'] ? Phone::display($settings['phone']) : ''],
            'recipient' => Phone::display($this->reports->recipient($tenant)),
            'preview' => $this->reports->dailyText($tenant, now($tenant->timezone)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daily' => ['boolean'],
            'daily_at' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'on_close' => ['boolean'],
            'phone' => ['nullable', 'string', 'max:20'],
        ], ['daily_at.regex' => 'Jam tidak valid. Contoh: 21:00.']);

        $phone = filled($data['phone'] ?? null) ? Phone::normalize($data['phone']) : null;
        if (filled($data['phone'] ?? null) && ! $phone) {
            return back()->withErrors(['phone' => 'No HP sepertinya salah. Contoh: 0812 3456 7890.']);
        }

        $tenant = $this->context->get();
        $settings = $tenant->settings ?? [];
        $settings['owner_report'] = [
            'daily' => (bool) ($data['daily'] ?? false),
            'daily_at' => $data['daily_at'],
            'on_close' => (bool) ($data['on_close'] ?? false),
            'phone' => $phone,
        ];
        $tenant->forceFill(['settings' => $settings])->save();

        return back()->with('success', 'Pengaturan laporan WhatsApp disimpan.');
    }

    public function test(): RedirectResponse
    {
        $tenant = $this->context->get();

        return $this->reports->sendDaily($tenant, now($tenant->timezone), true)
            ? back()->with('success', 'Laporan hari ini dikirim ke '.Phone::display($this->reports->recipient($tenant)).'.')
            : back()->with('info', 'Belum ada penjualan hari ini, atau no WhatsApp tujuan belum diisi. Kuota WhatsApp juga bisa sudah habis.');
    }
}
