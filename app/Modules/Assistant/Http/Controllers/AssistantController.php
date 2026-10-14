<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\AssistantPhone;
use App\Modules\Assistant\Services\Agent;
use App\Modules\Assistant\Services\AssistantWhatsApp;
use App\Modules\Assistant\Services\Llm;
use App\Modules\Assistant\Services\Quota;
use App\Modules\Billing\Services\Plans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    private const CODE_MINUTES = 10;

    private const CODE_ATTEMPTS = 5;

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request, Llm $llm): Response
    {
        $tenant = $this->context->get();
        $canManage = $request->user()->can('manage_business');

        $messages = AssistantMessage::query()
            ->where('channel', Agent::WEB)
            ->where('user_id', $request->user()->id)
            ->where('content', '!=', '')
            ->latest('id')->limit(30)->get(['id', 'role', 'content', 'status', 'created_at'])
            ->reverse()->values()
            ->map(fn (AssistantMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'text' => $m->content,
                'status' => $m->status,
                'time' => $m->created_at->timezone($tenant->timezone)->format('H:i'),
            ]);

        return Inertia::render('Assistant/Index', [
            'allowed' => $tenant->allows('assistant'),
            'requiredPlan' => Plans::label(Plans::requiredForFeature('assistant') ?? 'bisnis'),
            'live' => $llm->available(),
            'quota' => $this->quota(Quota::for($tenant)),
            'messages' => $messages,
            'canManagePhones' => $canManage,
            'phones' => $canManage
                ? AssistantPhone::query()->with('user:id,is_active')->orderBy('created_at')->get()->map(fn (AssistantPhone $p) => [
                    'id' => $p->id,
                    'phone' => Phone::display($p->phone),
                    'raw' => $p->phone,
                    'label' => $p->label,
                    'status' => match (true) {
                        ! $p->isVerified() => 'pending',
                        ! $p->ownerActive() => 'inactive',
                        default => 'active',
                    },
                ])
                : [],
            'maxPhones' => (int) config('hermes.assistant.max_phones'),
            'examples' => [
                'Penjualan hari ini berapa?',
                'Barang apa paling laku bulan ini?',
                'Stok apa yang mau habis?',
                'Bulan ini untung berapa?',
                'Siapa yang masih kasbon?',
                'Kenapa penjualan minggu ini turun?',
                'Jam berapa toko paling ramai?',
            ],
        ]);
    }

    public function ask(Request $request, Agent $agent): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:'.(int) config('hermes.assistant.question_max')],
            'mode' => ['nullable', 'in:auto,quick,deep'],
        ], [
            'question.required' => 'Ketik pertanyaannya dulu.',
            'question.max' => 'Pertanyaan terlalu panjang, maksimal :max huruf.',
        ]);

        $tenant = $this->context->get();
        $mode = ($data['mode'] ?? 'auto') === 'auto' ? null : $data['mode'];
        $answer = $agent->ask($tenant, $data['question'], Agent::WEB, $request->user(), null, $mode);

        return response()->json($answer->toArray() + [
            'quota' => $this->quota(Quota::for($tenant)),
            'time' => now($tenant->timezone)->format('H:i'),
        ]);
    }

    public function storePhone(Request $request, AssistantWhatsApp $sender): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'label' => ['nullable', 'string', 'max:60'],
        ], ['phone.required' => 'Isi nomor WhatsApp.']);

        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.']);
        }

        $tenant = $this->context->get();
        // Satu nomor hanya untuk satu usaha (dicek lintas usaha tanpa membocorkan pemiliknya).
        $existing = AssistantPhone::allTenants()->where('phone', $phone)->first();
        $mine = $existing?->tenant_id === $tenant->id ? $existing : null;

        if ($existing && ! $mine) {
            if ($existing->isVerified()) {
                throw ValidationException::withMessages(['phone' => 'Nomor ini sudah terdaftar di Asisten Toko.']);
            }
            if ($existing->codePending()) {
                throw ValidationException::withMessages(['phone' => 'Nomor ini sedang didaftarkan. Coba lagi '.self::CODE_MINUTES.' menit lagi.']);
            }
            $existing->delete();
        }

        if ($mine?->isVerified()) {
            throw ValidationException::withMessages(['phone' => 'Nomor ini sudah terdaftar di Asisten Toko.']);
        }
        if ($mine && $mine->updated_at->gt(now()->subMinute())) {
            throw ValidationException::withMessages(['phone' => 'Kode baru saja dikirim. Tunggu 1 menit sebelum minta kode lagi.']);
        }
        if (! $mine && AssistantPhone::query()->count() >= (int) config('hermes.assistant.max_phones')) {
            throw ValidationException::withMessages(['phone' => 'Maksimal '.config('hermes.assistant.max_phones').' nomor untuk satu usaha.']);
        }

        $code = (string) random_int(100000, 999999);
        $record = $mine ?? new AssistantPhone(['phone' => $phone]);
        $record->fill([
            'label' => ($data['label'] ?? null) ?: $record->label,
            'user_id' => $request->user()->id,
            'code_hash' => Hash::make($code),
            'code_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'code_attempts' => 0,
        ]);
        $record->updated_at = now();
        $record->save();

        $text = "Kode Asisten Toko Hermes POS: *{$code}*\n\nMasukkan kode ini di halaman Asisten Toko untuk menghubungkan nomor ini dengan {$tenant->name}. Berlaku ".self::CODE_MINUTES.' menit. Abaikan pesan ini kalau Anda tidak sedang mendaftar.';

        if (! $sender->send($phone, $text, $tenant->id)) {
            $record->delete();

            throw ValidationException::withMessages(['phone' => 'Kode gagal dikirim ke WhatsApp. Pastikan nomornya aktif di WhatsApp lalu coba lagi.']);
        }

        return back()->with('success', 'Kode sudah dikirim ke WhatsApp '.Phone::display($phone).'. Masukkan kodenya di bawah.');
    }

    public function verifyPhone(Request $request, AssistantPhone $phone): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']], ['code.required' => 'Isi kode dari WhatsApp.']);

        if ($phone->isVerified()) {
            return back()->with('success', 'Nomor ini sudah aktif.');
        }

        $error = fn (string $message) => ValidationException::withMessages(["code_{$phone->id}" => $message]);

        if (! $phone->codePending()) {
            throw $error('Kode sudah kedaluwarsa. Tekan "Kirim ulang kode".');
        }

        if (! Hash::check(preg_replace('/\D/', '', $data['code']), (string) $phone->code_hash)) {
            $phone->increment('code_attempts');

            if ($phone->code_attempts >= self::CODE_ATTEMPTS) {
                $phone->forceFill(['code_hash' => null, 'code_expires_at' => null])->save();

                throw $error('Kode salah terlalu sering. Tekan "Kirim ulang kode" untuk kode baru.');
            }

            throw $error('Kode salah. Coba lagi.');
        }

        $phone->forceFill(['verified_at' => now(), 'code_hash' => null, 'code_expires_at' => null, 'code_attempts' => 0])->save();

        return back()->with('success', 'Nomor '.Phone::display($phone->phone).' sekarang bisa bertanya ke Asisten Toko lewat WhatsApp.');
    }

    public function destroyPhone(AssistantPhone $phone): RedirectResponse
    {
        $phone->delete();

        return back()->with('success', 'Nomor '.Phone::display($phone->phone).' dilepas dari Asisten Toko.');
    }

    private function quota(Quota $quota): array
    {
        return [
            'percent' => $quota->percentLeft(),
            'questions' => $quota->questionsLeft(),
        ];
    }
}
