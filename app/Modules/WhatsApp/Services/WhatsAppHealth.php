<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use App\Modules\WhatsApp\Support\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Memantau perangkat WhatsApp Fonnte: nomor pusat, nomor Asisten Toko, dan nomor milik usaha.
 * Terputus terdeteksi dari cek berkala ke Fonnte atau dari pesan yang gagal "disconnected".
 * Nomor milik platform: email ke admin + banner untuk admin & usaha yang memakainya.
 * Nomor milik usaha: banner untuk pemilik usaha itu saja (hanya ia yang bisa menyambungkan lagi).
 */
class WhatsAppHealth
{
    private const KEY = 'wa-health:';

    private const INDEX = 'wa-health:down';

    private const REALERT_HOURS = 6;

    /** @return array<string, array{token: string, label: string, tenant_id: ?int}> */
    public function devices(): array
    {
        $devices = [];

        $platform = WhatsappAccount::query()->whereNull('tenant_id')->where('is_active', true)->first();
        $platformToken = match (true) {
            $platform !== null => $platform->provider === 'fonnte' ? (string) ($platform->credentials['token'] ?? '') : '',
            default => config('hermes.whatsapp.driver') === 'fonnte' ? (string) config('hermes.whatsapp.credentials.token') : '',
        };
        if ($platformToken !== '') {
            $devices['platform'] = ['token' => $platformToken, 'label' => 'Nomor WA pusat Kasira', 'tenant_id' => null];
        }

        $assistant = (string) config('hermes.assistant.fonnte_token');
        if ($assistant !== '' && $assistant !== $platformToken) {
            $devices['asisten'] = ['token' => $assistant, 'label' => 'Nomor WA Asisten Toko', 'tenant_id' => null];
        }

        WhatsappAccount::query()->with('tenant')->whereNotNull('tenant_id')->where('is_active', true)->where('provider', 'fonnte')->get()
            ->filter(fn (WhatsappAccount $a) => $a->tenant?->allows('own_whatsapp') && filled($a->credentials['token'] ?? null))
            ->each(function (WhatsappAccount $a) use (&$devices) {
                $devices['usaha:'.$a->tenant_id] = ['token' => (string) $a->credentials['token'], 'label' => 'Nomor WA '.$a->tenant->name, 'tenant_id' => $a->tenant_id];
            });

        return $devices;
    }

    public function check(): array
    {
        $result = [];

        foreach ($this->devices() as $key => $device) {
            try {
                $response = Http::asForm()->timeout(15)->withHeaders(['Authorization' => $device['token']])->post(config('hermes.whatsapp.fonnte_device_url'));
            } catch (ConnectionException) {
                $result[$key] = 'tidak bisa menghubungi Fonnte';

                continue;
            }

            if ($response->json('status') !== true) {
                $this->markDown($key, $device, (string) ($response->json('reason') ?? 'Fonnte menolak token'));
                $result[$key] = 'bermasalah';
            } elseif ($response->json('device_status') === 'connect') {
                $this->markUp($key, $device);
                $result[$key] = 'tersambung';
            } else {
                $this->markDown($key, $device, 'Perangkat terputus ('.($response->json('device_status') ?? '-').')');
                $result[$key] = 'terputus';
            }
        }

        return $result;
    }

    public function report(string $token, SendResult $result): void
    {
        if ($result->ok) {
            if (Cache::get(self::INDEX, []) === []) {
                return;
            }
            if ($found = $this->find($token)) {
                $this->markUp(...$found);
            }

            return;
        }

        if (str_contains(strtolower((string) $result->error), 'disconnect') && ($found = $this->find($token))) {
            $this->markDown(...$found, reason: (string) $result->error);
        }
    }

    /** @return array<string, array{label: string, tenant_id: ?int, since: Carbon}> */
    public function down(): array
    {
        return collect(Cache::get(self::INDEX, []))
            ->mapWithKeys(fn (string $key) => [$key => Cache::get(self::KEY.$key)])
            ->filter()
            ->map(fn (array $s) => ['label' => $s['label'], 'tenant_id' => $s['tenant_id'], 'since' => Carbon::parse($s['since'])])
            ->all();
    }

