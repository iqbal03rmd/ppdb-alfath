<?php

use App\Rules\NomorWhatsApp;

test('menormalisasi berbagai penulisan nomor indonesia', function (string $asal, string $hasil) {
    expect(NomorWhatsApp::normalisasi($asal))->toBe($hasil);
})->with([
    ['0812-3456-7890', '6281234567890'],
    ['+62 812 3456 7890', '6281234567890'],
    ['81234567890', '6281234567890'],
]);

test('menolak nomor kosong atau bukan seluler indonesia', function (?string $nomor) {
    expect(NomorWhatsApp::normalisasi($nomor))->toBeNull();
})->with([
    null,
    '',
    '12345',
    '+60 12 345 6789',
    'WA 0812-3456-7890',
    '0812abc345678',
]);
