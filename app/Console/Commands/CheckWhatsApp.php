<?php

namespace App\Console\Commands;

use App\Modules\WhatsApp\Services\WhatsAppHealth;
use Illuminate\Console\Command;

class CheckWhatsApp extends Command
{
    protected $signature = 'hermes:cek-wa';

    protected $description = 'Cek apakah perangkat WhatsApp Fonnte masih tersambung; email admin bila nomor platform terputus';

    public function handle(WhatsAppHealth $health): int
    {
        foreach ($health->check() as $key => $status) {
            $this->line("{$key}: {$status}");
        }

        return self::SUCCESS;
    }
}
