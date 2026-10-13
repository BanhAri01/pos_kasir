<?php

namespace App\Modules\Assistant\Jobs;

use App\Models\Tenant;
use App\Modules\Assistant\Services\AiAgent;
use App\Modules\WhatsApp\Services\WhatsAppManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnswerAssistantWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $senderPhone,
        public string $question
    ) {}

    public function handle(AiAgent $agent, WhatsAppManager $waManager): void
    {
        try {
            $response = $agent->ask(
                prompt: $this->question,
                tenant: $this->tenant,
                channel: 'whatsapp',
                senderPhone: $this->senderPhone
            );

            [, $driver] = $waManager->for($this->tenant);
            $driver->send($this->senderPhone, $response->message);
        } catch (\Throwable $e) {
            Log::error('Gagal memproses pesan WhatsApp AI Asisten', [
                'tenant_id' => $this->tenant->id,
                'phone' => $this->senderPhone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
