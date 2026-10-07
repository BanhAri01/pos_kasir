<?php

namespace App\Core\Support\Xlsx;

/**
 * Membuat file Excel (.xlsx) sederhana: beberapa sheet, baris judul tebal,
 * angka rupiah berformat ribuan. Dibuka normal di Excel, Google Sheets, dan WPS.
 *
 *   $xlsx = new XlsxWriter;
 *   $xlsx->sheet('Ringkasan', [['Keterangan', 'Jumlah'], ['Uang masuk', 125000]], money: [1]);
 *   return response($xlsx->output(), 200, XlsxWriter::headers('laporan.xlsx'));
 */
final class XlsxWriter
{
    /** @var list<array{name:string, rows:array, money:array, widths:array}> */
    private array $sheets = [];

    /**
     * @param  list<list<string|int|float|null>>  $rows  baris pertama dianggap judul kolom (tebal)
     * @param  list<int>  $money  indeks kolom (mulai 0) yang berformat uang
     */
    public function sheet(string $name, array $rows, array $money = []): self
    {
        $name = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name), 0, 31);
        $widths = [];
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $value) {
                $widths[$i] = max($widths[$i] ?? 8, min(60, mb_strlen((string) $value) + 2));
            }
        }
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'money' => $money, 'widths' => $widths];

        return $this;
    }

    public function output(): string
    {
        $zip = new ZipWriter;
        $count = count($this->sheets);

        $zip->add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, $count)))
            .'</Types>');

        $zip->add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->add('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .implode('', array_map(fn ($i) => '<sheet name="'.$this->escape($this->sheets[$i - 1]['name']).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>', range(1, $count)))
            .'</sheets></workbook>');

        $zip->add('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', array_map(fn ($i) => '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>', range(1, $count)))
            .'<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Gaya: 0 biasa, 1 judul tebal, 2 angka ribuan "#,##0", 3 angka desimal.
        $zip->add('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs></styleSheet>');

        foreach ($this->sheets as $i => $sheet) {
            $zip->add('xl/worksheets/sheet'.($i + 1).'.xml', $this->sheetXml($sheet));
        }

        return $zip->output();
    }

    public static function headers(string $filename): array
    {
        return [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $filename).'"',
            'Cache-Control' => 'no-store',
        ];
    }

    private function sheetXml(array $sheet): string
    {
        $cols = '<cols>'.implode('', array_map(fn ($i, $w) => '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>', array_keys($sheet['widths']), $sheet['widths'])).'</cols>';
        $xml = '';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $value) {
                $ref = $this->column($c).($r + 1);
                if ($value === null || $value === '') {
                    continue;
                }
                if ($r > 0 && (is_int($value) || is_float($value))) {
                    $style = in_array($c, $sheet['money'], true) ? 2 : (is_float($value) && floor($value) != $value ? 3 : 0);
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"'.($r === 0 ? ' s="1"' : '').'><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$cols.'<sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    private function escape(string $value): string
    {
        // Buang karakter kontrol yang tidak sah di XML.
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
