<?php

namespace App\Modules\Auth\Services;

use App\Core\Tenancy\TenantScope;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Mengingat HP/tablet toko. Setelah pemilik/manajer login sekali di sebuah HP,
 * kasir & karyawan cukup memilih nama lalu memasukkan PIN di HP itu.
 */
class DeviceService
{
    public const COOKIE = 'hermes_device';

    private const COOKIE_MINUTES = 60 * 24 * 365 * 5; // 5 tahun

    /** Perangkat yang tercatat untuk browser ini (atau null). */
    public function fromRequest(Request $request): ?Device
    {
        $uuid = $request->cookie(self::COOKIE);

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            return null;
        }

        return Device::allTenants()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->first();
    }

    /** Catat / perbarui perangkat ini untuk usaha milik user, lalu simpan cookie. */
    public function remember(Request $request, User $user): Device
    {
        $device = $this->fromRequest($request);

        if (! $device || $device->tenant_id !== $user->tenant_id) {
            $device = new Device([
                'uuid' => (string) Str::uuid(),
                'code' => $this->nextCode($user->tenant_id),
                // Saat login tenant belum aktif, jadi scope tenant dilewati di sini
                // (aman: tetap dibatasi ke outlet milik user ini sendiri).
                'outlet_id' => $user->outlets()->withoutGlobalScope(TenantScope::class)->value('outlets.id'),
                'is_active' => true,
            ]);
            $device->tenant_id = $user->tenant_id;
        }

        $device->user_agent = Str::limit((string) $request->userAgent(), 250, '');
        $device->last_seen_at = now();
        $device->save();

        Cookie::queue(self::COOKIE, $device->uuid, self::COOKIE_MINUTES);

        return $device;
    }

    /** Kode pendek perangkat: A, B, ... Z, AA, AB, ... (dipakai di nomor nota offline). */
    private function nextCode(int $tenantId): string
    {
        $index = Device::allTenants()->where('tenant_id', $tenantId)->count();

        $code = '';
        do {
            $code = chr(65 + ($index % 26)).$code;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $code;
    }
}
