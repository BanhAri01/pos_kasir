<?php

namespace App\Core\Support\Xlsx;

/**
 * Penulis file ZIP minimal (tanpa ekstensi php-zip, cukup zlib).
 * Hanya untuk membuat file baru di memori; cukup untuk .xlsx.
 */
final class ZipWriter
{
    /** @var list<array{name:string, crc:int, csize:int, usize:int, offset:int, method:int}> */
    private array $entries = [];

    private string $data = '';

    public function add(string $name, string $content): void
    {
        $compressed = gzdeflate($content, 6);
        $method = 8; // deflate
        if ($compressed === false || strlen($compressed) >= strlen($content)) {
            $compressed = $content;
            $method = 0; // simpan apa adanya
        }

        $crc = crc32($content);
        $offset = strlen($this->data);
        [$time, $date] = $this->dosTime();

        $this->data .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0x0800, $method, $time, $date, $crc, strlen($compressed), strlen($content), strlen($name), 0)
            .$name.$compressed;

        $this->entries[] = ['name' => $name, 'crc' => $crc, 'csize' => strlen($compressed), 'usize' => strlen($content), 'offset' => $offset, 'method' => $method, 'time' => $time, 'date' => $date];
    }

    public function output(): string
    {
        $central = '';
        foreach ($this->entries as $e) {
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0x0800, $e['method'], $e['time'], $e['date'], $e['crc'], $e['csize'], $e['usize'], strlen($e['name']), 0, 0, 0, 0, 0, $e['offset'])
                .$e['name'];
        }

        return $this->data.$central
            .pack('VvvvvVVv', 0x06054B50, 0, 0, count($this->entries), count($this->entries), strlen($central), strlen($this->data), 0);
    }

    /** @return array{0:int, 1:int} waktu & tanggal format DOS */
    private function dosTime(): array
    {
        $t = getdate();

        return [
            ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
            (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'],
        ];
    }
}
