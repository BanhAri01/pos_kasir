<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Modules\Operations\Services\BookingService;
use App\Modules\Operations\Services\MembershipService;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pengingat WhatsApp otomatis (dijalankan penjadwal tiap 15 menit):
 * janji temu 2 jam lagi, dan paket member yang habis 3 hari lagi.
 * Pengingat kasbon sengaja manual (tombol "Ingatkan") supaya tidak menyinggung pelanggan.
 */
#[AsCommand(name: 'hermes:send-reminders')]
class SendReminders extends Command
{
    protected $signature = 'hermes:send-reminders';

    protected $description = 'Kirim pengingat WhatsApp (janji temu & paket member)';

    public function handle(TenantContext $context, BookingService $bookings, MembershipService $memberships): int
    {
        $sent = 0;

        Tenant::query()->whereIn('status', ['trial', 'active'])->each(function (Tenant $tenant) use ($context, $bookings, $memberships, &$sent) {
            try {
                $context->runAs($tenant, function (Tenant $tenant) use ($bookings, $memberships, &$sent) {
                    if ($tenant->hasModule('whatsapp_notification') && $tenant->hasModule('booking')) {
                        $sent += $bookings->sendDueReminders($tenant->timezone);
                    }
                    if ($tenant->hasModule('whatsapp_notification') && $tenant->hasModule('membership')) {
                        $sent += $memberships->sendExpiringReminders($tenant->timezone);
                    }
                });
            } catch (Throwable $e) {
                report($e); // satu usaha gagal tidak boleh menghentikan usaha lain
            }
        });

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }
}
