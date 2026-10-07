<?php

namespace App\Enums;

/**
 * Daftar hak akses. Kode dipakai di backend; pengguna hanya melihat role (Pemilik, Kasir, ...).
 * Hak akses untuk fase berikutnya sudah didaftarkan supaya role tidak perlu diubah lagi.
 */
enum Permission: string
{
    // Pengaturan usaha
    case ManageBusiness = 'manage_business';
    case ManageModules = 'manage_modules';
    case ManageOutlets = 'manage_outlets';
    case ManageStaff = 'manage_staff';

    // Kasir & transaksi
    case UsePos = 'use_pos';
    case GiveDiscount = 'give_discount';
    case ChangePrice = 'change_price';
    case VoidSale = 'void_sale';
    case RefundSale = 'refund_sale';
    case ApproveActions = 'approve_actions'; // memberi otorisasi void/refund dengan PIN

    // Data
    case ManageProducts = 'manage_products';
    case ManageStock = 'manage_stock';
    case ManageCustomers = 'manage_customers';
    case ManageExpenses = 'manage_expenses';
    case ViewReports = 'view_reports';
    case ViewAllOutlets = 'view_all_outlets';

    // Karyawan (barista, dapur, kapster, trainer)
    case ViewOwnWork = 'view_own_work';
}
