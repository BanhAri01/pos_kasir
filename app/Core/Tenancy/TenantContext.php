<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;

/**
 * Menyimpan tenant (usaha) yang sedang aktif selama satu request / job.
 *
 * Diisi oleh middleware SetTenantContext dari user yang login. Semua model
 * dengan trait BelongsToTenant membaca nilai dari sini.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Jalankan callback sebagai tenant tertentu, lalu kembalikan konteks sebelumnya.
     * Berguna untuk job antrean, command, dan test.
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
