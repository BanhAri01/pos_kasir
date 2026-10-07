<?php

namespace App\Modules\WhatsApp\Drivers;

use App\Modules\WhatsApp\Contracts\WhatsAppGateway;
use App\Modules\WhatsApp\Support\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Cloud API resmi (Meta).
 * Catatan: di luar 24 jam setelah pelanggan terakhir membalas, Meta mewajibkan pesan template
 * yang sudah disetujui. Driver ini mengirim pesan teks biasa (cocok untuk struk setelah transaksi).
 */
class CloudApiDriver implements WhatsAppGateway
{
    public function __construct(private string $accessToken, private string $phoneNumberId) {}

    public function send(string $to, string $message): SendResult
    {
        try {
            $response = Http::timeout(20)->withToken($this->accessToken)
                ->post("https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $message],
                ]);

            $json = $response->json() ?? [];

            return $response->successful()
                ? SendResult::ok((string) ($json['messages'][0]['id'] ?? ''))
                : SendResult::failed((string) ($json['error']['message'] ?? 'WhatsApp Cloud API menolak pesan.'));
        } catch (Throwable $e) {
            return SendResult::failed('Tidak bisa menghubungi WhatsApp Cloud API: '.$e->getMessage());
        }
    }
}
