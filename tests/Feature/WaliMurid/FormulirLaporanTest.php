<?php

use App\Models\AsalPaud;
use App\Models\KategoriSiswa;
use App\Models\PendaftaranPpdb;
use App\Models\User;

/**
 * Dua isian yang ditambahkan buat laporan Kepala Sekolah: asal PAUD dan sumber
 * informasi. Cuma dua ini yang berupa PILIHAN, karena cuma dua ini yang
 * diagregasi jadi grafik.
 *
 * Alamat sengaja tidak ikut diuji sebagai daftar pilihan - seluruh bagiannya
 * teks bebas, termasuk kecamatan (keputusan user, 7 September 2026). Yang diuji
 * dari alamat cuma bahwa bagian-bagiannya wajib diisi dan tersimpan.
 *
 * Yang paling dijaga di berkas ini BUKAN "kolomnya tersimpan", tapi bahwa satu
 * baris tidak pernah memuat dua jawaban yang bertentangan - misalnya sekaligus
 * menunjuk sekolah master DAN ditandai tidak ikut PAUD. Data seperti itu tidak
 * bisa ditafsirkan laporan, dan gampang lolos kalau pembersihannya cuma
 * diandalkan pada tampilan.
 */
beforeEach(function () {
    $this->seed();

    $this->wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();
    $this->paud = AsalPaud::firstOrFail();
});

function formulirDasar(array $tambahan = []): array
{
    return array_merge([
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Reguler')->value('id'),
        'nama_pendaftar' => 'Uji Coba Anak',
        'nik' => '1471010101200001',
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
        'wali_murid' => [
            ['nama' => 'Wali Uji', 'nik' => '1471010101800001', 'hubungan' => 'Ayah', 'telepon' => '081200000001'],
        ],
    ], $tambahan);
}

test('asal PAUD wajib dijawab salah satu dari tiga cara', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar())
        ->assertSessionHasErrors('asal_paud_id');
});

test('sekolah asal boleh diketik sendiri kalau belum ada di daftar', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_lainnya' => 'TK Bina Insani Kampar',
        ]))
        ->assertSessionHasNoErrors();

    $baru = PendaftaranPpdb::where('nama_pendaftar', 'Uji Coba Anak')->firstOrFail();

    expect($baru->asal_paud_id)->toBeNull()
        ->and($baru->asal_paud_lainnya)->toBe('TK Bina Insani Kampar')
        ->and($baru->labelAsalPaud())->toBe('TK Bina Insani Kampar');
});

/**
 * Ketikan bebas TIDAK ditulis balik jadi baris master. Satu typo wali akan
 * langsung jadi "sekolah" baru yang memecah hitungan - persis data kotor yang
 * mau dihindari dengan menyediakan daftar pilihan.
 */
test('sekolah yang diketik sendiri tidak menambah baris ke daftar master', function () {
    $sebelum = AsalPaud::count();

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_lainnya' => 'TK Yang Belum Terdaftar',
        ]));

    expect(AsalPaud::count())->toBe($sebelum);
});

test('memilih tidak ikut PAUD membuang sekolah asal yang sempat terisi', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'tanpa_paud' => true,
            'asal_paud_id' => $this->paud->id,
            'asal_paud_lainnya' => 'TK Sisa Ketikan',
        ]))
        ->assertSessionHasNoErrors();

    $baru = PendaftaranPpdb::where('nama_pendaftar', 'Uji Coba Anak')->firstOrFail();

    expect($baru->asal_paud_id)->toBeNull()
        ->and($baru->asal_paud_lainnya)->toBeNull()
        ->and($baru->tanpa_paud)->toBeTrue()
        ->and($baru->labelAsalPaud())->toBe('Belum/tidak ikut PAUD');
});

/**
 * Satu-satunya isian yang boleh dilewati. Kalau ini sampai jadi wajib, wali yang
 * tidak ingat akan asal pilih - dan jawaban asal lebih merusak laporan daripada
 * tidak ada jawaban.
 */
test('sumber informasi boleh tidak dijawab', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
        ]))
        ->assertSessionHasNoErrors();

    $baru = PendaftaranPpdb::where('nama_pendaftar', 'Uji Coba Anak')->firstOrFail();

    expect($baru->tahu_dari)->toBeNull()
        ->and($baru->labelSumberInformasi())->toBe('Tidak menjawab');
});

test('memilih lainnya mewajibkan menyebutkan sumbernya', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
            'tahu_dari' => 'lainnya',
        ]))
        ->assertSessionHasErrors('tahu_dari_lainnya');
});

test('keterangan lainnya dibuang kalau sumbernya diganti ke pilihan tetap', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
            'tahu_dari' => 'brosur_spanduk',
            'tahu_dari_lainnya' => 'Sisa ketikan sebelumnya',
        ]))
        ->assertSessionHasNoErrors();

    expect(PendaftaranPpdb::where('nama_pendaftar', 'Uji Coba Anak')->firstOrFail()->tahu_dari_lainnya)->toBeNull();
});

