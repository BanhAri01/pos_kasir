<?php

namespace App\Modules\WhatsApp\Drivers;

use App\Modules\WhatsApp\Contracts\WhatsAppGateway;
use App\Modules\WhatsApp\Support\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Wablas: setiap akun punya domain server sendiri (mis. https://solo.wablas.com). */
class WablasDriver implements WhatsAppGateway
{
    public function __construct(private string $token, private string $domain) {}

    public function send(string $to, string $message): SendResult
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => $this->token])
                ->post(rtrim($this->domain, '/').'/api/send-message', ['phone' => $to, 'message' => $message]);

            $json = $response->json() ?? [];

            return $response->successful() && ($json['status'] ?? false)
                ? SendResult::ok((string) ($json['data']['messages'][0]['id'] ?? ''))
                : SendResult::failed((string) ($json['message'] ?? 'Wablas menolak pesan.'));
        } catch (Throwable $e) {
            return SendResult::failed('Tidak bisa menghubungi Wablas: '.$e->getMessage());
        }
    }
}
