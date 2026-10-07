<?php

namespace App\Modules\WhatsApp\Drivers;

use App\Modules\WhatsApp\Contracts\WhatsAppGateway;
use App\Modules\WhatsApp\Support\SendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Untuk development / uji coba: pesan hanya ditulis ke log, tidak benar-benar dikirim. */
class LogDriver implements WhatsAppGateway
{
    public function send(string $to, string $message): SendResult
    {
        Log::info("[WhatsApp ke {$to}] {$message}");

        return SendResult::ok('log-'.Str::random(8));
    }
}
