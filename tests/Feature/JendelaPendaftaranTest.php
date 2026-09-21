<?php

use App\Models\AsalPaud;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\PembayaranPendaftaranAwal;
use App\Models\PendaftaranPpdb;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * Jendela pendaftaran - sejak 11 September 2026 status_buka BUKAN lagi satu-satunya
 * penentu.
 *
 *   status_buka              NIAT sekolah - saklar yang ditekan Admin
 *   tanggal_mulai/selesai    JENDELA-nya - tanggal yang tercetak ke halaman wali
 *
 * Pendaftaran terbuka cuma kalau keduanya setuju. Tanpa syarat tanggal, gelombang
 * yang terlanjur terbuka melewati tanggal_selesai akan terus menerima pendaftar
 * SELAMANYA sampai ada orang yang ingat menekan Tutup - dan tanggal penutupan yang
 * sudah diumumkan ke orang tua jadi bohong tanpa ada yang menyentuhnya.
 *
 * Yang dijaga berkas ini: keempat layar yang menyebut "gelombang yang sedang
 * berjalan" harus sepakat. Kalau Beranda wali menawarkan gelombang yang
 * formulirnya justru menolak, wali melihat tombol yang membawanya ke jalan buntu.
 */
beforeEach(function () {
    $this->seed();

    $this->wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();
    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();
    $this->kepsek = User::where('email', 'kepsek@ppdbalfath.test')->firstOrFail();
    $this->gelombang = GelombangPpdb::where('status_buka', true)->firstOrFail();
    PembayaranPendaftaranAwal::create([
        'user_id' => $this->wali->id,
        'gelombang_ppdb_id' => $this->gelombang->id,
        'nominal_tagihan' => 125000,
        'nominal_transfer' => 125000,
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'uji/bukti.jpg',
        'status' => 'terverifikasi',
        'diverifikasi_pada' => now(),
    ]);
});

/** Saklarnya TIDAK disentuh - cuma tanggalnya yang digeser. */
function jendelaLewat(GelombangPpdb $g): void
{
    $g->update([
        'tanggal_mulai' => today()->subMonths(3),
        'tanggal_selesai' => today()->subDay(),
    ]);
}

function jendelaBelumMulai(GelombangPpdb $g): void
{
    $g->update([
        'tanggal_mulai' => today()->addMonth(),
        'tanggal_selesai' => today()->addMonths(2),
    ]);
}

function muatanPendaftaranJendela(): array
{
    return [
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Reguler')->value('id'),
        'nama_pendaftar' => 'Anak Terlambat',
        'nik' => '1471010101200099',
        'tanggal_lahir' => '2020-05-05',
        'tempat_lahir' => 'Pekanbaru',
        'jenis_kelamin' => 'laki-laki',
        'alamat' => 'Jl. Uji No. 1',
        'rt' => '003',
        'rw' => '005',
        'kelurahan' => 'Sidomulyo Timur',
        'kecamatan' => 'Marpoyan Damai',
        'kota_kabupaten' => 'Kota Pekanbaru',
        'provinsi' => 'Riau',
        'tanpa_paud' => false,
        'asal_paud_id' => AsalPaud::value('id'),
        'wali_murid' => [
            ['nama' => 'Wali Uji', 'nik' => '1471010101800099', 'hubungan' => 'Ayah', 'telepon' => '081200000099'],
        ],
    ];
}

test('gelombang di dalam jendelanya memang menerima pendaftar', function () {
    // Kontrol positif. Tanpa ini, seluruh uji di bawah bisa lulus cuma karena
    // scope-nya tidak pernah mengembalikan apa pun.
    expect($this->gelombang->sedangMenerimaPendaftar())->toBeTrue()
        ->and(GelombangPpdb::menerimaPendaftar()->count())->toBe(1);
});

test('saklar menyala tapi jendela lewat berarti tidak menerima pendaftar', function () {
    jendelaLewat($this->gelombang);

    expect($this->gelombang->refresh()->status_buka)->toBeTrue()
        ->and($this->gelombang->sedangMenerimaPendaftar())->toBeFalse()
        ->and(GelombangPpdb::menerimaPendaftar()->count())->toBe(0);
});

