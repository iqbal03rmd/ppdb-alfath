<?php

use App\Models\GelombangPpdb;
use App\Models\KuotaKategori;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed();

    $this->kepsek = User::where('email', 'kepsek@ppdbalfath.test')->firstOrFail();
});

test('kepala sekolah bisa membuka beranda', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('kepala-sekolah/dashboard'));
});

test('kepala sekolah bisa membuka rekapitulasi', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.rekapitulasi'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('kepala-sekolah/rekapitulasi'));
});

test('peran lain tidak boleh membuka modul kepala sekolah', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();

    $this->actingAs($user)->get(route('kepala-sekolah.dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('kepala-sekolah.rekapitulasi'))->assertForbidden();
})->with(['wali@ppdbalfath.test', 'staf@ppdbalfath.test']);

/**
 * PRD 8.3: Kepala Sekolah MONITORING SAJA, tidak ikut memutuskan diterima atau
 * ditolak. Test ini yang menahan kalau suatu saat ada yang menambahkan aksi -
 * seluruh route modul ini wajib GET.
 */
test('modul kepala sekolah tidak punya satu pun route yang mengubah data', function () {
    $rute = collect(Route::getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'kepala-sekolah.'));

    expect($rute)->not->toBeEmpty();

    $rute->each(function ($r) {
        expect(array_diff($r->methods(), ['GET', 'HEAD']))
            ->toBe([], "Route {$r->getName()} boleh GET saja - Kepala Sekolah tidak memutuskan apa pun.");
    });
});

/**
 * Draft belum pernah dikirim wali: sekolah belum punya hubungan dengannya dan
 * kursi kuota belum dipegang. Kalau ikut terhitung, laporan menyebut pendaftar
 * lebih banyak daripada kenyataannya.
 */
test('pendaftaran draft tidak ikut dihitung', function () {
    $draft = PendaftaranPpdb::where('status', 'draft')->count();
    $semua = PendaftaranPpdb::count();

    expect($draft)->toBeGreaterThan(0);

    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.dashboard'))
        ->assertInertia(function (AssertableInertia $page) use ($draft, $semua) {
            $props = $page->toArray()['props'];

            expect($props['ringkasan']['total'])->toBe($semua - $draft)
                ->and($props['statistik']['pendaftaran'])->not->toHaveKey('draft');
        });
});

test('ringkasan beranda cocok dengan hitungan langsung', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ringkasan.diterima', PendaftaranPpdb::where('status', 'diterima')->count())
            ->where('ringkasan.ditolak', PendaftaranPpdb::where('status', 'ditolak')->count())
            ->where('ringkasan.diproses', PendaftaranPpdb::whereIn('status', ['diajukan', 'perlu_perbaikan', 'diverifikasi'])->count())
        );
});

/**
 * Uang yang masuk HANYA yang sudah disahkan staf. Bukti yang masih menunggu
 * diperiksa dilaporkan terpisah - sebagiannya bisa ditolak, jadi menggabungkan
 * keduanya berarti melaporkan pemasukan yang belum tentu ada.
 */
test('uang masuk hanya menghitung transfer yang sudah disahkan', function () {
    $terverifikasi = (int) PembayaranPpdb::where('status', 'terverifikasi')->sum('nominal_transfer');
    $menunggu = (int) PembayaranPpdb::where('status', 'menunggu_verifikasi')->sum('nominal_transfer');

    expect($menunggu)->toBeGreaterThan(0);

    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('keuangan.sudah_masuk', $terverifikasi)
            ->where('keuangan.menunggu_diperiksa', $menunggu)
        );
});

test('sisa tagihan tidak pernah negatif', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('keuangan.sisa_tagihan', fn ($sisa) => $sisa >= 0)
        );
});

test('kartu kuota memakai hitungan KuotaKategori', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.dashboard'))
        ->assertInertia(function (AssertableInertia $page) {
            $kuota = $page->toArray()['props']['kuota'];

            expect($kuota)->not->toBeEmpty();

            foreach ($kuota as $k) {
                expect($k['sisa'])->toBe(max(0, $k['kuota'] - $k['terpakai']));
            }
        });
});

