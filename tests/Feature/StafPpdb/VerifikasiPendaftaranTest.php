<?php

use App\Models\PendaftaranPpdb;
use App\Models\User;

/**
 * Test ini berjalan di atas data seed, bukan factory: model PPDB saling
 * bergantung (tahun ajaran -> gelombang -> pendaftaran -> berkas), jadi
 * menyiapkannya satu per satu di tiap test malah panjang dan gampang meleset
 * dari data sungguhan. Seeder-nya sudah dipakai untuk demo, jadi sekalian
 * dijadikan patokan.
 */
beforeEach(function () {
    $this->seed();

    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();

    // Seed menyediakan satu pendaftaran berstatus 'diajukan' - itulah yang
    // muncul di antrian verifikasi.
    $this->pendaftaran = PendaftaranPpdb::where('status', 'diajukan')->firstOrFail();
});

test('staf bisa membuka antrian verifikasi', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.index'))
        ->assertOk();
});

test('staf bisa membuka halaman periksa satu pendaftaran', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.show', $this->pendaftaran))
        ->assertOk();
});

test('wali murid tidak boleh membuka halaman staf', function () {
    $wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();

    $this->actingAs($wali)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.index'))
        ->assertForbidden();
});

test('menyetujui membuat status jadi diverifikasi dan mencatat pemeriksanya', function () {
    // Sisa catatan dari pemeriksaan sebelumnya, harus ikut dibersihkan.
    $this->pendaftaran->update(['catatan_verifikasi' => 'Catatan lama dari pemeriksaan sebelumnya.']);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran))
        ->assertRedirect(route('staf-ppdb.verifikasi-pendaftaran.index'));

    $this->pendaftaran->refresh();

    expect($this->pendaftaran->status)->toBe('diverifikasi')
        ->and($this->pendaftaran->diverifikasi_oleh)->toBe($this->staf->id)
        ->and($this->pendaftaran->catatan_verifikasi)->toBeNull();
});

test('setelah disetujui wali jadi boleh membayar', function () {
    expect($this->pendaftaran->bolehBayar())->toBeFalse();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran));

    expect($this->pendaftaran->refresh()->bolehBayar())->toBeTrue();
});

test('minta perbaikan menyimpan catatan dan mengembalikan bola ke wali', function () {
    $catatan = 'Foto Kartu Keluarga buram, nomor KK tidak terbaca. Mohon unggah ulang.';

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', $this->pendaftaran), [
            'catatan_verifikasi' => $catatan,
        ])
        ->assertRedirect(route('staf-ppdb.verifikasi-pendaftaran.index'));

    $this->pendaftaran->refresh();

    expect($this->pendaftaran->status)->toBe('perlu_perbaikan')
        ->and($this->pendaftaran->catatan_verifikasi)->toBe($catatan)
        // Siapa yang meminta ikut tercatat, sama seperti saat menyetujui.
        ->and($this->pendaftaran->diverifikasi_oleh)->toBe($this->staf->id)
        // Bola balik ke wali: dia harus bisa mengedit lagi.
        ->and($this->pendaftaran->bisaDiedit())->toBeTrue();
});

test('minta perbaikan tanpa catatan ditolak dan status tidak berubah', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', $this->pendaftaran), [
            'catatan_verifikasi' => '',
        ])
        ->assertSessionHasErrors('catatan_verifikasi');

    expect($this->pendaftaran->refresh()->status)->toBe('diajukan');
});

test('catatan yang terlalu pendek ditolak', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', $this->pendaftaran), [
            'catatan_verifikasi' => 'buram',
        ])
        ->assertSessionHasErrors('catatan_verifikasi');

    expect($this->pendaftaran->refresh()->status)->toBe('diajukan');
});

/**
 * Penjaga status. Kalau dua staf membuka berkas yang sama lalu sama-sama menekan
 * tombol, yang datang belakangan harus ditolak - bukan menimpa keputusan pertama.
 */
test('tidak bisa menyetujui pendaftaran yang sedang tidak menunggu verifikasi', function () {
    $this->pendaftaran->update(['status' => 'diverifikasi']);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran))
        ->assertForbidden();
});

test('tidak bisa meminta perbaikan untuk pendaftaran yang sudah diterima', function () {
    $this->pendaftaran->update(['status' => 'diterima']);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', $this->pendaftaran), [
            'catatan_verifikasi' => 'Ada yang perlu dibetulkan di formulirnya.',
        ])
        ->assertForbidden();

    expect($this->pendaftaran->refresh()->status)->toBe('diterima');
});
