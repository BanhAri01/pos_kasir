<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\MessageTemplate;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan WhatsApp: default memakai nomor pusat Hermes (tidak perlu atur apa-apa).
 * Pemilik bisa memakai nomor sendiri lewat Fonnte / Wablas / WhatsApp Cloud API,
 * mengubah isi pesan, kirim pesan uji, dan melihat riwayat pesan terkirim.
 */
class WhatsAppSettingsController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): Response
    {
        $account = $this->account();
        $custom = MessageTemplate::query()->get()->keyBy('code');
        $timezone = $this->context->get()->timezone;

        return Inertia::render('Settings/WhatsApp', [
            'account' => $account ? [
                'provider' => $account->provider,
                'sender_phone' => Phone::display($account->sender_phone),
                'is_active' => $account->is_active,
                'has_token' => ! empty($account->credentials['token'] ?? null),
                'domain' => $account->credentials['domain'] ?? null,
                'phone_number_id' => $account->credentials['phone_number_id'] ?? null,
            ] : null,
            'templates' => collect(WhatsAppService::DEFAULT_TEMPLATES)->map(fn ($body, $code) => [
                'code' => $code,
                'label' => WhatsAppService::LABELS[$code] ?? $code,
                'body' => $custom[$code]->body ?? $body,
                'default' => $body,
                'is_custom' => isset($custom[$code]),
                'is_active' => $custom[$code]->is_active ?? true,
            ])->values(),
            'messages' => WhatsappMessage::query()->latest('id')->limit(30)->get()->map(fn ($m) => [
                'id' => $m->id, 'to' => Phone::display($m->to_phone), 'template' => WhatsAppService::LABELS[$m->template_code] ?? 'Pesan',
                'status' => $m->status, 'error' => $m->error, 'body' => $m->body,
                'time' => $m->created_at->timezone($timezone)->locale('id')->translatedFormat('j M H:i'),
            ]),
        ]);
    }

    /** Simpan akun pengirim. Token dikosongkan = token lama tetap dipakai. */
    public function saveAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['central', 'own'])],
            'provider' => ['required_if:mode,own', 'nullable', Rule::in(['fonnte', 'wablas', 'cloud_api'])],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'token' => ['nullable', 'string', 'max:500'],
            'domain' => ['nullable', 'url', 'max:100'],
            'phone_number_id' => ['nullable', 'string', 'max:50'],
        ], ['provider.required_if' => 'Pilih penyedia WhatsApp.']);

        $account = $this->account();

        if ($data['mode'] === 'own' && ! $this->context->get()->allows('own_whatsapp')) {
            return back()->with('error', 'Nomor WhatsApp sendiri tersedia di paket Bisnis. Naikkan paket di menu Langganan.');
        }

        if ($data['mode'] === 'central') {
            $account?->update(['is_active' => false]);

            return back()->with('success', 'Pesan WhatsApp dikirim memakai nomor pusat Hermes.');
        }

        $credentials = $account?->credentials ?? [];
        if (! empty($data['token'])) {
            $credentials['token'] = $data['token'];
        }
        $credentials['domain'] = $data['domain'] ?? null;
        $credentials['phone_number_id'] = $data['phone_number_id'] ?? null;

        if (empty($credentials['token'])) {
            return back()->withErrors(['token' => 'Token dari penyedia WhatsApp belum diisi.']);
        }

        $account ??= (new WhatsappAccount)->forceFill(['tenant_id' => $this->context->id()]);
        $account->fill([
            'provider' => $data['provider'],
            'sender_phone' => Phone::normalize($data['sender_phone'] ?? null),
            'credentials' => $credentials,
            'is_active' => true,
        ])->save();

        return back()->with('success', 'Nomor WhatsApp usaha sudah disimpan. Coba kirim pesan uji.');
    }

    public function saveTemplate(Request $request, string $code): RedirectResponse
    {
        abort_unless(array_key_exists($code, WhatsAppService::DEFAULT_TEMPLATES), 404);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'reset' => ['boolean'],
        ]);

        if (! empty($data['reset'])) {
            MessageTemplate::query()->where('code', $code)->delete();

            return back()->with('success', 'Isi pesan dikembalikan ke bawaan.');
        }

        MessageTemplate::updateOrCreate(['code' => $code], [
            'body' => $data['body'] ?: WhatsAppService::DEFAULT_TEMPLATES[$code],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Isi pesan sudah disimpan.');
    }

    public function test(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']], ['phone.required' => 'Isi no HP tujuan.']);
        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            return back()->withErrors(['phone' => 'No HP belum benar. Contoh: 0812 3456 7890.']);
        }

        $whatsapp->send($phone, "Halo! Ini pesan uji dari *{$this->context->get()->name}* lewat Hermes POS ✅");

        return back()->with('success', 'Pesan uji sedang dikirim. Cek riwayat di bawah dalam beberapa detik.');
    }

    private function account(): ?WhatsappAccount
    {
        return WhatsappAccount::query()->where('tenant_id', $this->context->id())->first();
    }
}
