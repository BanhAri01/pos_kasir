<?php

namespace App\Core\Support;

use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen berurutan per usaha per hari, aman dipakai bersamaan (row lock).
 *
 *   next('SM')  ->  "SM-251005-0001"
 *
 * Catatan: nomor nota penjualan dibuat di perangkat kasir ({kode HP}-{yymmdd}-{urut})
 * supaya tetap berurutan saat offline (Fase 5).
 */
class NumberSequence
{
    public function __construct(private TenantContext $context) {}

    public function next(string $prefix, ?string $timezone = null): string
    {
        $tenantId = $this->context->id();
        $period = now($timezone ?? $this->context->get()?->timezone ?? 'Asia/Jakarta')->format('ymd');

        $number = DB::transaction(function () use ($tenantId, $prefix, $period) {
            $row = DB::table('number_sequences')
                ->where(['tenant_id' => $tenantId, 'key' => $prefix, 'period' => $period])
                ->lockForUpdate()
                ->first();

            if ($row) {
                $next = $row->last_number + 1;
                DB::table('number_sequences')->where('id', $row->id)->update(['last_number' => $next]);

                return $next;
            }

            DB::table('number_sequences')->insert([
                'tenant_id' => $tenantId, 'key' => $prefix, 'period' => $period, 'last_number' => 1,
            ]);

            return 1;
        });

        return sprintf('%s-%s-%04d', $prefix, $period, $number);
    }
}
