<?php

namespace App\Core\Tenancy;

use App\Enums\Permission;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Outlet yang sedang dipakai pengguna (disimpan di session).
 *
 * - Pemilik / yang punya hak "lihat semua outlet": boleh memilih outlet mana pun.
 * - Lainnya: hanya outlet tempat ia ditugaskan.
 */
class CurrentOutlet
{
    private const SESSION_KEY = 'current_outlet_id';

    private ?Outlet $resolved = null;

    /** @return Collection<int, Outlet> */
    public function accessible(User $user): Collection
    {
        $query = Outlet::query()->where('is_active', true)->orderBy('name');

        if (! $user->can(Permission::ViewAllOutlets->value)) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query->get();
    }

    public function get(User $user): ?Outlet
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $outlets = $this->accessible($user);
        // Belum memilih: pakai outlet tempat ia ditugaskan (yang paling awal), bukan urut abjad.
        $id = session(self::SESSION_KEY)
            ?? $user->outlets()->orderBy('outlets.id')->value('outlets.id');

        return $this->resolved = $outlets->firstWhere('id', $id) ?? $outlets->first();
    }

    public function id(User $user): ?int
    {
        return $this->get($user)?->id;
    }

    public function switch(User $user, int $outletId): bool
    {
        $outlet = $this->accessible($user)->firstWhere('id', $outletId);

        if (! $outlet) {
            return false;
        }

        session([self::SESSION_KEY => $outlet->id]);
        $this->resolved = $outlet;

        return true;
    }
}