test('rekap per gelombang menjumlah ke angka yang sama dengan beranda', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.rekapitulasi'))
        ->assertInertia(function (AssertableInertia $page) {
            $baris = collect($page->toArray()['props']['perGelombang']);

            expect($baris)->not->toBeEmpty()
                ->and($baris->sum('total'))->toBe(PendaftaranPpdb::where('status', '!=', 'draft')->count())
                ->and($baris->sum('diterima'))->toBe(PendaftaranPpdb::where('status', 'diterima')->count());
        });
});

/**
 * Kursi kuota dipegang sejak formulir dikirim sampai pendaftaran ditutup, jadi
 * yang masih diproses pun memakainya - "sisa" tidak boleh diturunkan dari jumlah
 * yang sudah diterima saja.
 *
 * Kolom "terpakai" sengaja tidak dikirim ke layar (keputusan user 7 September
 * 2026): angkanya selalu total - ditolak, jadi cukup diwakili kolom "ditolak".
 * Test ini menjaga bahwa penyederhanaan itu tetap konsisten - angka yang TAMPIL
 * di satu baris harus benar-benar menjumlah, kalau tidak pembacanya melihat
 * Kuota - Sisa yang tidak cocok dengan Pendaftar tanpa penjelasan.
 */
test('rekap per kategori memakai aturan kuota, bukan jumlah yang diterima', function () {
    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.rekapitulasi'))
        ->assertInertia(function (AssertableInertia $page) {
            $baris = collect($page->toArray()['props']['perKategori']);

            expect($baris)->not->toBeEmpty()
                ->and($baris->count())->toBe(KuotaKategori::count());

            foreach ($baris as $b) {
                $memegangKursi = $b['total'] - $b['ditolak'];

                expect($b['sisa'])->toBe(max(0, $b['kuota'] - $memegangKursi))
                    // Yang diterima adalah bagian dari yang memegang kursi, jadi
                    // tidak pernah lebih banyak.
                    ->and($b['diterima'])->toBeLessThanOrEqual($memegangKursi)
                    // Kolom "terpakai" tidak boleh diam-diam dihidupkan lagi -
                    // itu yang dulu bikin bingung karena mirip "Pendaftar".
                    ->and($b)->not->toHaveKey('terpakai');
            }
        });
});

/**
 * Penjaga khusus untuk kasus yang jadi alasan kolom "ditolak" diadakan: begitu
 * ada satu pendaftaran ditolak, kursinya lepas, dan selisih antara Pendaftar dan
 * Sisa harus tepat sebesar jumlah yang ditolak itu.
 */
test('pendaftaran yang ditolak melepas kursi dan selisihnya terbaca di kolom ditolak', function () {
    $kuota = KuotaKategori::with('gelombang')->firstOrFail();

    $korban = PendaftaranPpdb::where('gelombang_ppdb_id', $kuota->gelombang_ppdb_id)
        ->where('kategori_siswa_id', $kuota->kategori_siswa_id)
        ->whereIn('status', ['diajukan', 'perlu_perbaikan', 'diverifikasi'])
        ->first();

    if ($korban === null) {
        $this->markTestSkipped('Seeder tidak menyediakan pendaftaran yang bisa ditutup pada kategori ini.');
    }

    $korban->update(['status' => 'ditolak', 'catatan_verifikasi' => 'Uji rekapitulasi.']);

    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.rekapitulasi'))
        ->assertInertia(function (AssertableInertia $page) use ($kuota) {
            $baris = collect($page->toArray()['props']['perKategori'])
                ->firstWhere(fn ($b) => $b['gelombang_id'] === $kuota->gelombang_ppdb_id
                    && $b['kategori'] === $kuota->kategoriSiswa->nama);

            expect($baris)->not->toBeNull()
                ->and($baris['ditolak'])->toBeGreaterThanOrEqual(1)
                ->and($baris['sisa'])->toBe(max(0, $baris['kuota'] - ($baris['total'] - $baris['ditolak'])));
        });
});

test('rekapitulasi terbuka pada tahun ajaran aktif', function () {
    $aktif = GelombangPpdb::with('tahunAjaran')->where('status_buka', true)->firstOrFail()->tahunAjaran->nama;

    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.rekapitulasi'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filterAwal', $aktif));
});
