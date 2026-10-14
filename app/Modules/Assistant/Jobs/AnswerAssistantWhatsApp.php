<?php

namespace App\Modules\Assistant\Jobs;

use App\Modules\Assistant\Models\AssistantPhone;
use App\Modules\Assistant\Services\Agent;
use App\Modules\WhatsApp\Services\WhatsAppManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Menjawab pertanyaan WhatsApp ke Asisten Toko lewat antrean, supaya Fonnte tidak menunggu jawaban AI. */
class AnswerAssistantWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $phoneId,
        public readonly string $text,
        public readonly ?string $externalId = null,
    ) {}

    public function handle(Agent $agent, WhatsAppManager $whatsapp): void
    {
        $phone = AssistantPhone::allTenants()->with(['tenant', 'user'])->find($this->phoneId);

        if (! $phone || ! $phone->tenant) {
            return;
        }

        $answer = $agent->ask($phone->tenant, $this->text, Agent::WA, $phone->user, $phone->phone, null, $this->externalId);

        if ($answer->status === 'duplicate' || $answer->text === '') {
            return;
        }

        $token = (string) config('hermes.assistant.fonnte_token');
        $driver = $token !== '' ? $whatsapp->make('fonnte', ['token' => $token]) : $whatsapp->for(null)[1];

        try {
            $result = $driver->send($phone->phone, $answer->text);
            if (! $result->ok) {
                Log::warning('Balasan Asisten Toko gagal dikirim', ['tenant' => $phone->tenant_id, 'error' => $result->error ?? null]);
            }
        } catch (Throwable $e) {
            Log::warning('Balasan Asisten Toko gagal dikirim: '.$e->getMessage(), ['tenant' => $phone->tenant_id]);
        }
    }
}
