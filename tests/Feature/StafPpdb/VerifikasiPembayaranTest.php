<?php

use App\Models\PembayaranPpdb;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed();

    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();

    // Seed menyediakan satu transfer yang menunggu diperiksa: nominalnya PAS
    // minimal bayar, jadi mengesahkannya harus membuat pendaftarannya diterima.
    $this->transfer = PembayaranPpdb::where('status', 'menunggu_verifikasi')->firstOrFail();
    $this->pendaftaran = $this->transfer->pendaftaran;
});

test('staf bisa membuka antrian pembayaran', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pembayaran.index'))
        ->assertOk();
});

test('staf bisa membuka halaman periksa satu transfer', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pembayaran.show', $this->transfer))
        ->assertOk();
});

test('wali murid tidak boleh membuka verifikasi pembayaran', function () {
    $wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();

    $this->actingAs($wali)
        ->get(route('staf-ppdb.verifikasi-pembayaran.index'))
        ->assertForbidden();
});

test('mengesahkan transfer mencatat pemeriksanya', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer))
        ->assertRedirect(route('staf-ppdb.verifikasi-pembayaran.index'));

    $this->transfer->refresh();

    expect($this->transfer->status)->toBe('terverifikasi')
        ->and($this->transfer->diverifikasi_oleh)->toBe($this->staf->id);
});

/**
 * Inti dari seluruh modul ini: status pendaftaran naik SENDIRI, tanpa tombol
 * "Tetapkan Diterima" terpisah.
 */
test('mengesahkan transfer yang mencapai minimal bayar membuat pendaftaran otomatis diterima', function () {
    expect($this->pendaftaran->status)->toBe('diverifikasi');

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer));

    expect($this->pendaftaran->refresh()->status)->toBe('diterima');
});

/**
 * Arah sebaliknya, dan ini yang bikin otomatisasinya utuh: pengesahan yang
 * terlanjur salah harus bisa dicabut, dan statusnya ikut turun lagi.
 */
test('membatalkan pengesahan menurunkan kembali status pendaftaran', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer));

    expect($this->pendaftaran->refresh()->status)->toBe('diterima');

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.tolak', $this->transfer), [
            'catatan_verifikasi' => 'Ternyata bukti transfernya milik pendaftaran lain, pengesahan dibatalkan.',
        ]);

    expect($this->transfer->refresh()->status)->toBe('ditolak')
        ->and($this->pendaftaran->refresh()->status)->toBe('diverifikasi');
});

test('menolak transfer tidak menolak pendaftarannya', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.tolak', $this->transfer), [
            'catatan_verifikasi' => 'Bukti transfer buram, nominalnya tidak terbaca.',
        ]);

    expect($this->transfer->refresh()->status)->toBe('ditolak')
        // Aturan yang gampang keliru: yang ditolak buktinya, bukan pendaftarannya.
        ->and($this->pendaftaran->refresh()->status)->toBe('diverifikasi');
});

test('menolak tanpa alasan ditolak dan status transfer tidak berubah', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.tolak', $this->transfer), [
            'catatan_verifikasi' => '',
        ])
        ->assertSessionHasErrors('catatan_verifikasi');

    expect($this->transfer->refresh()->status)->toBe('menunggu_verifikasi');
});

test('transfer yang sudah ditolak tidak bisa diputuskan lagi', function () {
    $this->transfer->update(['status' => 'ditolak']);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer))
        ->assertForbidden();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.tolak', $this->transfer), [
            'catatan_verifikasi' => 'Mencoba menolak untuk kedua kalinya.',
        ])
        ->assertForbidden();
});

test('transfer yang sudah disahkan tidak bisa disahkan dua kali', function () {
    $this->transfer->update(['status' => 'terverifikasi']);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer))
        ->assertForbidden();
});

/**
 * Peringatan sebelum membatalkan pengesahan harus menyebut keadaan pendaftaran
 * ini sebenarnya, bukan kemungkinan umum. Beda kondisi, beda kalimat.
 */
test('peringatan pembatalan menyebut kalau status benar-benar akan turun', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer));

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pembayaran.show', $this->transfer))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ringkasan.sudah_disahkan', true)
            ->where('ringkasan.akan_menurunkan_status', true)
            ->where('ringkasan.transfer_menunggu_lain', 0)
        );
});

test('peringatan pembatalan menyebut cicilan lain yang terlanjur dikirim wali', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer));

    // Wali melihat dirinya diterima, lalu mencicil sisanya.
    PembayaranPpdb::create([
        'pendaftaran_ppdb_id' => $this->pendaftaran->id,
        'nominal_transfer' => 1_500_000,
        'tanggal_transfer' => now(),
        'bukti_transfer' => 'seed-placeholder.png',
        'status' => 'menunggu_verifikasi',
    ]);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pembayaran.show', $this->transfer))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ringkasan.akan_menurunkan_status', true)
            ->where('ringkasan.transfer_menunggu_lain', 1)
            ->where('ringkasan.nominal_menunggu_lain', 1500000)
        );
});

test('peringatan menenangkan kalau pembatalan tidak menurunkan status', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pembayaran.sahkan', $this->transfer));

    // Transfer kedua yang juga sudah sah - minimal bayar tetap tertutupi walau
    // pengesahan yang pertama dicabut.
    PembayaranPpdb::create([
        'pendaftaran_ppdb_id' => $this->pendaftaran->id,
        'nominal_transfer' => 3_000_000,
        'tanggal_transfer' => now(),
        'bukti_transfer' => 'seed-placeholder.png',
        'status' => 'terverifikasi',
    ]);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pembayaran.show', $this->transfer))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ringkasan.akan_menurunkan_status', false)
        );
});
