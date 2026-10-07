<?php

namespace App\Modules\Catalog\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Modules\Catalog\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageProducts->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageProducts->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id && $user->can(Permission::ManageProducts->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    /** "Habis hari ini" juga boleh diubah kasir dari layar kasir. */
    public function markSoldOut(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id
            && ($user->can(Permission::ManageProducts->value) || $user->can(Permission::UsePos->value));
    }
}
