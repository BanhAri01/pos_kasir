<?php

namespace App\Modules\Report\Services;

use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\ShiftService;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OwnerReportService
{
    public const DEFAULTS = [
        'daily' => true,
        'daily_at' => '21:00',
        'on_close' => false,
        'phone' => null,
    ];

    public function __construct(
        private TenantContext $context,
        private ReportService $reports,
        private WhatsAppService $whatsapp,
        private ShiftService $shifts,
    ) {}

    public static function settings(Tenant $tenant): array
    {
        $saved = $tenant->settings['owner_report'] ?? [];

        return [
            'daily' => (bool) ($saved['daily'] ?? self::DEFAULTS['daily']),
            'daily_at' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($saved['daily_at'] ?? '')) ? $saved['daily_at'] : self::DEFAULTS['daily_at'],
            'on_close' => (bool) ($saved['on_close'] ?? self::DEFAULTS['on_close']),
            'phone' => $saved['phone'] ?? null,
        ];
    }

    public function recipient(Tenant $tenant): ?string
    {
        return Phone::normalize(self::settings($tenant)['phone'] ?? null)
            ?? Phone::normalize($tenant->owner?->phone)
            ?? Phone::normalize($tenant->phone);
    }

    public function dailyText(Tenant $tenant, Carbon $day): ?string
    {
        $date = $day->toDateString();
        $filter = ReportFilter::make($date, $date, $tenant->timezone);
        $summary = $this->reports->summary($filter);

        if ($summary['transactions'] === 0) {
            return null;
        }

        $top = collect($this->reports->products($filter, 3, 'qty'))
            ->map(fn ($p, $i) => ($i + 1).'. '.$p['name'].' ('.Qty::display((string) $p['qty']).')')
            ->implode("\n");
        $payments = collect($this->reports->payments($filter))
            ->map(fn ($p) => '• '.$p['name'].': '.Rupiah::format($p['net']))
            ->implode("\n");
        $low = $this->lowStockNames();

        $lines = [
            $day->locale('id')->translatedFormat('l, j F Y'),
            '',
            'Uang masuk: *'.Rupiah::format($summary['total_collected']).'*',
            'Transaksi: '.$summary['transactions'].' (rata-rata '.Rupiah::format($summary['average']).')',
            'Untung kotor: *'.Rupiah::format($summary['gross_profit']).'*',
            'Pengeluaran: '.Rupiah::format($summary['expenses']),
            'Untung bersih: *'.Rupiah::format($summary['net_profit']).'*',
        ];
        if ($summary['unpaid'] > 0) {
            $lines[] = 'Belum dibayar (kasbon/tempo): '.Rupiah::format($summary['unpaid']);
        }
        if ($summary['refunds'] > 0) {
            $lines[] = 'Pengembalian: '.Rupiah::format($summary['refunds']);
        }
        if ($payments !== '') {
            $lines[] = '';
            $lines[] = '*Cara bayar*';
            $lines[] = $payments;
        }
        if ($top !== '') {
            $lines[] = '';
            $lines[] = '*Paling laku*';
            $lines[] = $top;
        }
        if ($low->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '⚠️ *Stok menipis*';
            $lines[] = $low->take(8)->map(fn (string $name) => '• '.$name)->implode("
");
            if ($low->count() > 8) {
                $lines[] = '... dan '.($low->count() - 8).' lainnya. Cek menu Stok > Daftar Belanja.';
            }
        }

        return implode("\n", $lines);
    }

    public function shiftText(Tenant $tenant, Shift $shift): string
    {
        $summary = $this->shifts->summary($shift);
        $shift->loadMissing(['outlet:id,name', 'user:id,name']);
        $tz = $tenant->timezone;

        $lines = [
            'Tutup kasir '.($shift->outlet?->name ?? '').' oleh '.($shift->user?->name ?? '-'),
            $shift->opened_at->timezone($tz)->format('H:i').' - '.($shift->closed_at ?? now())->timezone($tz)->format('H:i'),
            '',
            'Penjualan: *'.Rupiah::format($summary['sales_total']).'* ('.$summary['sales_count'].' transaksi)',
            'Uang di laci seharusnya: '.Rupiah::format($shift->expected_cash ?? $summary['expected_cash']),
            'Uang dihitung: '.Rupiah::format((int) $shift->counted_cash),
        ];
        $diff = (int) $shift->cash_difference;
        if ($diff !== 0) {
            $lines[] = ($diff < 0 ? '⚠️ Uang kurang ' : 'Uang lebih ').Rupiah::format(abs($diff));
        }
        foreach ($summary['by_method'] as $m) {
            $lines[] = '• '.$m['name'].': '.Rupiah::format($m['total']);
        }

        return implode("\n", $lines);
    }

    public function sendDaily(Tenant $tenant, Carbon $day, bool $force = false): bool
    {
        return $this->context->runAs($tenant, function () use ($tenant, $day, $force) {
            $to = $this->recipient($tenant);
            if (! $to || (! $force && ! $this->claim($tenant, $day->toDateString()))) {
                return false;
            }

            $text = $this->dailyText($tenant, $day);
            if ($text === null) {
                return false;
            }

            return $this->whatsapp->sendTemplate('daily_report', $to, ['isi' => $text]) !== null;
        });
    }

    public function shiftClosed(Shift $shift): void
    {
        $tenant = $this->context->get();
        if (! $tenant || ! self::settings($tenant)['on_close'] || ! ($to = $this->recipient($tenant))) {
            return;
        }

        try {
            $this->whatsapp->sendTemplate('daily_report', $to, ['isi' => $this->shiftText($tenant, $shift)], $shift);
        } catch (Throwable $e) {
            Log::warning("Laporan tutup kasir gagal dikirim: {$e->getMessage()}");
        }
    }

    public function dueTenants(): Collection
    {
        return Tenant::query()->with('owner:id,phone')->whereIn('status', ['trial', 'active'])->get()
            ->filter(function (Tenant $tenant) {
                $settings = self::settings($tenant);
                if (! $settings['daily'] || ! $tenant->hasAccess()) {
                    return false;
                }
                $now = now($tenant->timezone);

                return $now->format('H:i') >= $settings['daily_at']
                    && ($tenant->settings['owner_report_sent_on'] ?? null) !== $now->toDateString();
            });
    }

    private function claim(Tenant $tenant, string $date): bool
    {
        return DB::transaction(function () use ($tenant, $date) {
            $fresh = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();
            $settings = $fresh->settings ?? [];
            if (($settings['owner_report_sent_on'] ?? null) === $date) {
                return false;
            }
            $settings['owner_report_sent_on'] = $date;
            $fresh->forceFill(['settings' => $settings])->save();

            return true;
        });
    }

    private function lowStockNames(): Collection
    {
        return Product::query()->where('is_active', true)->where('track_stock', true)->where('min_stock', '>', 0)
            ->whereHas('stocks', fn ($q) => $q->whereColumn('stocks.qty', '<=', 'products.min_stock'))
            ->orderBy('name')
            ->pluck('name');
    }
}
