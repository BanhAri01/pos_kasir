<?php

namespace App\Modules\WhatsApp\Drivers;

use App\Modules\WhatsApp\Contracts\WhatsAppGateway;
use App\Modules\WhatsApp\Support\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Fonnte (fonnte.com): kirim pesan teks dengan token perangkat. */
class FonnteDriver implements WhatsAppGateway
{
    public function __construct(private string $token) {}

    public function send(string $to, string $message): SendResult
    {
        try {
            $response = Http::asForm()->timeout(20)
                ->withHeaders(['Authorization' => $this->token])
                ->post('https://api.fonnte.com/send', ['target' => $to, 'message' => $message, 'countryCode' => '62']);

            $json = $response->json() ?? [];

            return $response->successful() && ($json['status'] ?? false)
                ? SendResult::ok(is_array($json['id'] ?? null) ? (string) ($json['id'][0] ?? '') : (string) ($json['id'] ?? ''))
                : SendResult::failed((string) ($json['reason'] ?? $json['detail'] ?? 'Fonnte menolak pesan.'));
        } catch (Throwable $e) {
            return SendResult::failed('Tidak bisa menghubungi Fonnte: '.$e->getMessage());
        }
    }
}
