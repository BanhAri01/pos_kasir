@extends('documents.layout')
@php
    use App\Core\Support\Qty;
    use App\Core\Support\Rupiah;
    $max = max(1, collect($series)->max('total') ?? 1);
@endphp

@section('title', "Laporan {$tenant->name} - {$period}")

@section('content')
    <div class="head">
        <div>
            <div class="brand">{{ $tenant->name }}</div>
            <div class="muted">{{ $outletLabel }}</div>
        </div>
        <div>
            <div class="doc-title">LAPORAN</div>
            <div class="muted" style="text-align:right">{{ $period }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="label">Uang masuk</div>
            <div style="font-size:24px;font-weight:800">{{ Rupiah::format($summary['total_collected']) }}</div>
            <div class="muted">{{ $summary['transactions'] }} transaksi · rata-rata {{ Rupiah::format($summary['average']) }}</div>
        </div>
        <div class="box">
            <div class="label">Untung bersih</div>
            <div style="font-size:24px;font-weight:800;color:{{ $summary['net_profit'] < 0 ? '#b91c1c' : '#1f2937' }}">{{ Rupiah::format($summary['net_profit']) }}</div>
            <div class="muted">Untung kotor {{ Rupiah::format($summary['gross_profit']) }} − pengeluaran {{ Rupiah::format($summary['expenses']) }}</div>
        </div>
    </div>

    <table>
        <thead><tr><th>Ringkasan</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
            <tr><td>Penjualan kotor</td><td class="num">{{ Rupiah::format($summary['gross_sales']) }}</td></tr>
            <tr><td>Diskon</td><td class="num">− {{ Rupiah::format($summary['discounts']) }}</td></tr>
            <tr><td>Pengembalian barang ({{ $summary['refund_count'] }})</td><td class="num">− {{ Rupiah::format($summary['refunds']) }}</td></tr>
            <tr><td><strong>Penjualan bersih</strong></td><td class="num"><strong>{{ Rupiah::format($summary['net_sales']) }}</strong></td></tr>
            <tr><td>Modal barang terjual</td><td class="num">− {{ Rupiah::format($summary['cogs']) }}</td></tr>
            <tr><td><strong>Untung kotor</strong> ({{ $summary['margin'] }}%)</td><td class="num"><strong>{{ Rupiah::format($summary['gross_profit']) }}</strong></td></tr>
            <tr><td>Pengeluaran</td><td class="num">− {{ Rupiah::format($summary['expenses']) }}</td></tr>
            <tr><td><strong>Untung bersih</strong></td><td class="num"><strong>{{ Rupiah::format($summary['net_profit']) }}</strong></td></tr>
            <tr><td class="muted">Pajak dipungut</td><td class="num muted">{{ Rupiah::format($summary['tax']) }}</td></tr>
            <tr><td class="muted">Biaya layanan</td><td class="num muted">{{ Rupiah::format($summary['service']) }}</td></tr>
            @if($summary['unpaid'])<tr><td class="muted">Belum dibayar (kasbon / tempo)</td><td class="num muted">{{ Rupiah::format($summary['unpaid']) }}</td></tr>@endif
        </tbody>
    </table>

    @if(count($series) > 1)
        <table>
            <thead><tr><th style="width:90px">Tanggal</th><th>Penjualan</th><th class="num" style="width:130px">Jumlah</th></tr></thead>
            <tbody>
                @foreach($series as $p)
                    <tr>
                        <td>{{ $p['label'] }}</td>
                        <td><div style="height:10px;border-radius:4px;background:#4b5563;width:{{ round($p['total'] / $max * 100) }}%"></div></td>
                        <td class="num">{{ Rupiah::format($p['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table>
        <thead><tr><th>Barang terlaris</th><th class="num">Jumlah</th><th class="num">Penjualan</th><th class="num">Untung</th></tr></thead>
        <tbody>
            @forelse($products as $p)
                <tr><td>{{ $p['name'] }}</td><td class="num">{{ Qty::display($p['qty']) }} {{ $p['unit'] }}</td><td class="num">{{ Rupiah::format($p['revenue']) }}</td><td class="num">{{ Rupiah::format($p['profit']) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">Belum ada penjualan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="grid">
        <table>
            <thead><tr><th>Cara bayar</th><th class="num">Bersih</th></tr></thead>
            <tbody>
                @forelse($payments as $p)<tr><td>{{ $p['name'] }} ({{ $p['transactions'] }})</td><td class="num">{{ Rupiah::format($p['net']) }}</td></tr>@empty<tr><td colspan="2" class="muted">-</td></tr>@endforelse
            </tbody>
        </table>
        <table>
            <thead><tr><th>Pengeluaran</th><th class="num">Jumlah</th></tr></thead>
            <tbody>
                @forelse($expenses as $e)<tr><td>{{ $e['name'] }}</td><td class="num">{{ Rupiah::format($e['total']) }}</td></tr>@empty<tr><td colspan="2" class="muted">Tidak ada pengeluaran.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <table>
        <thead><tr><th>Kasir</th><th class="num">Transaksi</th><th class="num">Penjualan</th></tr></thead>
        <tbody>
            @forelse($cashiers as $c)<tr><td>{{ $c['name'] }}</td><td class="num">{{ $c['transactions'] }}</td><td class="num">{{ Rupiah::format($c['total']) }}</td></tr>@empty<tr><td colspan="3" class="muted">-</td></tr>@endforelse
        </tbody>
    </table>

    <p class="muted" style="font-size:12px">Dicetak {{ $printedAt }} dari Hermes POS.</p>
@endsection
