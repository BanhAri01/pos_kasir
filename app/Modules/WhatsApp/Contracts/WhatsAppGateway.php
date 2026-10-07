<?php

namespace App\Modules\WhatsApp\Contracts;

use App\Modules\WhatsApp\Support\SendResult;

/**
 * Penyedia pengiriman WhatsApp. Ganti penyedia (Fonnte, Wablas, WhatsApp Cloud API)
 * cukup dengan mengganti driver di pengaturan, tanpa mengubah kode fitur lain.
 */
interface WhatsAppGateway
{
    /**
     * @param  string  $to  no HP format 628xxxxxxxxx
     */
    public function send(string $to, string $message): SendResult;
}
