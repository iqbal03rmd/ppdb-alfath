<?php

use App\Jobs\KirimNotifikasiWhatsApp;
use App\Models\NotifikasiWhatsapp;
use App\Services\FonnteService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('service mengirim payload yang aman ke fonnte', function () {
    config()->set('services.fonnte.token', 'token-rahasia');
    config()->set('services.fonnte.endpoint', 'https://api.fonnte.test/send');

    Http::fake([
        'api.fonnte.test/*' => Http::response([
            'status' => true,
            'id' => ['80367170'],
            'requestid' => 2937124,
        ]),
    ]);

    $hasil = (new FonnteService)->kirim('628123456789', 'Tagihan tersedia.');

    expect($hasil['message_id'])->toBe('80367170')
        ->and($hasil['request_id'])->toBe('2937124');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.fonnte.test/send'
        && $request->hasHeader('Authorization', 'token-rahasia')
        && $request['target'] === '628123456789'
        && $request['message'] === 'Tagihan tersedia.'
        && $request['connectOnly'] === true
        && $request['countryCode'] === '0'
        && $request['preview'] === false
    );
});

test('service menolak dijalankan tanpa token', function () {
    config()->set('services.fonnte.token', null);

    expect(fn () => (new FonnteService)->kirim('628123456789', 'Tes'))
        ->toThrow(RuntimeException::class, 'FONNTE_TOKEN belum dikonfigurasi.');
});

test('job mencatat bahwa pesan diterima service', function () {
    config()->set('services.whatsapp.driver', 'log');

    $notifikasi = NotifikasiWhatsapp::create([
        'jenis' => 'tagihan_dibuka',
        'idempotency_key' => 'tagihan_dibuka:test-job',
        'nomor_tujuan' => '628123456789',
        'pesan' => 'Tagihan tersedia.',
        'status' => 'menunggu',
    ]);

    (new KirimNotifikasiWhatsApp($notifikasi->id))->handle(new FonnteService);

    $notifikasi->refresh();

    expect($notifikasi->status)->toBe('diterima_gateway')
        ->and($notifikasi->provider_message_id)->toStartWith('log-')
        ->and($notifikasi->jumlah_percobaan)->toBe(1)
        ->and($notifikasi->diterima_gateway_pada)->not->toBeNull();
});
