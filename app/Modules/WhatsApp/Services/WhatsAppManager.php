<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppGateway;
use App\Modules\WhatsApp\Drivers\CloudApiDriver;
use App\Modules\WhatsApp\Drivers\FonnteDriver;
use App\Modules\WhatsApp\Drivers\LogDriver;
use App\Modules\WhatsApp\Drivers\WablasDriver;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use InvalidArgumentException;

/**
 * Memilih penyedia WhatsApp:
 *   1. akun milik usaha sendiri (kalau diisi & aktif), lalu
 *   2. nomor pusat milik platform (keputusan yang disetujui: default memakai nomor pusat),
 *   3. terakhir pengaturan .env (HERMES_WA_DRIVER, default "log").
 */
class WhatsAppManager
{
    public function for(?Tenant $tenant): array
    {
        $account = $tenant?->allows('own_whatsapp')
            ? WhatsappAccount::query()->where('tenant_id', $tenant->id)->where('is_active', true)->first()
            : null;
        $account ??= WhatsappAccount::query()->whereNull('tenant_id')->where('is_active', true)->first();

        if ($account) {
            return [$account->provider, $this->make($account->provider, $account->credentials ?? [])];
        }

        $driver = config('hermes.whatsapp.driver', 'log');

        return [$driver, $this->make($driver, config('hermes.whatsapp.credentials', []))];
    }

    public function make(string $provider, array $credentials): WhatsAppGateway
    {
        return match ($provider) {
            'fonnte' => new FonnteDriver((string) ($credentials['token'] ?? '')),
            'wablas' => new WablasDriver((string) ($credentials['token'] ?? ''), (string) ($credentials['domain'] ?? 'https://solo.wablas.com')),
            'cloud_api' => new CloudApiDriver((string) ($credentials['token'] ?? ''), (string) ($credentials['phone_number_id'] ?? '')),
            'log' => new LogDriver,
            default => throw new InvalidArgumentException("Penyedia WhatsApp tidak dikenal: {$provider}"),
        };
    }
}