test('sumber informasi di luar daftar pilihan ditolak', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
            'tahu_dari' => 'papan_reklame_bandara',
        ]))
        ->assertSessionHasErrors('tahu_dari');
});

test('halaman formulir mengirim daftar sekolah asal dan sumber informasi', function () {
    $this->actingAs($this->wali)
        ->get(route('wali-murid.pendaftaran.create'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            // Tidak ada daftar wilayah yang dikirim - alamat seluruhnya diketik
            // sendiri. Kalau suatu saat prop wilayah muncul lagi di sini, berarti
            // ada yang menghidupkan ulang dropdown yang sengaja dibuang.
            expect($props)->not->toHaveKey('kabupatenKota');

            // Sekolah asal dikirim berkelompok per jenis, tapi jumlah totalnya
            // wajib tetap sama dengan isi tabel - kalau ada kelompok yang
            // terlewat, sekolahnya hilang dari daftar pilihan tanpa ada galat.
            $totalSekolah = collect($props['asalPaud'])->sum(fn ($k) => count($k['sekolah']));

            expect($totalSekolah)->toBe(AsalPaud::count())
                ->and($props['sumberInformasi'])->toHaveCount(count(PendaftaranPpdb::SUMBER_INFORMASI));

            // RA wajib ada kelompoknya sendiri. Untuk SD Islam Terpadu, jalur
            // madrasah justru penyumbang besar - kalau RA tidak terpisah, temuan
            // yang paling ingin dilihat kepala sekolah ikut hilang.
            expect(collect($props['asalPaud'])->pluck('jenis'))->toContain('TK', 'RA');
        });
});

test('bagian alamat tersimpan apa adanya', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
        ]))
        ->assertSessionHasNoErrors();

    $baru = PendaftaranPpdb::where('nama_pendaftar', 'Uji Coba Anak')->firstOrFail();

    expect($baru->kelurahan)->toBe('Sidomulyo Timur')
        ->and($baru->kecamatan)->toBe('Marpoyan Damai')
        ->and($baru->kota_kabupaten)->toBe('Kota Pekanbaru')
        ->and($baru->provinsi)->toBe('Riau')
        ->and($baru->rt)->toBe('003')
        ->and($baru->rw)->toBe('005');
});

test('kelurahan sampai provinsi wajib diisi', function (string $kolom) {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
            $kolom => '',
        ]))
        ->assertSessionHasErrors($kolom);
})->with(['kelurahan', 'kecamatan', 'kota_kabupaten', 'provinsi']);

// RT/RW boleh kosong: banyak perumahan tidak memakainya, dan mewajibkannya
// bikin wali mengarang angka supaya bisa lanjut.
test('RT dan RW boleh dikosongkan', function () {
    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), formulirDasar([
            'asal_paud_id' => $this->paud->id,
            'rt' => '',
            'rw' => '',
        ]))
        ->assertSessionHasNoErrors();
});

/**
 * Nama sekolah TIDAK unik. Di Pekanbaru ada empat pasang yang kembar persis -
 * TK NURUL IMAN berdiri di Bukit Raya DAN di Tenayan Raya, begitu juga TK AMAL
 * IKHLAS, TK BAITURRAHMAN, dan TK ISLAM ASY SYAKIRIN.
 *
 * Tanpa kecamatan di labelnya, wali tidak bisa tahu mana yang dia pilih, dan di
 * grafik laporan dua sekolah berbeda muncul sebagai dua batang yang tulisannya
 * sama persis.
 */
test('sekolah yang namanya kembar dibedakan oleh kecamatannya', function () {
    $kembar = AsalPaud::where('jenis', 'TK')->where('nama', 'NURUL IMAN')->get();

    expect($kembar)->toHaveCount(2)
        ->and($kembar->map->namaLengkap()->unique())->toHaveCount(2)
        ->and($kembar->map->namaDenganKecamatan()->unique())->toHaveCount(2);
});

test('daftar pilihan menyebut kecamatan tiap sekolah', function () {
    $this->actingAs($this->wali)
        ->get(route('wali-murid.pendaftaran.create'))
        ->assertInertia(function ($page) {
            $kelompok = collect($page->toArray()['props']['asalPaud']);
            $sekolah = $kelompok->flatMap(fn ($k) => $k['sekolah']);

            // Kalau ada satu saja yang tidak menyebut kecamatan, berarti
            // labelnya diambil dari kolom mentah, bukan lewat model.
            expect($sekolah->filter(fn ($s) => ! str_contains($s['nama'], ' · ')))->toBeEmpty();

            // Keunikan diuji PER KELOMPOK, bukan lintas kelompok. Satu yayasan
            // sering menaungi TK, KB, dan TPA bernama sama di kecamatan yang
            // sama - "ABIDARI · Bukit Raya" muncul di tiga kelompok sekaligus,
            // dan itu benar: di layar ketiganya berada di bawah judul jenis
            // yang berbeda, jadi tidak mungkin tertukar.
            foreach ($kelompok as $k) {
                $nama = collect($k['sekolah'])->pluck('nama');

                expect($nama->unique())->toHaveCount($nama->count(), "Ada nama kembar di kelompok {$k['jenis']}.");
            }
        });
});
