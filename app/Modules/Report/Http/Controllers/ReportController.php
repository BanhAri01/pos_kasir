<?php

namespace App\Modules\Report\Http\Controllers;

use App\Core\Support\Xlsx\XlsxWriter;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\Report\Services\ReportFilter;
use App\Modules\Report\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan: ringkasan untung-rugi, grafik penjualan, barang terlaris, cara bayar, karyawan, outlet.
 * Bisa diunduh sebagai Excel atau dicetak / disimpan PDF dari browser.
 */
class ReportController extends Controller
{
    public const TABS = ['ringkasan', 'barang', 'pembayaran', 'karyawan', 'outlet'];

    public function __construct(private ReportService $reports, private TenantContext $context, private CurrentOutlet $outlet) {}

    public function index(Request $request): Response
    {
        $filter = $this->filter($request);
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'ringkasan';

        $data = match ($tab) {
            'ringkasan' => [
                'summary' => $this->reports->summaryWithComparison($filter),
                'series' => $this->reports->series($filter),
                'hours' => $this->reports->byHour($filter),
                'expenseCategories' => $this->reports->expenseCategories($filter),
            ],
            'barang' => [
                'products' => $this->reports->products($filter, 100, $request->query('urut', 'revenue')),
                'categories' => $this->reports->categories($filter),
            ],
            'pembayaran' => ['payments' => $this->reports->payments($filter), 'summary' => $this->reports->summary($filter)],
            'karyawan' => ['cashiers' => $this->reports->cashiers($filter), 'staff' => $this->reports->staff($filter)],
            'outlet' => ['outletSales' => $this->reports->outlets($filter)],
        };

        return Inertia::render('Reports/Index', [
            ...$data,
            'tab' => $tab,
            'filters' => $this->filterProps($request, $filter),
            'presets' => ReportService::presets($this->context->get()->timezone),
            'outlets' => $this->allowedOutlets($request),
        ]);
    }

    /** Unduh Excel: semua bagian laporan dalam satu file, satu sheet per bagian. */
    public function export(Request $request): HttpResponse
    {
        $filter = $this->filter($request);
        $s = $this->reports->summary($filter);
        $period = $this->periodLabel($filter);

        $xlsx = (new XlsxWriter)
            ->sheet('Ringkasan', [
                ['Laporan '.$this->context->get()->name, $period],
                ['Uang masuk', $s['total_collected']],
                ['Jumlah transaksi', $s['transactions']],
                ['Rata-rata per transaksi', $s['average']],
                ['Penjualan kotor', $s['gross_sales']],
                ['Diskon', $s['discounts']],
                ['Pengembalian barang', $s['refunds']],
                ['Penjualan bersih', $s['net_sales']],
                ['Pajak', $s['tax']],
                ['Biaya layanan', $s['service']],
                ['Modal barang terjual', $s['cogs']],
                ['Untung kotor', $s['gross_profit']],
                ['Pengeluaran', $s['expenses']],
                ['Untung bersih', $s['net_profit']],
                ['Belum dibayar (piutang)', $s['unpaid']],
            ], money: [1])
            ->sheet('Per Hari', [
                ['Tanggal', 'Transaksi', 'Penjualan'],
                ...array_map(fn ($p) => [$p['key'], $p['transactions'], $p['total']], $this->reports->series($filter)),
            ], money: [2])
            ->sheet('Barang', [
                ['Barang', 'Kategori', 'Jumlah', 'Satuan', 'Penjualan', 'Modal', 'Untung'],
                ...array_map(fn ($p) => [$p['name'], $p['category'], $p['qty'], $p['unit'], $p['revenue'], $p['cost'], $p['profit']], $this->reports->products($filter, PHP_INT_MAX)),
            ], money: [4, 5, 6])
            ->sheet('Cara Bayar', [
                ['Cara bayar', 'Transaksi', 'Diterima', 'Dikembalikan', 'Bersih'],
                ...array_map(fn ($p) => [$p['name'], $p['transactions'], $p['total'], $p['refunds'], $p['net']], $this->reports->payments($filter)),
            ], money: [2, 3, 4])
            ->sheet('Kasir', [
                ['Kasir', 'Transaksi', 'Penjualan', 'Rata-rata'],
                ...array_map(fn ($c) => [$c['name'], $c['transactions'], $c['total'], $c['average']], $this->reports->cashiers($filter)),
            ], money: [2, 3])
            ->sheet('Pengeluaran', [
                ['Jenis', 'Jumlah'],
                ...array_map(fn ($e) => [$e['name'], $e['total']], $this->reports->expenseCategories($filter)),
            ], money: [1]);

        $staff = $this->reports->staff($filter);
        if ($staff) {
            $xlsx->sheet('Karyawan', [['Karyawan', 'Pekerjaan', 'Penjualan', 'Komisi'], ...array_map(fn ($r) => [$r['name'], $r['jobs'], $r['revenue'], $r['commission']], $staff)], money: [2, 3]);
        }

        $filename = 'laporan-'.$filter->from->format('Ymd').($filter->days() > 1 ? '-'.$filter->to->format('Ymd') : '').'.xlsx';

        return response($xlsx->output(), 200, XlsxWriter::headers($filename));
    }

