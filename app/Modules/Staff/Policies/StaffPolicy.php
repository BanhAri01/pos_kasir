<?php

namespace App\Modules\Staff\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Otorisasi kelola karyawan (model User di dalam satu usaha).
 * Manajer tidak boleh mengubah pemilik atau sesama manajer.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageStaff->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageStaff->value);
    }

    public function update(User $user, User $staff): bool
    {
        if ($user->tenant_id !== $staff->tenant_id || ! $user->can(Permission::ManageStaff->value)) {
            return false;
        }

        return $user->id === $staff->id || $this->canManageRoleOf($user, $staff);
    }

    public function delete(User $user, User $staff): bool
    {
        return $user->tenant_id === $staff->tenant_id
            && $user->id !== $staff->id
            && $user->can(Permission::ManageStaff->value)
            && $this->canManageRoleOf($user, $staff);
    }

    public function restore(User $user, User $staff): bool
    {
        return $this->delete($user, $staff);
    }

    private function canManageRoleOf(User $user, User $staff): bool
    {
        $staffRole = $staff->role();

        return $staffRole !== null
            && in_array($staffRole, $user->role()?->canAssign() ?? [], true);
    }
}
