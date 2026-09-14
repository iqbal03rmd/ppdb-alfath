<?php

use App\Jobs\KirimNotifikasiWhatsApp;
use App\Models\NotifikasiWhatsapp;
use App\Models\PendaftaranPpdb;
use App\Models\TarifKategori;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Test ini berjalan di atas data seed, bukan factory: model PPDB saling
 * bergantung (tahun ajaran -> gelombang -> pendaftaran -> berkas), jadi
 * menyiapkannya satu per satu di tiap test malah panjang dan gampang meleset
 * dari data sungguhan. Seeder-nya sudah dipakai untuk demo, jadi sekalian
 * dijadikan patokan.
 */
beforeEach(function () {
    $this->seed();

    // Test tidak boleh mengikuti WHATSAPP_ENABLED milik mesin developer dan
    // mengirim pesan sungguhan. Skenario notifikasi mengaktifkannya sendiri
    // bersama Queue::fake() saat memang sedang menguji integrasi tersebut.
    config()->set('services.whatsapp.enabled', false);

    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();

    // Pilih skenario alur utama secara eksplisit. Seeder laporan juga punya
    // beberapa data diajukan, jadi bergantung pada first() bisa menutupi data
    // seed yang tidak konsisten.
    $this->pendaftaran = PendaftaranPpdb::where('nomor_pendaftaran', 'PPDB-2026-00002')->firstOrFail();
});

test('staf bisa membuka antrian verifikasi', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.index'))
        ->assertOk();
});

test('seluruh data seed yang diajukan sudah memiliki berkas wajib lengkap', function () {
    $diajukan = PendaftaranPpdb::with(['gelombang.dokumenWajib', 'dokumen'])
        ->where('status', 'diajukan')
        ->get();

    expect($diajukan)->not->toBeEmpty()
        ->and($diajukan->every->berkasLengkap())->toBeTrue();
});

test('pendaftaran diajukan yang berkasnya tidak lengkap tidak muncul di antrian', function () {
    $this->pendaftaran->dokumen()->firstOrFail()->delete();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staf-ppdb/verifikasi-pendaftaran')
            ->where('antrian', fn ($antrian) => collect($antrian)
                ->doesntContain('id', $this->pendaftaran->id))
        );
});

test('staf bisa membuka halaman periksa satu pendaftaran', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.show', $this->pendaftaran))
        ->assertOk();
});

test('staf tidak bisa membuka halaman periksa sebelum pendaftaran siap diverifikasi', function () {
    $this->pendaftaran->dokumen()->firstOrFail()->delete();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-pendaftaran.show', $this->pendaftaran))
        ->assertForbidden();
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

test('menyetujui menerbitkan tagihan dan mengantrikan satu notifikasi whatsapp', function () {
    config()->set('services.whatsapp.enabled', true);
    config()->set('services.whatsapp.driver', 'log');
    Queue::fake();

    expect($this->pendaftaran->tagihanItem()->exists())->toBeFalse();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran))
        ->assertRedirect(route('staf-ppdb.verifikasi-pendaftaran.index'));

    $notifikasi = NotifikasiWhatsapp::sole();

    expect($this->pendaftaran->refresh()->tagihanItem()->exists())->toBeTrue()
        ->and($this->pendaftaran->minimal_bayar)->not->toBeNull()
        ->and($notifikasi->status)->toBe('menunggu')
        ->and($notifikasi->nomor_tujuan)->toBe('6281200000001')
        ->and($notifikasi->pesan)->toContain($this->pendaftaran->nama_pendaftar)
        ->and($notifikasi->pesan)->toContain($this->pendaftaran->nomor_pendaftaran);

    Queue::assertPushed(
        KirimNotifikasiWhatsApp::class,
        fn (KirimNotifikasiWhatsApp $job) => $job->notifikasiId === $notifikasi->id
    );
});

test('notifikasi tidak diantrikan jika wali menonaktifkannya', function () {
    config()->set('services.whatsapp.enabled', true);
    Queue::fake();
    $this->pendaftaran->user->update(['notifikasi_whatsapp_aktif' => false]);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran));

    expect(NotifikasiWhatsapp::sole()->status)->toBe('dilewati');
    Queue::assertNothingPushed();
});

test('persetujuan dibatalkan jika tarif belum dikonfigurasi', function () {
    TarifKategori::where('gelombang_ppdb_id', $this->pendaftaran->gelombang_ppdb_id)
        ->where('kategori_siswa_id', $this->pendaftaran->kategori_siswa_id)
        ->delete();

    $this->actingAs($this->staf)
        ->from(route('staf-ppdb.verifikasi-pendaftaran.show', $this->pendaftaran))
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran))
        ->assertRedirect(route('staf-ppdb.verifikasi-pendaftaran.show', $this->pendaftaran))
        ->assertSessionHas('error');

    expect($this->pendaftaran->refresh()->status)->toBe('diajukan')
        ->and($this->pendaftaran->tagihanItem()->exists())->toBeFalse()
        ->and(NotifikasiWhatsapp::count())->toBe(0);
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

test('tidak bisa menyetujui pendaftaran diajukan yang berkasnya tidak lengkap', function () {
    $this->pendaftaran->dokumen()->firstOrFail()->delete();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran))
        ->assertForbidden();

    expect($this->pendaftaran->refresh()->status)->toBe('diajukan')
        ->and($this->pendaftaran->tagihanItem()->exists())->toBeFalse()
        ->and(NotifikasiWhatsapp::count())->toBe(0);
});

test('tidak bisa meminta perbaikan untuk pendaftaran diajukan yang berkasnya tidak lengkap', function () {
    $this->pendaftaran->dokumen()->firstOrFail()->delete();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', $this->pendaftaran), [
            'catatan_verifikasi' => 'Dokumen belum lengkap dan tidak boleh masuk proses pemeriksaan.',
        ])
        ->assertForbidden();

    expect($this->pendaftaran->refresh()->status)->toBe('diajukan');
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

/**
 * Titik nol buat mengukur berapa lama wali menggantung sebelum transfer
 * pertamanya. Diisi HANYA di sini - bukan saat staf minta perbaikan, bukan saat
 * menutup pendaftaran. Dua tindakan itu juga "memeriksa", tapi yang diukur satu
 * hal spesifik: sejak kapan wali boleh membayar.
 */
test('menyetujui mencatat kapan berkas dinyatakan lolos', function () {
    expect($this->pendaftaran->diverifikasi_pada)->toBeNull();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.setujui', $this->pendaftaran));

    expect($this->pendaftaran->fresh()->diverifikasi_pada)->not->toBeNull();
});

test('minta perbaikan tidak mencatat waktu verifikasi', function () {
    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', $this->pendaftaran), [
            'catatan_verifikasi' => 'Foto Kartu Keluarga buram, mohon diunggah ulang.',
        ]);

    // Wali belum boleh membayar, jadi jam "menggantung" belum boleh mulai
    // berjalan - kalau ikut terisi di sini, jedanya terhitung sejak percobaan
    // yang justru gagal.
    expect($this->pendaftaran->fresh()->diverifikasi_pada)->toBeNull();
});
