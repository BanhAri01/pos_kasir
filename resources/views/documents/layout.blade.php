<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    <style>
        /* Dokumen A4 sederhana tanpa framework, enak dicetak dari browser mana pun. */
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font: 14px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111827; }
        .sheet { background: #fff; width: 100%; max-width: 210mm; min-height: 297mm; margin: 16px auto; padding: 16mm 14mm; box-shadow: 0 2px 16px rgb(0 0 0 / .1); }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid #1f2937; padding-bottom: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .brand { font-size: 22px; font-weight: 800; }
        .doc-title { font-size: 24px; font-weight: 800; letter-spacing: .04em; color: #1f2937; text-align: right; }
        .muted { color: #4b5563; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .box { border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; }
        .label { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f3f4f6; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        th, td { border: 1px solid #d1d5db; padding: 7px 9px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 320px; max-width: 100%; margin-left: auto; }
        .totals td { border: 0; padding: 4px 0; }
        .grand td { font-size: 17px; font-weight: 800; border-top: 2px solid #111827; padding-top: 8px; }
        .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 48px; text-align: center; }
        .sign div { padding-top: 64px; border-bottom: 1px solid #111827; }
        .actions { max-width: 210mm; margin: 0 auto 24px; padding: 0 16px; display: flex; gap: 10px; }
        .actions button { flex: 1; min-height: 52px; border-radius: 14px; border: 0; font: 700 17px system-ui, sans-serif; background: #1f2937; color: #fff; cursor: pointer; }
        .actions button.secondary { background: #fff; color: #111827; border: 2px solid #d1d5db; }
        @media (max-width: 640px) { .sheet { padding: 20px 16px; min-height: 0; margin: 0; } .grid { grid-template-columns: 1fr; } .doc-title { text-align: left; } }
        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; padding: 0; min-height: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">@yield('content')</div>
    <div class="actions">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <button type="button" class="secondary" onclick="history.length > 1 ? history.back() : window.close()">Kembali</button>
    </div>
</body>
</html>
