<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Assistant\Services\AiAgent;
use Illuminate\Console\Command;

class PosAssistantCommand extends Command
{
    protected $signature = 'pos:asisten {pertanyaan? : Pertanyaan analisis toko} {--tenant=1 : ID tenant} {--driver=demo : Driver LLM (demo atau claude)}';

    protected $description = 'Tanya Asisten AI Toko (Hermes POS AI) dari CLI';

    public function handle(AiAgent $agent): int
    {
        $tenantId = (int) $this->option('tenant');
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant dengan ID {$tenantId} tidak ditemukan.");
            return self::FAILURE;
        }

        $pertanyaan = $this->argument('pertanyaan');

        if (! $pertanyaan) {
            $pertanyaan = $this->ask('Silakan masukkan pertanyaan untuk Asisten Toko');
        }

        if (! $pertanyaan) {
            $this->warn('Pertanyaan tidak boleh kosong.');
            return self::SUCCESS;
        }

        $driver = $this->option('driver') ?: 'demo';

        $this->info("Menghubungi Hermes POS AI untuk tenant [{$tenant->name}] menggunakan driver [{$driver}]...");
        $this->line('');

        $response = $agent->ask(
            prompt: $pertanyaan,
            tenant: $tenant,
            channel: 'cli',
            driver: $driver
        );

        $this->info('--- JAWABAN ASISTEN TOKO ---');
        $this->line($response->message);
        $this->line('');
        $this->comment("Token terpakai: {$response->tokens_used} | Estimasi Biaya: Rp " . number_format($response->cost_idr, 2, ',', '.'));

        return self::SUCCESS;
    }
}
