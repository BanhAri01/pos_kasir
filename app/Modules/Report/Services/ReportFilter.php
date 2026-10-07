<?php

namespace App\Modules\Report\Services;

use Illuminate\Support\Carbon;

/**
 * Rentang tanggal (zona waktu usaha) + outlet yang dilaporkan.
 * outletIds kosong = semua outlet.
 */
final class ReportFilter
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $timezone,
        public readonly array $outletIds = [],
    ) {}

    public static function make(string $from, string $to, string $timezone, array $outletIds = []): self
    {
        return new self(
            Carbon::parse($from, $timezone)->startOfDay(),
            Carbon::parse($to, $timezone)->endOfDay(),
            $timezone,
            array_values(array_map('intval', $outletIds)),
        );
    }

    /** Batas dalam UTC untuk kolom timestamp. */
    public function utcRange(): array
    {
        return [$this->from->copy()->utc(), $this->to->copy()->utc()];
    }

    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    /** Periode sebelumnya dengan panjang sama (untuk perbandingan naik / turun). */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->copy()->subDays($days), $this->to->copy()->subDays($days), $this->timezone, $this->outletIds);
    }

    /** Selisih jam zona waktu usaha terhadap UTC. Indonesia tidak memakai jam musim panas. */
    public function offsetHours(): int
    {
        return intdiv($this->from->utcOffset(), 60);
    }
}
