@extends('documents.layout')
@php
    use App\Core\Support\Phone;
    use App\Core\Support\Qty;
@endphp

@section('title', "Surat Jalan {$delivery->number} - {$tenant->name}")

@section('content')
    <div class="head">
        <div>
            <div class="brand">{{ $tenant->name }}</div>
            <div class="muted">{{ $outlet->name }}@if($outlet->address) · {{ $outlet->address }}@endif</div>
            @if($outlet->phone)<div class="muted">Telp/WA: {{ Phone::display($outlet->phone) }}</div>@endif
        </div>
        <div>
            <div class="doc-title">SURAT JALAN</div>
            <div class="muted" style="text-align:right">No. {{ $delivery->number }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="label">Dikirim kepada</div>
            <strong>{{ $delivery->recipient_name }}</strong>
            @if($delivery->phone)<div>{{ Phone::display($delivery->phone) }}</div>@endif
            <div>{{ $delivery->address }}</div>
        </div>
        <div class="box">
            <div class="label">Rincian</div>
            <div>Nota: {{ $delivery->sale?->number }}</div>
            <div>Tanggal kirim: {{ ($delivery->scheduled_at ?? $delivery->created_at)->timezone($timezone)->locale('id')->translatedFormat('j F Y, H:i') }}</div>
            @if($delivery->driver_name)<div>Sopir: {{ $delivery->driver_name }}@if($delivery->vehicle) ({{ $delivery->vehicle }})@endif</div>@endif
        </div>
    </div>

    <table>
        <thead><tr><th style="width:40px">No</th><th>Nama Barang</th><th class="num">Jumlah</th><th>Keterangan</th></tr></thead>
        <tbody>
            @foreach($delivery->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ Qty::display($item->qty) }} {{ $item->unit_name }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($delivery->note)<p>Catatan: {{ $delivery->note }}</p>@endif
    <p class="muted">Mohon periksa barang saat diterima. Barang yang sudah diterima dalam keadaan baik menjadi tanggung jawab penerima.</p>

    <div class="sign"><div></div><div></div><div></div></div>
    <div class="sign" style="margin-top:6px"><span>Penerima</span><span>Sopir</span><span>Bagian Gudang</span></div>
@endsection