    /** Peringatan yang perlu dilihat pengguna ini: admin melihat semua, usaha hanya nomor yang ia pakai. */
    public function noticesFor(?User $user, ?Tenant $tenant): array
    {
        $down = $this->down();
        if ($down === [] || ! $user) {
            return [];
        }

        if ($user->is_super_admin) {
            return array_values(array_map(fn (array $d) => ['text' => $d['label'].' terputus sejak '.$d['since']->format('d/m H:i').'.', 'fix' => $d['tenant_id'] === null], $down));
        }

        if (! $tenant || ! $user->can('manage_business')) {
            return [];
        }

        $notices = [];
        $ownKey = 'usaha:'.$tenant->id;
        $usesOwn = $tenant->allows('own_whatsapp') && WhatsappAccount::query()->where('tenant_id', $tenant->id)->where('is_active', true)->exists();

        if (isset($down[$ownKey])) {
            $notices[] = ['text' => 'Nomor WhatsApp usaha Anda terputus sejak '.$down[$ownKey]['since']->format('d/m H:i').'. Buka Fonnte lalu scan ulang QR dari HP nomor itu.', 'fix' => true];
        } elseif (! $usesOwn && isset($down['platform'])) {
            $notices[] = ['text' => 'WhatsApp Kasira sedang terputus. Struk & laporan WhatsApp tertunda; admin Kasira sudah diberi tahu.', 'fix' => false];
        }
        if (isset($down['asisten']) && $tenant->allows('assistant')) {
            $notices[] = ['text' => 'Asisten Toko lewat WhatsApp sedang terputus; tanya lewat aplikasi tetap bisa. Admin Kasira sudah diberi tahu.', 'fix' => false];
        }

        return $notices;
    }

    /** @return array{0: string, 1: array}|null */
    private function find(string $token): ?array
    {
        foreach ($this->devices() as $key => $device) {
            if (hash_equals($device['token'], $token)) {
                return [$key, $device];
            }
        }

        return null;
    }

    private function markDown(string $key, array $device, string $reason): void
    {
        $state = Cache::get(self::KEY.$key);

        if ($state !== null) {
            if (Carbon::parse($state['alerted_at'])->lte(now()->subHours(self::REALERT_HOURS))) {
                $this->alert($device, 'masih terputus sejak '.Carbon::parse($state['since'])->format('d M H:i'), $reason);
                Cache::forever(self::KEY.$key, [...$state, 'alerted_at' => now()->toIso8601String()]);
            }

            return;
        }

        Cache::forever(self::KEY.$key, [
            'label' => $device['label'], 'tenant_id' => $device['tenant_id'], 'reason' => $reason,
            'since' => now()->toIso8601String(), 'alerted_at' => now()->toIso8601String(),
        ]);
        Cache::forever(self::INDEX, array_values(array_unique([...Cache::get(self::INDEX, []), $key])));
        Log::warning("WhatsApp {$device['label']} terputus: {$reason}");
        $this->alert($device, 'terputus', $reason);
    }

    private function markUp(string $key, array $device): void
    {
        $state = Cache::get(self::KEY.$key);
        if ($state === null) {
            return;
        }

        Cache::forget(self::KEY.$key);
        Cache::forever(self::INDEX, array_values(array_diff(Cache::get(self::INDEX, []), [$key])));
        Log::info("WhatsApp {$device['label']} tersambung lagi");

        if ($device['tenant_id'] === null) {
            $this->mail('[Kasira] WhatsApp tersambung lagi', $device['label'].' sudah tersambung lagi (terputus sejak '.Carbon::parse($state['since'])->format('d M Y H:i').').');
        }
    }

    private function alert(array $device, string $what, string $reason): void
    {
        if ($device['tenant_id'] !== null) {
            return;
        }

        $this->mail(
            '[Kasira] PENTING: WhatsApp '.$what,
            $device['label']." {$what}.\nKeterangan dari Fonnte: {$reason}\n\n"
            ."Selama terputus, struk, laporan harian, pengingat, dan Asisten Toko lewat WhatsApp TIDAK terkirim.\n\n"
            ."Cara menyambungkan lagi:\n"
            ."1. Buka https://fonnte.com lalu masuk.\n"
            ."2. Menu Device, pilih nomor ini, tekan Connect.\n"
            ."3. Scan QR dari HP nomor WhatsApp itu (WhatsApp > Perangkat tertaut > Tautkan perangkat).\n\n"
            .'Supaya tidak sering putus: HP khusus, selalu dicas & tersambung Wi-Fi, penghemat baterai untuk WhatsApp dimatikan.'
        );
    }

    private function mail(string $subject, string $body): void
    {
        $to = config('hermes.whatsapp.alert_email');
        if (! $to) {
            return;
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (Throwable $e) {
            Log::warning('Email peringatan WhatsApp gagal dikirim: '.$e->getMessage());
        }
    }
}
