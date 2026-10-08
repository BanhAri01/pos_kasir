<?php

namespace App\Modules\Billing\Services;

use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use Illuminate\Validation\ValidationException;

class PlanGuard
{
    public function outletCount(Tenant $tenant): int
    {
        return Outlet::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count();
    }

    public function staffCount(Tenant $tenant): int
    {
        return User::query()->where('tenant_id', $tenant->id)->where('id', '!=', $tenant->owner_id)->whereNull('deleted_at')->count();
    }

    public function ensureCanAddOutlet(Tenant $tenant): void
    {
        $limit = $tenant->limit('outlets');
        if ($limit !== null && $this->outletCount($tenant) >= $limit) {
            throw ValidationException::withMessages([
                'name' => "Paket {$tenant->planLabel()} hanya untuk {$limit} outlet. Naikkan paket di menu Langganan untuk menambah outlet.",
            ]);
        }
    }

    public function ensureCanAddStaff(Tenant $tenant): void
    {
        $limit = $tenant->limit('staff');
        if ($limit !== null && $this->staffCount($tenant) >= $limit) {
            throw ValidationException::withMessages([
                'name' => "Paket {$tenant->planLabel()} hanya untuk {$limit} karyawan. Naikkan paket di menu Langganan untuk menambah karyawan.",
            ]);
        }
    }

    public function usesOwnWhatsapp(Tenant $tenant): bool
    {
        return $tenant->allows('own_whatsapp')
            && WhatsappAccount::query()->where('tenant_id', $tenant->id)->where('is_active', true)->exists();
    }

    public function whatsappQuotaLeft(Tenant $tenant): ?int
    {
        if ($this->usesOwnWhatsapp($tenant)) {
            return null;
        }

        $limit = $tenant->limit('whatsapp');

        return $limit === null ? null : max(0, $limit - $tenant->whatsappUsedThisMonth());
    }

    public function usage(Tenant $tenant): array
    {
        return [
            'outlets' => ['used' => $this->outletCount($tenant), 'limit' => $tenant->limit('outlets')],
            'staff' => ['used' => $this->staffCount($tenant), 'limit' => $tenant->limit('staff')],
            'whatsapp' => [
                'used' => $tenant->whatsappUsedThisMonth(),
                'limit' => $this->usesOwnWhatsapp($tenant) ? null : $tenant->limit('whatsapp'),
                'own' => $this->usesOwnWhatsapp($tenant),
            ],
        ];
    }
}
