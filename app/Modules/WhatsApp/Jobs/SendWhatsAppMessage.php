<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/** Mengirim satu pesan WhatsApp (dicoba ulang 3x kalau gagal). */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public int $messageId, public int $tenantId) {}

    public function handle(TenantContext $context, WhatsAppManager $manager): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $context->runAs($tenant, function () use ($manager, $tenant) {
            $message = WhatsappMessage::query()->find($this->messageId);
            if (! $message || $message->status === 'sent') {
                return;
            }

            [$provider, $gateway] = $manager->for($tenant);
            $result = $gateway->send($message->to_phone, $message->body);

            $message->forceFill([
                'provider' => $provider,
                'attempts' => $message->attempts + 1,
                'status' => $result->ok ? 'sent' : 'failed',
                'provider_message_id' => $result->messageId,
                'error' => $result->error ? mb_substr($result->error, 0, 250) : null,
                'sent_at' => $result->ok ? now() : null,
            ])->save();

            if (! $result->ok && $this->attempts() < $this->tries) {
                throw new RuntimeException($result->error ?? 'Gagal mengirim WhatsApp');
            }
        });
    }
}
