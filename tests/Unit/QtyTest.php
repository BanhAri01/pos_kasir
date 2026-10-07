<?php

use App\Core\Support\Qty;

it('menjumlahkan desimal tanpa galat', function () {
    expect(Qty::add('0.1', '0.2'))->toBe('0.300');
});

it('menerima koma sebagai pemisah desimal', function () {
    expect(Qty::normalize('2,5'))->toBe('2.500');
});

it('menampilkan jumlah tanpa nol berlebih', function (string $input, string $expected) {
    expect(Qty::display($input))->toBe($expected);
})->with([
    ['10.000', '10'],
    ['2.500', '2,5'],
    ['0.250', '0,25'],
    ['-3.000', '-3'],
    ['0.000', '0'],
]);

it('menghitung harga x jumlah ke rupiah terdekat', function () {
    expect(Qty::money(7000, '2.5'))->toBe(17500)
        ->and(Qty::money(3333, '1.5'))->toBe(5000); // 4999,5 dibulatkan
});
