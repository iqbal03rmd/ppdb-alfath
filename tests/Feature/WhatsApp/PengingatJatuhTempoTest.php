<?php

use App\Jobs\KirimNotifikasiWhatsApp;
use App\Models\NotifikasiWhatsapp;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\PengaturanSistem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed();

    config()->set('services.whatsapp.enabled', true);
    config()->set('services.whatsapp.driver', 'log');
    Queue::fake();

    $this->pendaftaran = PendaftaranPpdb::where('nomor_pendaftaran', 'PPDB-2026-00004')->firstOrFail();

    // Gelombang seed dipakai banyak pendaftaran. Isolasi satu target supaya
    // assertion test ini tidak bergantung pada jumlah data demo lainnya.
    PendaftaranPpdb::where('id', '!=', $this->pendaftaran->id)
        ->where('status', 'pembayaran')
        ->update(['status' => 'ditolak']);
});

test('pengingat dikirim tepat tujuh hari sebelum tenggat jika minimal belum tercapai', function () {
    $this->pendaftaran->gelombang->update([
        'batas_waktu_pembayaran' => today('Asia/Jakarta')->addDays(7),
    ]);

    Artisan::call('ppdb:kirim-pengingat-jatuh-tempo');

    $notifikasi = NotifikasiWhatsapp::where('jenis', 'pengingat_jatuh_tempo_minimal')->sole();

    expect($notifikasi->pendaftaran_ppdb_id)->toBe($this->pendaftaran->id)
        ->and($notifikasi->pesan)->toContain('Tersisa 7 hari')
        ->and($notifikasi->pesan)->toContain('Kekurangan agar pendaftaran diterima');

    Queue::assertPushed(
        KirimNotifikasiWhatsApp::class,
        fn (KirimNotifikasiWhatsApp $job) => $job->notifikasiId === $notifikasi->id,
    );

    // Scheduler boleh terpanggil ulang; idempotency key harus mencegah pesan ganda.
    Artisan::call('ppdb:kirim-pengingat-jatuh-tempo');
    expect(NotifikasiWhatsapp::where('jenis', 'pengingat_jatuh_tempo_minimal')->count())->toBe(1);
});

test('pengingat tidak dikirim sebelum memasuki tujuh hari menuju tenggat', function () {
    $this->pendaftaran->gelombang->update([
        'batas_waktu_pembayaran' => today('Asia/Jakarta')->addDays(8),
    ]);

    Artisan::call('ppdb:kirim-pengingat-jatuh-tempo');

    expect(NotifikasiWhatsapp::where('jenis', 'pengingat_jatuh_tempo_minimal')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('pengingat tidak dikirim jika pembayaran terverifikasi sudah mencapai minimal', function () {
    $this->pendaftaran->gelombang->update([
        'batas_waktu_pembayaran' => today('Asia/Jakarta')->addDays(7),
    ]);

    PembayaranPpdb::create([
        'pendaftaran_ppdb_id' => $this->pendaftaran->id,
        'nominal_transfer' => $this->pendaftaran->minimalBayar(),
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'seed-placeholder.png',
        'status' => 'terverifikasi',
    ]);

    Artisan::call('ppdb:kirim-pengingat-jatuh-tempo');

    expect(NotifikasiWhatsapp::where('jenis', 'pengingat_jatuh_tempo_minimal')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('scheduler memakai jumlah hari yang diatur super admin', function () {
    PengaturanSistem::tersimpan()->update(['hari_pengingat_jatuh_tempo' => 3]);
    $this->pendaftaran->gelombang->update([
        'batas_waktu_pembayaran' => today('Asia/Jakarta')->addDays(3),
    ]);

    Artisan::call('ppdb:kirim-pengingat-jatuh-tempo');

    $notifikasi = NotifikasiWhatsapp::where('jenis', 'pengingat_jatuh_tempo_minimal')->sole();

    expect($notifikasi->pesan)->toContain('Tersisa 3 hari');
});
