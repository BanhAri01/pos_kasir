<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Report\Services\OwnerReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOwnerReports extends Command
{
    protected $signature = 'hermes:owner-reports';

    protected $description = 'Kirim laporan harian ke WhatsApp pemilik usaha sesuai jam yang dipilih';

    public function handle(OwnerReportService $reports): int
    {
        $sent = 0;

        $reports->dueTenants()->each(function (Tenant $tenant) use ($reports, &$sent) {
            try {
                $sent += $reports->sendDaily($tenant, now($tenant->timezone)) ? 1 : 0;
            } catch (Throwable $e) {
                Log::warning("Laporan harian usaha {$tenant->id} gagal: {$e->getMessage()}");
            }
        });

        $this->info("{$sent} laporan harian dikirim.");

        return self::SUCCESS;
    }
}
