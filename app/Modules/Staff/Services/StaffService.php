<?php

namespace App\Modules\Staff\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Auth\Services\RegisterTenantService;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public function __construct(private TenantContext $context) {}

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $staff = new User([
                'name' => $data['name'],
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'preferences' => RegisterTenantService::defaultPreferences(),
            ]);
            $staff->tenant_id = $this->context->id();

            if (! empty($data['password'])) {
                $staff->password = $data['password'];
            }

            $staff->setPin($data['pin']);
            $staff->save();

            $staff->assignRole($data['role']);
            $staff->outlets()->sync($data['outlet_ids']);

            return $staff;
        });
    }

    public function update(User $staff, array $data): User
    {
        return DB::transaction(function () use ($staff, $data) {
            $staff->fill([
                'name' => $data['name'],
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            if (! empty($data['password'])) {
                $staff->password = $data['password'];
            }

            if (! empty($data['pin'])) {
                $staff->setPin($data['pin']);
            }

            $staff->save();

            if (! empty($data['role'])) {
                $staff->syncRoles([$data['role']]);
            }

            $staff->outlets()->sync($data['outlet_ids']);

            return $staff;
        });
    }

    public function delete(User $staff): void
    {
        $staff->delete();
    }

    public function restore(User $staff): void
    {
        $staff->restore();
    }
}
