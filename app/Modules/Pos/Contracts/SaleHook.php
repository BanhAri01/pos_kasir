<?php

namespace App\Modules\Pos\Contracts;

use App\Models\User;
use App\Modules\Pos\Models\Sale;

/**
 * Langkah tambahan setelah penjualan tersimpan, milik modul tertentu
 * (komisi, member, kasbon, layar dapur, status laundry, WhatsApp, ...).
 *
 * Dijalankan DI DALAM database transaction penjualan: kalau melempar exception,
 * seluruh penjualan dibatalkan. Hook wajib memeriksa sendiri apakah modulnya menyala.
 */
interface SaleHook
{
    /**
     * @param  array<string, mixed>  $data  data penjualan dari kasir
     * @param  bool  $strict  false = sinkron dari kasir offline (jangan menolak hal yang sudah terjadi)
     */
    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void;
}
