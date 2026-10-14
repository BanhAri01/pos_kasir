<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Assistant\Models\AssistantTopup;
use App\Modules\Assistant\Services\Agent;
use App\Modules\Assistant\Services\Quota;
use Illuminate\Console\Command;

/**
 * Untuk admin platform:
 *   php artisan pos:asisten 12 "penjualan hari ini?"   -> uji tanya (memakai pulsa usaha itu)
 *   php artisan pos:asisten 12 --pulsa                  -> lihat sisa pulsa
 *   php artisan pos:asisten 12 --tambah=15000           -> tambah pulsa bulan ini (setelah usaha membayar)
 */
class PosAssistantCommand extends Command
{
    protected $signature = 'pos:asisten {tenant : ID usaha} {pertanyaan? : Pertanyaan uji} {--pulsa : Tampilkan sisa pulsa} {--tambah= : Tambah pulsa bulan ini (rupiah)} {--catatan= : Catatan tambah pulsa}';

    protected $description = 'Asisten Toko: uji tanya, cek pulsa, atau tambah pulsa usaha';

    public function handle(Agent $agent): int
    {
        $tenant = Tenant::find((int) $this->argument('tenant'));
        if (! $tenant) {
            $this->error('Usaha tidak ditemukan.');

            return self::FAILURE;
        }

        if ($this->option('tambah') !== null) {
            $amount = (int) $this->option('tambah');
            if ($amount <= 0 || $amount > 1_000_000) {
                $this->error('Nominal tambah pulsa harus 1 sampai 1.000.000.');

                return self::FAILURE;
            }
            if (! $this->confirm("Tambah pulsa Rp{$amount} untuk [{$tenant->name}]?", true)) {
                return self::SUCCESS;
            }

            AssistantTopup::allTenants()->forceCreate([
                'tenant_id' => $tenant->id,
                'period' => Quota::for($tenant)->period(),
                'amount' => $amount,
                'note' => $this->option('catatan') ?: 'Tambah pulsa oleh admin',
            ]);
        }

        if ($question = $this->argument('pertanyaan')) {
            $answer = $agent->ask($tenant, (string) $question, Agent::WEB);
            $this->line($answer->text);
            $this->comment("Status: {$answer->status} | Biaya: Rp".number_format($answer->cost, 2, ',', '.'));
        }

        $quota = Quota::for($tenant);
        $this->info("[{$tenant->name}] Pulsa {$quota->percentLeft()}% (sisa Rp".number_format($quota->remaining(), 0, ',', '.').' dari Rp'.number_format($quota->budget(), 0, ',', '.').')');

        return self::SUCCESS;
    }
}
