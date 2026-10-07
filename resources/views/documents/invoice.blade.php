@extends('documents.layout')
@php
    use App\Core\Support\Qty;
    use App\Core\Support\Rupiah;
@endphp

@section('title', "Faktur {$sale->number} - {$tenant->name}")

@section('content')
    <div class="head">
        <div>
            <div class="brand">{{ $tenant->name }}</div>
            <div class="muted">{{ $outlet->name }}@if($outlet->address) · {{ $outlet->address }}@endif</div>
            @if($outletPhone)<div class="muted">Telp/WA: {{ $outletPhone }}</div>@endif
        </div>
        <div>
            <div class="doc-title">FAKTUR</div>
            <div class="muted" style="text-align:right">No. {{ $sale->number }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="label">Kepada</div>
            <strong>{{ $sale->customer?->name ?? 'Pelanggan umum' }}</strong>
            @if($customerPhone)<div>{{ $customerPhone }}</div>@endif
            @if($sale->customer?->address)<div class="muted">{{ $sale->customer->address }}</div>@endif
        </div>
        <div class="box">
            <div class="label">Rincian</div>
            <div>Tanggal: {{ $at->locale('id')->translatedFormat('j F Y, H:i') }}</div>
            @if($sale->due_date)<div>Jatuh tempo: <strong>{{ $sale->due_date->locale('id')->translatedFormat('j F Y') }}</strong></div>@endif
            <div>Kasir: {{ $sale->cashier?->name }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr><th style="width:40px">No</th><th>Barang / Layanan</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Diskon</th><th class="num">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($sale->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->name }}
                        @if($item->modifiers->isNotEmpty())<div class="muted" style="font-size:12px">{{ $item->modifiers->pluck('name')->join(', ') }}</div>@endif
                        @if($item->note)<div class="muted" style="font-size:12px">{{ $item->note }}</div>@endif
                    </td>
                    <td class="num">{{ Qty::display($item->qty) }} {{ $item->unit_name }}</td>
                    <td class="num">{{ Rupiah::format($item->unit_price) }}</td>
                    <td class="num">{{ $item->discount_amount ? Rupiah::format($item->discount_amount) : '-' }}</td>
                    <td class="num">{{ Rupiah::format($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ Rupiah::format($sale->subtotal) }}</td></tr>
        @if($sale->discount_amount)<tr><td>Diskon</td><td class="num">- {{ Rupiah::format($sale->discount_amount) }}</td></tr>@endif
        @if($sale->service_charge_amount)<tr><td>Biaya layanan</td><td class="num">{{ Rupiah::format($sale->service_charge_amount) }}</td></tr>@endif
        @if($sale->tax_amount)<tr><td>Pajak</td><td class="num">{{ Rupiah::format($sale->tax_amount) }}</td></tr>@endif
        @if($sale->rounding_amount)<tr><td>Pembulatan</td><td class="num">{{ Rupiah::format($sale->rounding_amount) }}</td></tr>@endif
        <tr class="grand"><td>TOTAL</td><td class="num">{{ Rupiah::format($sale->total) }}</td></tr>
        <tr><td>Sudah dibayar</td><td class="num">{{ Rupiah::format(min($sale->paid_amount, $sale->total)) }}</td></tr>
        @if($sale->due_amount > 0)<tr><td><strong>Sisa tagihan</strong></td><td class="num"><strong>{{ Rupiah::format($sale->due_amount) }}</strong></td></tr>@endif
    </table>

    <p class="muted">Terbilang: <em>{{ ucfirst(Rupiah::spell($sale->total)) }} rupiah</em></p>
    @if($sale->note)<p>Catatan: {{ $sale->note }}</p>@endif

    <div class="sign">
        <div></div><div></div><div></div>
    </div>
    <div class="sign" style="margin-top:6px">
        <span>Penerima</span><span>Pengirim</span><span>Hormat kami</span>
    </div>
@endsection
