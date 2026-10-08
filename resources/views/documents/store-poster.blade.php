<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Poster Toko Online - {{ $tenant->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font: 16px/1.4 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111827; }
        .poster { max-width: 148mm; margin: 16px auto; background: #fff; padding: 12mm; text-align: center; border-top: 10mm solid #111827; }
        h1 { margin: 0 0 2mm; font-size: 30px; }
        .sub { color: #374151; font-size: 18px; margin-bottom: 6mm; }
        .badge { display: inline-block; background: #111827; color: #f5b544; font-weight: 800; font-size: 22px; padding: 2mm 6mm; border-radius: 4mm; margin-bottom: 5mm; }
        .qr svg { width: 80mm; height: 80mm; }
        .steps { font-size: 18px; font-weight: 700; margin-top: 4mm; }
        .url { font-size: 12px; color: #4b5563; word-break: break-all; margin-top: 3mm; }
        .actions { max-width: 148mm; margin: 0 auto 24px; padding: 0 16px; }
        .actions button { width: 100%; min-height: 52px; border-radius: 14px; border: 0; font: 700 17px system-ui, sans-serif; background: #1f2937; color: #fff; cursor: pointer; }
        @media print {
            @page { size: A5; margin: 6mm; }
            body { background: #fff; }
            .poster { margin: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="poster">
        <h1>{{ $tenant->name }}</h1>
        <div class="sub">{{ $outlet->name }}</div>
        <div class="badge">PESAN DARI HP</div>
        <div class="qr">{!! $svg !!}</div>
        <div class="steps">Scan · Pilih menu · {{ $outlet->online_delivery ? 'Diantar atau ambil sendiri' : 'Ambil sendiri' }}</div>
        <div class="url">{{ $url }}</div>
    </div>
    <div class="actions"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
</body>
</html>
