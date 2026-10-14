<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\AssistantPhone;
use App\Modules\Assistant\Services\Agent;
use App\Modules\Assistant\Services\Llm;
use App\Modules\Assistant\Services\Quota;
use App\Modules\Billing\Services\Plans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
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
                ? AssistantPhone::query()->orderBy('created_at')->get()->map(fn (AssistantPhone $p) => [
                    'id' => $p->id, 'phone' => Phone::display($p->phone), 'label' => $p->label,
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

    public function storePhone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'label' => ['nullable', 'string', 'max:60'],
        ], ['phone.required' => 'Isi nomor WhatsApp.']);

        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.']);
        }
        if (AssistantPhone::query()->count() >= (int) config('hermes.assistant.max_phones')) {
            throw ValidationException::withMessages(['phone' => 'Maksimal '.config('hermes.assistant.max_phones').' nomor untuk satu usaha.']);
        }
        // Satu nomor hanya untuk satu usaha (dicek lintas usaha tanpa membocorkan pemiliknya).
        if (AssistantPhone::allTenants()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Nomor ini sudah terdaftar di Asisten Toko.']);
        }

        AssistantPhone::create(['phone' => $phone, 'label' => ($data['label'] ?? null) ?: null, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Nomor '.Phone::display($phone).' sekarang bisa bertanya ke Asisten Toko lewat WhatsApp.');
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
