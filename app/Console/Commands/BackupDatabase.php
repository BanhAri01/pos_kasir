<?php

namespace App\Console\Commands;

use App\Core\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'hermes:backup {--connection= : Nama koneksi database}';

    protected $description = 'Backup database Hermes POS (terkompres gzip) dan hapus backup lama';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $result = $backup->run($this->option('connection') ?: null);
        } catch (Throwable $e) {
            Log::error('Backup database gagal: '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup tersimpan: '.$result['path'].' ('.number_format($result['size'] / 1024, 0, ',', '.').' KB)');
        $this->line($result['pruned'].' backup lama dihapus.');

        return self::SUCCESS;
    }
}
