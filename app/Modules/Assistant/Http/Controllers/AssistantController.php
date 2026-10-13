<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\TenantAssistantPhone;
use App\Modules\Assistant\Services\AiAgent;
use App\Modules\Assistant\Services\AiQuota;
use App\Modules\Assistant\Services\ClaudeLlm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __construct(
        protected AiAgent $agent,
        protected AiQuota $quota,
        protected ClaudeLlm $claudeLlm,
        protected TenantContext $context
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->context->get() ?? $request->user()->tenant;

        $messages = AssistantMessage::query()
            ->where('tenant_id', $tenant->id)
            ->where('channel', 'web')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $phones = TenantAssistantPhone::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id', 'desc')
            ->get();

        $stats = $this->quota->getTodayStats($tenant);

        return Inertia::render('Assistant/Index', [
            'messages' => $messages,
            'phones' => $phones,
            'stats' => $stats,
            'isClaudeConfigured' => $this->claudeLlm->isConfigured(),
            'webhookUrl' => url('webhook/fonnte/assistant'),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'driver' => ['nullable', 'string', 'in:demo,claude'],
        ]);

        $tenant = $this->context->get() ?? $request->user()->tenant;
        $user = $request->user();

        $this->agent->ask(
            prompt: $validated['message'],
            tenant: $tenant,
            user: $user,
            channel: 'web',
            driver: $validated['driver'] ?? null
        );

        return back();
    }

    public function storePhone(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $tenant = $this->context->get() ?? $request->user()->tenant;
        $normalized = Phone::normalize($validated['phone']);

        if (! $normalized) {
            return back()->withErrors(['phone' => 'Format nomor WhatsApp tidak valid. Gunakan format seperti 081234567890']);
        }

        TenantAssistantPhone::updateOrCreate(
            ['tenant_id' => $tenant->id, 'phone_number' => $normalized],
            ['label' => $validated['label'] ?? 'Owner Toko', 'is_active' => true]
        );

        return back()->with('success', 'Nomor WhatsApp berhasil didaftarkan untuk Asisten AI.');
    }

    public function destroyPhone(Request $request, TenantAssistantPhone $phone): RedirectResponse
    {
        $tenant = $this->context->get() ?? $request->user()->tenant;

        if ($phone->tenant_id !== $tenant->id) {
            abort(403);
        }

        $phone->delete();

        return back()->with('success', 'Nomor WhatsApp berhasil dihapus.');
    }
}
