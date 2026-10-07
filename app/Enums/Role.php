<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'kasir';
    case Staff = 'karyawan';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Manager => 'Manajer',
            self::Cashier => 'Kasir',
            self::Staff => 'Karyawan',
        };
    }

    /** Penjelasan singkat yang tampil saat memilih role di form karyawan. */
    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Bisa mengatur semuanya.',
            self::Manager => 'Bisa mengatur barang, karyawan, dan melihat laporan.',
            self::Cashier => 'Hanya untuk melayani pembeli di kasir.',
            self::Staff => 'Barista, dapur, kapster, atau trainer. Melihat pekerjaannya sendiri.',
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Manager => array_values(array_filter(
                Permission::cases(),
                fn (Permission $p) => ! in_array($p, [Permission::ManageBusiness, Permission::ManageModules], true),
            )),
            self::Cashier => [
                Permission::UsePos,
                Permission::GiveDiscount,
                Permission::ManageCustomers,
                Permission::ManageExpenses,
                Permission::ViewOwnWork,
            ],
            self::Staff => [
                Permission::ViewOwnWork,
            ],
        };
    }

    /** Role yang boleh dibuat/diubah oleh role ini. */
    public function canAssign(): array
    {
        return match ($this) {
            self::Owner => [self::Manager, self::Cashier, self::Staff],
            self::Manager => [self::Cashier, self::Staff],
            default => [],
        };
    }
}