    /** Versi cetak (A4). Di HP / laptop pilih "Simpan sebagai PDF" untuk dikirim lewat WhatsApp. */
    public function print(Request $request): View
    {
        $filter = $this->filter($request);

        return view('documents.report', [
            'tenant' => $this->context->get(),
            'period' => $this->periodLabel($filter),
            'outletLabel' => $filter->outletIds ? Outlet::query()->whereIn('id', $filter->outletIds)->pluck('name')->join(', ') : 'Semua outlet',
            'summary' => $this->reports->summary($filter),
            'products' => $this->reports->products($filter, 20),
            'payments' => $this->reports->payments($filter),
            'cashiers' => $this->reports->cashiers($filter),
            'expenses' => $this->reports->expenseCategories($filter),
            'series' => $filter->days() > 1 ? $this->reports->series($filter) : [],
            'printedAt' => now($this->context->get()->timezone)->locale('id')->translatedFormat('j F Y H:i'),
        ]);
    }

    private function filter(Request $request): ReportFilter
    {
        $timezone = $this->context->get()->timezone;
        $today = now($timezone)->toDateString();
        $from = $this->date($request->query('dari')) ?? $today;
        $to = $this->date($request->query('sampai')) ?? $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return ReportFilter::make($from, $to, $timezone, $this->outletIds($request));
    }

    /** Outlet yang dilaporkan: "semua" hanya untuk yang boleh melihat semua outlet. */
    private function outletIds(Request $request): array
    {
        $user = $request->user();
        $choice = $request->query('outlet');

        if ($choice === 'semua' && $user->can(Permission::ViewAllOutlets->value)) {
            return [];
        }

        $allowed = collect($this->allowedOutlets($request))->pluck('id');
        if (is_numeric($choice) && $allowed->contains((int) $choice)) {
            return [(int) $choice];
        }

        return [$this->outlet->id($user)];
    }

    private function allowedOutlets(Request $request): array
    {
        $user = $request->user();
        $query = Outlet::query()->where('is_active', true)->orderBy('id');
        if (! $user->can(Permission::ViewAllOutlets->value)) {
            $query->whereIn('id', $user->outlets()->pluck('outlets.id'));
        }

        return $query->get(['id', 'name'])->toArray();
    }

    private function filterProps(Request $request, ReportFilter $filter): array
    {
        return [
            'dari' => $filter->from->toDateString(),
            'sampai' => $filter->to->toDateString(),
            'outlet' => $filter->outletIds ? (string) $filter->outletIds[0] : 'semua',
            'urut' => $request->query('urut', 'revenue'),
            'label' => $this->periodLabel($filter),
            'canAllOutlets' => $request->user()->can(Permission::ViewAllOutlets->value),
        ];
    }

    private function periodLabel(ReportFilter $f): string
    {
        $fmt = fn ($d) => $d->locale('id')->translatedFormat('j F Y');

        return $f->days() === 1 ? $f->from->locale('id')->translatedFormat('l, j F Y') : $fmt($f->from).' – '.$fmt($f->to);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
    }
}
