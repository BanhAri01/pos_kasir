@php
    use App\Core\Support\Qty;
    use App\Core\Support\Rupiah;
    $width = $paper === '80' ? '80mm' : '58mm';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="format-detection" content="telephone=no">
    <title>Struk {{ $sale->number }} - {{ $tenant->name }}</title>
    <style>
        /* Struk sengaja tanpa framework: ringan, cepat dibuka dari WhatsApp. */
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; font-family: ui-monospace, 'Courier New', monospace; color: #111827; font-size: 15px; line-height: 1.45; }
        .paper { background: #fff; max-width: 420px; margin: 16px auto; padding: 20px 18px; border-radius: 12px; box-shadow: 0 2px 12px rgb(0 0 0 / .08); }
        .center { text-align: center; }
        .big { font-size: 20px; font-weight: 800; }
        .muted { color: #374151; }
        hr { border: 0; border-top: 1px dashed #6b7280; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .row span:last-child { text-align: right; white-space: nowrap; }
        .item { margin-bottom: 6px; }
        .total { font-size: 19px; font-weight: 800; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-weight: 800; }
        .void { background: #fee2e2; color: #991b1b; }
        .actions { max-width: 420px; margin: 0 auto 24px; padding: 0 16px; display: flex; gap: 10px; }
        .actions button { flex: 1; min-height: 52px; border-radius: 14px; border: 0; font: 700 17px system-ui, sans-serif; background: #1f2937; color: #fff; cursor: pointer; }
        @media print {
            @page { size: {{ $width }} auto; margin: 2mm; }
            body { background: #fff; font-size: {{ $paper === '80' ? '12px' : '10.5px' }}; }
            .paper { box-shadow: none; margin: 0; padding: 0; max-width: none; width: 100%; border-radius: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
<main class="paper">
    <div class="center">
        <div class="big">{{ $tenant->name }}</div>
        @if ($outlet->name !== $tenant->name)<div>{{ $outlet->name }}</div>@endif
        @if ($outlet->address)<div class="muted">{{ $outlet->address }}</div>@endif
        @if ($outletPhone)<div class="muted">{{ $outletPhone }}</div>@endif
        @if ($outlet->receipt_header)<div>{{ $outlet->receipt_header }}</div>@endif
    </div>
    <hr>
    <div class="row"><span>No. Nota</span><span>{{ $sale->number }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ $at->locale('id')->translatedFormat('j M Y, H:i') }} {{ $timezoneLabel }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $sale->cashier?->name }}</span></div>
    @if ($sale->customer)<div class="row"><span>Pelanggan</span><span>{{ $sale->customer->name }}</span></div>@endif
    @if ($sale->isVoid())<div class="center" style="margin-top:8px"><span class="badge void">DIBATALKAN</span></div>@endif
    <hr>
    @foreach ($sale->items as $item)
        <div class="item">
            <div>{{ $item->name }}</div>
            <div class="row muted">
                <span>{{ Qty::display($item->qty) }}{{ $item->unit_name ? ' '.$item->unit_name : '' }} x {{ Rupiah::number($item->unit_price) }}</span>
                <span>{{ Rupiah::number($item->subtotal + $item->discount_amount) }}</span>
            </div>
            @if ($item->discount_amount > 0)<div class="row muted"><span>  Diskon</span><span>-{{ Rupiah::number($item->discount_amount) }}</span></div>@endif
            @if ($item->note)<div class="muted">  * {{ $item->note }}</div>@endif
        </div>
    @endforeach
    <hr>
    <div class="row"><span>Subtotal</span><span>{{ Rupiah::number($sale->subtotal) }}</span></div>
    @if ($sale->discount_amount > 0)<div class="row"><span>Diskon</span><span>-{{ Rupiah::number($sale->discount_amount) }}</span></div>@endif
    @if ($sale->service_charge_amount > 0)<div class="row"><span>Biaya layanan</span><span>{{ Rupiah::number($sale->service_charge_amount) }}</span></div>@endif
    @if ($sale->tax_amount > 0)<div class="row"><span>Pajak{{ $outlet->tax_inclusive ? ' (sudah termasuk)' : '' }}</span><span>{{ Rupiah::number($sale->tax_amount) }}</span></div>@endif
    <div class="row total"><span>TOTAL</span><span>{{ Rupiah::format($sale->total) }}</span></div>
    <hr>
    @foreach ($sale->payments as $payment)
        <div class="row"><span>{{ $payment->method_name }}</span><span>{{ Rupiah::number($payment->amount + ($payment->method_type === 'cash' ? $sale->change_amount : 0)) }}</span></div>
    @endforeach
    @if ($sale->change_amount > 0)<div class="row"><span>Kembalian</span><span>{{ Rupiah::number($sale->change_amount) }}</span></div>@endif
    @if ($sale->due_amount > 0)<div class="row"><span>Sisa belum dibayar</span><span>{{ Rupiah::number($sale->due_amount) }}</span></div>@endif
    @if ($sale->refunded_amount > 0)<div class="row"><span>Dikembalikan</span><span>-{{ Rupiah::number($sale->refunded_amount) }}</span></div>@endif
    <hr>
    <div class="center">
        {{ $outlet->receipt_footer ?: 'Terima kasih sudah berbelanja!' }}
        <div class="muted" style="margin-top:6px;font-size:12px">Struk digital oleh Hermes POS</div>
    </div>
</main>
<div class="actions">
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
</div>
@if ($autoPrint)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
