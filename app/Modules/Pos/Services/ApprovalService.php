<?php

namespace App\Modules\Pos\Services;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Persetujuan atasan untuk aksi penting (void, refund).
 *
 * Kalau yang melakukan sudah punya izin, tidak perlu persetujuan.
 * Kalau tidak (misalnya kasir), manajer/pemilik memasukkan PIN-nya di HP kasir.
 */
class ApprovalService
{
    /**
     * @return User|null atasan yang menyetujui (null = tidak perlu persetujuan)
     */
    public function authorize(User $actor, Permission $permission, ?int $approverId, ?string $pin): ?User
    {
        if ($actor->can($permission->value)) {
            return null;
        }

        if (! $approverId || ! $pin) {
            throw ValidationException::withMessages([
                'approval' => 'Butuh persetujuan manajer atau pemilik. Minta mereka memasukkan PIN.',
            ]);
        }

        $key = "approval:{$actor->tenant_id}:{$approverId}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'approval' => 'PIN salah terlalu sering. Tunggu '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $approver = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('is_active', true)
            ->find($approverId);

        if (! $approver || ! $approver->can(Permission::ApproveActions->value) || ! $approver->checkPin($pin)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'approval' => 'PIN persetujuan salah. Coba lagi.',
            ]);
        }

        RateLimiter::clear($key);

        return $approver;
    }

    /** Daftar orang yang boleh memberi persetujuan (untuk dipilih di layar kasir). */
    public function approvers(int $tenantId): array
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->permission(Permission::ApproveActions->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
