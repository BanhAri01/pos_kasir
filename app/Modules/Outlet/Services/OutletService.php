<?php

namespace App\Modules\Outlet\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OutletService
{
    public function create(array $data, User $creator): Outlet
    {
        $outlet = Outlet::create($data + ['is_active' => true]);

        // Pemilik otomatis bertugas di semua outlet.
        $owners = User::sameTenant()->role('owner')->pluck('id');
        $outlet->users()->syncWithoutDetaching($owners->push($creator->id)->unique()->all());

        return $outlet;
    }

    public function update(Outlet $outlet, array $data): Outlet
    {
        $outlet->update($data);

        return $outlet;
    }

    public function delete(Outlet $outlet): void
    {
        if (Outlet::query()->count() <= 1) {
            throw ValidationException::withMessages([
                'outlet' => 'Outlet ini tidak bisa dihapus karena hanya ada satu. Usaha harus punya minimal satu outlet.',
            ]);
        }

        $outlet->delete();
    }

    public function restore(Outlet $outlet): void
    {
        $outlet->restore();
    }
}
