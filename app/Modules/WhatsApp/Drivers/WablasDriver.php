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

    public static function isAllowedDomain(?string $domain): bool
    {
        $parts = parse_url(trim((string) $domain));

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['port'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && in_array($parts['path'] ?? '', ['', '/'], true)
            && preg_match('/^([a-z0-9-]+\.)*wablas\.com$/', strtolower($parts['host'] ?? '')) === 1;
    }

    public function send(string $to, string $message): SendResult
    {
        if (! self::isAllowedDomain($this->domain)) {
            return SendResult::failed('Domain Wablas tidak valid. Pakai alamat resmi, contoh: https://solo.wablas.com');
        }

        try {
            $response = Http::timeout(20)
                ->withoutRedirecting()
                ->withHeaders(['Authorization' => $this->token])
                ->post(rtrim($this->domain, '/').'/api/send-message', ['phone' => $to, 'message' => $message]);

            $json = $response->json() ?? [];

            return $response->successful() && ($json['status'] ?? false)
                ? SendResult::ok((string) ($json['data']['messages'][0]['id'] ?? ''))
                : SendResult::failed('Wablas menolak pesan: '.mb_substr(strip_tags((string) ($json['message'] ?? 'tanpa keterangan')), 0, 120));
        } catch (Throwable) {
            return SendResult::failed('Tidak bisa menghubungi Wablas. Periksa domain dan token.');
        }
    }
}
