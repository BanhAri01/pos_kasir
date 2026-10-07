<?php

namespace App\Modules\Outlet\Policies;

use App\Enums\Permission;
use App\Models\Outlet;
use App\Models\User;

class OutletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOutlets->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOutlets->value);
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return $user->tenant_id === $outlet->tenant_id
            && $user->can(Permission::ManageOutlets->value);
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return $this->update($user, $outlet);
    }

    public function restore(User $user, Outlet $outlet): bool
    {
        return $this->update($user, $outlet);
    }
}
