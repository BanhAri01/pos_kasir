<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>QR Meja - {{ $tenant->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font: 14px/1.4 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111827; }
        .sheet { max-width: 210mm; margin: 16px auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8mm; padding: 10mm; background: #fff; }
        .card { border: 2px dashed #9ca3af; border-radius: 6mm; padding: 6mm; text-align: center; break-inside: avoid; page-break-inside: avoid; }
        .brand { font-size: 18px; font-weight: 800; }
        .table { display: inline-block; margin: 3mm 0; padding: 2mm 6mm; border-radius: 4mm; background: #111827; color: #f5b544; font-size: 26px; font-weight: 800; }
        .qr svg { width: 55mm; height: 55mm; }
        .steps { font-size: 14px; font-weight: 700; }
        .muted { color: #4b5563; font-size: 12px; }
        .actions { max-width: 210mm; margin: 0 auto 24px; padding: 0 16px; display: flex; gap: 10px; }
        .actions button { flex: 1; min-height: 52px; border-radius: 14px; border: 0; font: 700 17px system-ui, sans-serif; background: #1f2937; color: #fff; cursor: pointer; }
        .empty { grid-column: 1 / -1; text-align: center; font-size: 18px; padding: 20mm 0; }
        @media (max-width: 640px) { .sheet { grid-template-columns: 1fr; margin: 0; } }
        @media print {
            @page { size: A4; margin: 8mm; }
            body { background: #fff; }
            .sheet { margin: 0; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        @forelse($cards as $card)
            <div class="card">
                <div class="brand">{{ $tenant->name }}</div>
                <div class="table">{{ $card['name'] }}</div>
                <div class="qr">{!! $card['svg'] !!}</div>
                <div class="steps">Scan QR · Pilih menu · Pesan</div>
                <div class="muted">Bayar di kasir atau langsung QRIS dari HP</div>
            </div>
        @empty
            <p class="empty">Belum ada meja aktif.</p>
        @endforelse
    </div>
    <div class="actions">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
</body>
</html>
