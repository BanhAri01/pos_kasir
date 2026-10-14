<?php

namespace App\Modules\Assistant\Services;

use App\Modules\WhatsApp\Services\WhatsAppManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Mengirim pesan dari nomor WhatsApp Asisten Toko (Fonnte khusus asisten bila diisi). */
class AssistantWhatsApp
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    public function send(string $phone, string $text, ?int $tenantId = null): bool
    {
        $token = (string) config('hermes.assistant.fonnte_token');
        $driver = $token !== '' ? $this->whatsapp->make('fonnte', ['token' => $token]) : $this->whatsapp->for(null)[1];

        try {
            $result = $driver->send($phone, $text);
            if (! $result->ok) {
                Log::warning('Pesan Asisten Toko gagal dikirim', ['tenant' => $tenantId, 'error' => $result->error ?? null]);
            }

            return $result->ok;
        } catch (Throwable $e) {
            Log::warning('Pesan Asisten Toko gagal dikirim: '.$e->getMessage(), ['tenant' => $tenantId]);

            return false;
        }
    }
}
