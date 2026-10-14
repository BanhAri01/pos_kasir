<?php

namespace App\Modules\Assistant\Jobs;

use App\Modules\Assistant\Models\AssistantPhone;
use App\Modules\Assistant\Services\Agent;
use App\Modules\Assistant\Services\AssistantWhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

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

    public function handle(Agent $agent, AssistantWhatsApp $sender): void
    {
        $phone = AssistantPhone::allTenants()->with(['tenant', 'user'])->find($this->phoneId);

        if (! $phone || ! $phone->tenant || ! $phone->serviceable()) {
            return;
        }

        $answer = $agent->ask($phone->tenant, $this->text, Agent::WA, $phone->user, $phone->phone, null, $this->externalId);

        if ($answer->status === 'duplicate' || $answer->text === '') {
            return;
        }

        $sender->send($phone->phone, $answer->text, $phone->tenant_id);
    }
}