test('saklar menyala tapi jendela belum mulai juga belum menerima pendaftar', function () {
    jendelaBelumMulai($this->gelombang);

    expect($this->gelombang->refresh()->sedangMenerimaPendaftar())->toBeFalse()
        ->and(GelombangPpdb::menerimaPendaftar()->count())->toBe(0);
});

/*
| Sisi wali - yang paling menentukan, karena ini gerbang uangnya
*/

test('wali tidak ditawari mendaftar lagi sesudah jendelanya lewat', function (string $rute) {
    jendelaLewat($this->gelombang);

    $respons = $this->actingAs($this->wali)->get(route($rute));

    if (str_contains($rute, 'create')) {
        $respons->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));
    } else {
        $respons->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('gelombangDibuka', fn ($nilai) => $nilai === null || $nilai === false)
                ->etc());
    }
})->with(['wali-murid.pendaftaran.index', 'wali-murid.pendaftaran.create']);

test('beranda wali sepakat dengan formulirnya', function () {
    // Beranda dan formulir HARUS memakai syarat yang sama. Kalau beda, Beranda
    // menawarkan gelombang yang formulirnya menolak - tombol menuju jalan buntu.
    jendelaLewat($this->gelombang);

    $this->actingAs($this->wali)
        ->get(route('wali-murid.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('gelombangDibuka', null)->etc());
});

/**
 * Ini gerbang sebenarnya. Dua di atas cuma menyembunyikan tombolnya; yang ini
 * menolak datanya - termasuk kalau permintaannya dikirim langsung.
 */
test('pendaftaran baru ditolak sesudah jendelanya lewat', function () {
    $muatan = muatanPendaftaranJendela();

    // Kontrol: muatan yang sama diterima selagi jendelanya masih terbuka.
    // Tanpa ini, 422 di bawah bisa datang dari validasi yang gagal - bukan dari
    // gerbangnya - dan ujinya lulus tanpa menguji apa pun.
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), $muatan)
        ->assertSessionHasNoErrors();

    PendaftaranPpdb::where('nik', '1471010101200099')->delete();

    PembayaranPendaftaranAwal::create([
        'user_id' => $this->wali->id,
        'gelombang_ppdb_id' => $this->gelombang->id,
        'nominal_tagihan' => 125000,
        'nominal_transfer' => 125000,
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'uji/bukti-kedua.jpg',
        'status' => 'terverifikasi',
        'diverifikasi_pada' => now(),
    ]);

    jendelaLewat($this->gelombang);

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), $muatan)
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));
});

test('draft tidak dapat diedit atau dikirim setelah gelombang ditutup', function () {
    Storage::fake('public');

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), muatanPendaftaranJendela())
        ->assertSessionHasNoErrors();

    $pendaftaran = PendaftaranPpdb::where('nik', '1471010101200099')->firstOrFail();
    $this->gelombang->tutup();

    $this->actingAs($this->wali)
        ->get(route('wali-murid.pendaftaran.edit', $pendaftaran))
        ->assertForbidden();

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.unggah-berkas.store', $pendaftaran), [
            'jenis_dokumen' => 'kartu_keluarga',
            'berkas' => UploadedFile::fake()->image('kk.jpg'),
        ])
        ->assertForbidden();

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.unggah-berkas.submit', $pendaftaran))
        ->assertForbidden();

    $this->actingAs($this->wali)
        ->get(route('wali-murid.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('daftarPendaftaran', function ($daftar) use ($pendaftaran) {
                $draft = collect($daftar)->firstWhere('id', $pendaftaran->id);

                return $draft['status'] === 'draft_kedaluwarsa' && $draft['tombol'] === null;
            })
            ->etc());
});

/*
| Sisi staf & kepala sekolah - layar yang menyebut "sedang berjalan"
*/

test('kartu kuota staf berhenti menyebut gelombang yang jendelanya lewat', function () {
    jendelaLewat($this->gelombang);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kuota', fn ($kuota) => ($kuota['gelombang'] ?? null) === null)
            ->etc());
});

test('rekap kepala sekolah menandai gelombang itu tidak lagi terbuka', function () {
    jendelaLewat($this->gelombang);

    $this->actingAs($this->kepsek)
        ->get(route('kepala-sekolah.rekapitulasi'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('perGelombang', function ($daftar) {
                $baris = collect($daftar)->firstWhere('id', $this->gelombang->id);

                return $baris !== null && $baris['status_buka'] === false;
            })
            ->etc());
});
