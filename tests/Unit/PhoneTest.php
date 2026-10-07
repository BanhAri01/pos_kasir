<?php

use App\Core\Support\Phone;

it('menyeragamkan no HP ke format 628', function (?string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['081234567890', '6281234567890'],
    ['0812-3456-7890', '6281234567890'],
    ['+62 812 3456 7890', '6281234567890'],
    ['6281234567890', '6281234567890'],
    ['81234567890', '6281234567890'],
    ['021345678', null],      // telepon rumah, bukan HP
    ['123', null],
    ['', null],
    [null, null],
]);

it('menampilkan no HP dengan format yang mudah dibaca', function () {
    expect(Phone::display('6281234567890'))->toBe('0812-3456-7890');
});

it('menulis terbilang rupiah untuk faktur', function (int $amount, string $expected) {
    expect(App\Core\Support\Rupiah::spell($amount))->toBe($expected);
})->with([
    [0, 'nol'],
    [11, 'sebelas'],
    [15, 'lima belas'],
    [100, 'seratus'],
    [1000, 'seribu'],
    [680000, 'enam ratus delapan puluh ribu'],
    [1250500, 'satu juta dua ratus lima puluh ribu lima ratus'],
    [2000000000, 'dua miliar'],
]);
