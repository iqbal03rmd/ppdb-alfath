<?php

use App\Models\GelombangPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Halaman arsip pendaftaran milik staf: daftar semua pendaftaran + detail satu
 * pendaftaran lengkap dengan posisi pembayarannya.
 *
 * Sama seperti test staf yang lain, dijalankan di atas data seed - seeder-nya
 * sengaja menyediakan satu pendaftaran untuk tiap status.
 */
beforeEach(function () {
    $this->seed();

    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();
});

test('staf bisa membuka daftar semua pendaftaran', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.index'))
        ->assertOk();
});

/**
 * Tahun ajaran & gelombang jadi dua penyaring yang berdiri sendiri di layar,
 * jadi dua kolom ini wajib ikut terkirim tiap baris.
 */
test('daftar pendaftaran mengirim tahun ajaran dan nama gelombang', function () {
    $pendaftaran = PendaftaranPpdb::with('gelombang.tahunAjaran')->latest()->firstOrFail();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('staf-ppdb/pendaftaran')
            ->where('pendaftaran.0.tahun_ajaran', $pendaftaran->gelombang->tahunAjaran->nama)
            ->where('pendaftaran.0.gelombang', $pendaftaran->gelombang->nama)
        );
});

/**
 * Halaman dibuka sudah menyaring ke tahun ajaran aktif + gelombang yang sedang
 * dibuka, supaya staf tidak perlu menyetel apa-apa untuk pekerjaan harian.
 *
 * Pasangannya harus konsisten: gelombang yang dipilih WAJIB ada di tahun ajaran
 * yang dipilih. Kalau tidak, dua penyaring itu saling meniadakan dan halaman
 * terbuka dengan tabel kosong.
 */
test('penyaring terbuka pada tahun ajaran aktif dan gelombang yang dibuka', function () {
    $tahunAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();
    $gelombangDibuka = GelombangPpdb::where('status_buka', true)->firstOrFail();

    expect($gelombangDibuka->tahun_ajaran_id)->toBe($tahunAktif->id);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filterAwal.tahunAjaran', $tahunAktif->nama)
            ->where('filterAwal.gelombang', $gelombangDibuka->nama)
        );
});

test('wali murid tidak boleh membuka arsip pendaftaran staf', function () {
    $wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();

    $this->actingAs($wali)
        ->get(route('staf-ppdb.pendaftaran.index'))
        ->assertForbidden();
});

/**
 * Arsip harus bisa dibuka untuk SEGALA status, termasuk yang sudah lewat dari
 * antrian verifikasi - justru itu gunanya halaman ini.
 */
test('detail pendaftaran bisa dibuka untuk segala status', function (string $status) {
    $pendaftaran = PendaftaranPpdb::where('status', $status)->firstOrFail();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.show', $pendaftaran))
        ->assertOk();
})->with(['draft', 'diajukan', 'perlu_perbaikan', 'diverifikasi', 'diterima', 'ditolak']);

test('detail pendaftaran yang sudah bayar memuat angka pembayarannya', function () {
    $pendaftaran = PendaftaranPpdb::where('status', 'diterima')->firstOrFail();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.show', $pendaftaran))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('staf-ppdb/pendaftaran-show')
            ->where('ringkasanPembayaran.totalTagihan', $pendaftaran->fresh()->totalTagihan())
            ->where('ringkasanPembayaran.totalTerbayar', $pendaftaran->fresh()->totalTerbayar())
            ->where('ringkasanPembayaran.sudahPenuhiMinimal', true)
        );
});

/**
 * Yang membayar penuh sekaligus tidak punya sisa untuk dicicil, jadi layar
 * tidak boleh menjanjikannya. Layarnya memilih kalimat dari sisaTagihan, jadi
 * yang dikunci di sini nilai yang jadi dasarnya: nol, bukan sekadar
 * "minimalnya terpenuhi" - dua-duanya benar untuk pendaftaran ini, tapi cuma
 * yang pertama yang membedakannya dari wali yang masih mencicil.
 */
test('pendaftaran yang lunas penuh tidak menyisakan tagihan', function () {
    $lunas = PendaftaranPpdb::where('nomor_pendaftaran', 'PPDB-2026-00006')->firstOrFail();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.show', $lunas))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ringkasanPembayaran.sisaTagihan', 0)
            ->where('ringkasanPembayaran.statusPelunasan', 'lunas')
            // Tidak ada tanggal cicilan yang boleh muncul - tidak ada yang dicicil.
            ->where('ringkasanPembayaran.tanggalPelunasanCicilan', null)
            ->where('ringkasanPembayaran.jatuhTempoMinimal', null)
        );
});

/**
 * Penjaga yang paling penting di halaman ini.
 *
 * Tagihan dibekukan saat WALI pertama kali melihatnya - halaman pembayaran wali
 * memang memanggil terbitkanTagihan(). Kalau halaman arsip staf ikut memanggil,
 * tarif bisa terkunci gara-gara staf membuka arsip, bukan gara-gara walinya
 * menagih. Test ini yang menahan supaya panggilan itu tidak diam-diam
 * ditambahkan lagi ke show().
 */
test('membuka detail tidak menerbitkan tagihan', function () {
    // 'ditolak' sudah boleh melihat tagihan tapi di seed belum punya tagihan -
    // persis kondisi yang bikin panggilan terbitkanTagihan() ikut jalan.
    $pendaftaran = PendaftaranPpdb::where('status', 'ditolak')->firstOrFail();

    expect($pendaftaran->bolehLihatTagihan())->toBeTrue()
        ->and($pendaftaran->tagihanItem()->count())->toBe(0);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.pendaftaran.show', $pendaftaran))
        ->assertOk();

    expect($pendaftaran->tagihanItem()->count())->toBe(0);
});
