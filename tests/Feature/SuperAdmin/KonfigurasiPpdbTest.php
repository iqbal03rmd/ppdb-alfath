<?php

use App\Models\BerkasPersyaratan;
use App\Models\DokumenPpdb;
use App\Models\DokumenWajibKategori;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KebijakanKategori;
use App\Models\KomponenBiaya;
use App\Models\PendaftaranPpdb;
use App\Models\TahunAjaran;
use App\Models\TarifKategori;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed();

    $this->admin = User::where('email', 'superadmin@ppdbalfath.test')->firstOrFail();
    $this->tahunAjaran = TahunAjaran::where('status_aktif', true)->firstOrFail();
    $this->gelombang = GelombangPpdb::where('tahun_ajaran_id', $this->tahunAjaran->id)->firstOrFail();
    $this->reguler = KategoriSiswa::where('nama', 'Reguler')->firstOrFail();
    $this->yatim = KategoriSiswa::where('nama', 'Anak Yatim')->firstOrFail();
    $this->seragam = KomponenBiaya::where('nama', 'Seragam')->firstOrFail();
});

/**
 * Bentuk minimal muatan "Ubah Gelombang". Syarat berkas sekarang ikut disimpan
 * di sini, jadi tiap uji yang menyentuh gelombang harus menyertakannya - kalau
 * tidak, menyimpan jadwal ikut mengosongkan syarat berkas seluruh jalur.
 */
function muatanGelombang(GelombangPpdb $g, array $timpa = []): array
{
    $dokumen = DokumenWajibKategori::where('gelombang_ppdb_id', $g->id)
        ->get()
        ->groupBy('kategori_siswa_id')
        ->map(fn ($baris) => $baris->pluck('jenis_dokumen')->all())
        ->all();

    return [
        'tahun_ajaran_id' => $g->tahun_ajaran_id,
        'nama' => $g->nama,
        'tanggal_mulai' => $g->tanggal_mulai?->format('Y-m-d'),
        'tanggal_selesai' => $g->tanggal_selesai?->format('Y-m-d'),
        'batas_waktu_pembayaran' => $g->batas_waktu_pembayaran?->format('Y-m-d'),
        'minimal_pembayaran' => $g->minimal_pembayaran,
        'kebijakan' => [],
        'dokumen' => $dokumen,
        ...$timpa,
    ];
}

/*
| Akses
*/

test('empat halaman konfigurasi terbuka untuk super admin', function (string $rute, string $komponen) {
    $this->actingAs($this->admin)
        ->get(route($rute))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($komponen));
})->with([
    ['super-admin.tahun-ajaran.index', 'super-admin/tahun-ajaran'],
    ['super-admin.komponen-biaya.index', 'super-admin/komponen-biaya'],
    ['super-admin.gelombang.index', 'super-admin/gelombang'],
    ['super-admin.jalur.index', 'super-admin/jalur'],
]);

test('peran lain tidak boleh membuka konfigurasi', function (string $email) {
    $this->actingAs(User::where('email', $email)->firstOrFail())
        ->get(route('super-admin.gelombang.index'))
        ->assertForbidden();
})->with(['staf@ppdbalfath.test', 'kepsek@ppdbalfath.test', 'wali@ppdbalfath.test']);

/*
| Tahun ajaran
*/

test('tahun ajaran baru jadi arsip kalau tidak diminta berjalan', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.tahun-ajaran.store'), ['nama' => '2027/2028', 'tahun_mulai' => 2027, 'batas_pelunasan' => '2028-03-31'])
        ->assertSessionHasNoErrors();

    // Bawaannya arsip, bukan berjalan. Kalau kebalik, tahun ajaran berjalan
    // berpindah diam-diam begitu admin menyiapkan angkatan berikutnya - padahal
    // PPDB tahun ini masih berlangsung.
    expect(TahunAjaran::where('nama', '2027/2028')->firstOrFail()->status_aktif)->toBeFalse()
        ->and($this->tahunAjaran->refresh()->status_aktif)->toBeTrue();
});

test('tahun ajaran baru bisa langsung dijadikan berjalan, dan yang lama jadi arsip', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.tahun-ajaran.store'), [
            'nama' => '2027/2028',
            'tahun_mulai' => 2027,
            'batas_pelunasan' => '2028-03-31',
            'status_aktif' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(TahunAjaran::where('nama', '2027/2028')->firstOrFail()->status_aktif)->toBeTrue()
        ->and($this->tahunAjaran->refresh()->status_aktif)->toBeFalse()
        // Yang penting bukan cuma "yang baru menyala", tapi tidak pernah ada
        // dua yang menyala bersamaan - seluruh sistem membacanya lewat first().
        ->and(TahunAjaran::where('status_aktif', true)->count())->toBe(1);
});

test('tahun ajaran arsip bisa dijadikan berjalan lewat formulir ubah', function () {
    $lain = TahunAjaran::create([
        'nama' => '2027/2028', 'tahun_mulai' => 2027, 'status_aktif' => false, 'batas_pelunasan' => '2028-03-31',
    ]);

    $this->actingAs($this->admin)
        ->put(route('super-admin.tahun-ajaran.update', $lain), [
            'nama' => '2027/2028', 'tahun_mulai' => 2027, 'batas_pelunasan' => '2028-03-31', 'status_aktif' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($lain->refresh()->status_aktif)->toBeTrue()
        ->and($this->tahunAjaran->refresh()->status_aktif)->toBeFalse()
        ->and(TahunAjaran::where('status_aktif', true)->count())->toBe(1);
});

test('status berjalan tidak bisa dimatikan lewat formulir', function () {
    // Formulir cuma bisa MENYALAKAN. Layarnya mengunci centang milik tahun
    // ajaran yang sedang berjalan, tapi penjaga sebenarnya di server: kalau
    // permintaan yang dikarang sendiri bisa mematikannya, sistem berakhir
    // dengan NOL tahun ajaran aktif - Beranda Kepala Sekolah kosong dan
    // penyaring Semua Pendaftaran kehilangan posisi awalnya, tanpa ada satu
    // pun pesan yang menjelaskan kenapa.
    $this->actingAs($this->admin)
        ->put(route('super-admin.tahun-ajaran.update', $this->tahunAjaran), [
            'nama' => $this->tahunAjaran->nama,
            'tahun_mulai' => $this->tahunAjaran->tahun_mulai,
            'batas_pelunasan' => '2027-03-31',
            'status_aktif' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($this->tahunAjaran->refresh()->status_aktif)->toBeTrue()
        ->and(TahunAjaran::where('status_aktif', true)->count())->toBe(1);
});

test('daftar tahun ajaran membawa tanggal dalam dua bentuk sekaligus', function () {
    // Formulir tambah/ubah tahun ajaran sekarang modal di atas daftarnya, jadi
    // tidak ada lagi permintaan kedua ke server yang bisa mengangkut nilai
    // mentahnya. Kalau batas_pelunasan_iso hilang dari sini, isian tanggal di
    // modal Ubah diam-diam terbuka kosong - dan menyimpannya MENGHAPUS tanggal
    // yang sudah diatur, tanpa ada yang kelihatan salah di layar.
    $this->tahunAjaran->update(['batas_pelunasan' => '2027-07-01']);

    $this->actingAs($this->admin)
        ->get(route('super-admin.tahun-ajaran.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tahunAjaran.0', fn (AssertableInertia $baris) => $baris
                ->where('batas_pelunasan', '01 Juli 2027')
                ->where('batas_pelunasan_iso', '2027-07-01')
                ->etc()));
});

test('batas pelunasan wajib diisi', function () {
    // Tanggal ini bukan penentu apa-apa di sistem - lewatnya tidak menggugurkan
    // pendaftaran siapa pun. Yang bikin wajib adalah pembacanya: halaman
    // Pembayaran dan Beranda wali mencetaknya sebagai pengingat kapan cicilan
    // harus lunas. Boleh kosong berarti sebagian wali dapat kalimat pengingat
    // tanpa tanggal - dan itu justru menyesatkan, bukan sekadar kurang.
    $this->actingAs($this->admin)
        ->post(route('super-admin.tahun-ajaran.store'), ['nama' => '2028/2029', 'tahun_mulai' => 2028])
        ->assertSessionHasErrors('batas_pelunasan');

    expect(TahunAjaran::where('nama', '2028/2029')->exists())->toBeFalse();
});

test('batas pelunasan tidak bisa dikosongkan lewat ubah', function () {
    // Pagar yang sama dari arah sebaliknya: yang sudah punya tanggal tidak boleh
    // kehilangan tanggalnya, termasuk pada tahun ajaran yang sudah ada isinya.
    $this->actingAs($this->admin)
        ->put(route('super-admin.tahun-ajaran.update', $this->tahunAjaran), [
            'nama' => $this->tahunAjaran->nama,
            'tahun_mulai' => $this->tahunAjaran->tahun_mulai,
            'batas_pelunasan' => '',
        ])
        ->assertSessionHasErrors('batas_pelunasan');

    expect($this->tahunAjaran->refresh()->batas_pelunasan)->not->toBeNull();
});

test('tambah dan ubah tahun ajaran tidak punya halaman sendiri', function (string $rute) {
    // Modal menggantikan dua halaman formulir. Route GET-nya ikut dibuang -
    // kalau dihidupkan lagi tanpa halamannya, tautannya jadi layar kosong.
    expect(Illuminate\Support\Facades\Route::has($rute))->toBeFalse();
})->with(['super-admin.tahun-ajaran.create', 'super-admin.tahun-ajaran.edit']);

test('tidak ada route mengaktifkan yang berdiri sendiri', function () {
    // Status aktif berpindah lewat centang di modal (store/update), bukan lewat
    // tombol tersendiri di daftar. Route-nya ikut dibuang bareng tombolnya -
    // endpoint POST yang tidak dituju layar mana pun cuma jadi jalan masuk yang
    // tidak ada yang menjaganya.
    expect(Illuminate\Support\Facades\Route::has('super-admin.tahun-ajaran.aktifkan'))->toBeFalse();
});

/*
| Berkas persyaratan - daftar master jenis berkas
*/

test('jenis berkas baru langsung bisa diwajibkan sebuah jalur', function () {
    // Inti seluruh perubahan ini: menambah jenis berkas TIDAK perlu programmer.
    // Dulu daftarnya konstanta DokumenPpdb::LABEL plus enum di dua tabel, jadi
    // satu jenis baru berarti satu migration.
    $this->actingAs($this->admin)
        ->post(route('super-admin.berkas-persyaratan.store'), ['nama' => 'Rapor PAUD', 'urutan' => 7])
        ->assertSessionHasNoErrors();

    $rapor = BerkasPersyaratan::where('nama', 'Rapor PAUD')->firstOrFail();

    expect($rapor->kode)->toBe('rapor_paud')
        ->and($rapor->status_aktif)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('super-admin.gelombang.update', $this->gelombang), muatanGelombang($this->gelombang, [
            'dokumen' => [$this->reguler->id => ['kartu_keluarga', 'rapor_paud']],
        ]))
        ->assertSessionHasNoErrors();

    expect($this->gelombang->refresh()->dokumenWajibUntuk($this->reguler->id))->toContain('rapor_paud');
});

test('jenis berkas non-aktif berhenti diminta walau gelombangnya masih mewajibkan', function () {
    $kk = BerkasPersyaratan::where('kode', 'kartu_keluarga')->firstOrFail();

    expect($this->gelombang->dokumenWajibUntuk($this->reguler->id))->toContain('kartu_keluarga');

    $kk->update(['status_aktif' => false]);

    // Barisnya di dokumen_wajib_kategori SENGAJA tidak ikut dihapus - dinyalakan
    // lagi, gelombang yang dulu memintanya langsung meminta lagi tanpa disetel ulang.
    expect($this->gelombang->refresh()->dokumenWajibUntuk($this->reguler->id))->not->toContain('kartu_keluarga')
        ->and(DokumenWajibKategori::where('kategori_siswa_id', $this->reguler->id)
            ->where('jenis_dokumen', 'kartu_keluarga')->exists())->toBeTrue();

    $kk->update(['status_aktif' => true]);

    expect($this->gelombang->refresh()->dokumenWajibUntuk($this->reguler->id))->toContain('kartu_keluarga');
});

test('mengganti nama jenis berkas tidak memutus berkas yang sudah diunggah', function () {
    // Kode-nya beku, namanya bebas. Kalau kodenya ikut berubah, seluruh baris
    // dokumen_ppdb kehilangan jenisnya sekaligus - berkas yang sudah diunggah
    // wali jadi menggantung tanpa nama di layar staf.
    $kk = BerkasPersyaratan::where('kode', 'kartu_keluarga')->firstOrFail();
    $dokumen = DokumenPpdb::where('jenis_dokumen', 'kartu_keluarga')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('super-admin.berkas-persyaratan.update', $kk), [
            'nama' => 'Kartu Keluarga Terbaru',
            'urutan' => $kk->urutan,
            'status_aktif' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($kk->refresh()->kode)->toBe('kartu_keluarga')
        ->and($dokumen->refresh()->jenis_dokumen)->toBe('kartu_keluarga')
        ->and($dokumen->label())->toBe('Kartu Keluarga Terbaru');
});

test('jenis berkas yang sudah pernah diunggah tidak bisa dihapus', function () {
    $kk = BerkasPersyaratan::where('kode', 'kartu_keluarga')->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('super-admin.berkas-persyaratan.destroy', $kk))
        ->assertSessionHas('error');

    expect(BerkasPersyaratan::whereKey($kk->id)->exists())->toBeTrue();
});

test('jenis berkas yang masih diminta sebuah jalur tidak bisa dihapus', function () {
    // Menghapusnya ikut mencabut persyaratan jalur itu diam-diam - aturan
    // pendaftaran berubah sebagai efek samping tombol Hapus, bukan keputusan.
    $baru = BerkasPersyaratan::create(['kode' => 'rapor_paud', 'nama' => 'Rapor PAUD', 'urutan' => 9]);
    DokumenWajibKategori::create([
        'gelombang_ppdb_id' => $this->gelombang->id,
        'kategori_siswa_id' => $this->reguler->id,
        'jenis_dokumen' => 'rapor_paud',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('super-admin.berkas-persyaratan.destroy', $baru))
        ->assertSessionHas('error');

    expect(BerkasPersyaratan::whereKey($baru->id)->exists())->toBeTrue()
        ->and(DokumenWajibKategori::where('jenis_dokumen', 'rapor_paud')->exists())->toBeTrue();
});

test('jenis berkas yang belum dipakai sama sekali boleh dihapus', function () {
    $baru = BerkasPersyaratan::create(['kode' => 'rapor_paud', 'nama' => 'Rapor PAUD', 'urutan' => 9]);

    $this->actingAs($this->admin)
        ->delete(route('super-admin.berkas-persyaratan.destroy', $baru))
        ->assertSessionHasNoErrors();

    expect(BerkasPersyaratan::whereKey($baru->id)->exists())->toBeFalse();
});

test('wali tidak bisa mengunggah jenis berkas yang sudah dinonaktifkan', function () {
    BerkasPersyaratan::where('kode', 'kartu_keluarga')->firstOrFail()->update(['status_aktif' => false]);

    $pendaftaran = PendaftaranPpdb::where('status', 'draft')->firstOrFail();

    $this->actingAs($pendaftaran->user)
        ->post(route('wali-murid.pendaftaran.unggah-berkas.store', $pendaftaran), [
            'jenis_dokumen' => 'kartu_keluarga',
            'berkas' => Illuminate\Http\UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('jenis_dokumen');
});

/*
| Komponen biaya - daftar global
*/

test('komponen biaya berlaku untuk semua gelombang, bukan milik satu gelombang', function () {
    // Kolom gelombang_ppdb_id sengaja tidak ada lagi di tabel ini. Kalau muncul
    // kembali, "Pembangunan" Gelombang 1 dan Gelombang 2 jadi dua baris yang
    // tidak saling kenal.
    expect(Illuminate\Support\Facades\Schema::hasColumn('komponen_biaya', 'gelombang_ppdb_id'))->toBeFalse();
});

test('komponen biaya bisa ditambah', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.komponen-biaya.store'), ['nama' => 'Buku Paket', 'urutan' => 5])
        ->assertSessionHasNoErrors();

    expect(KomponenBiaya::where('nama', 'Buku Paket')->exists())->toBeTrue();
});

test('komponen biaya yang belum punya nominal boleh dihapus', function () {
    $baru = KomponenBiaya::create(['nama' => 'Buku Paket', 'urutan' => 5]);

    $this->actingAs($this->admin)->delete(route('super-admin.komponen-biaya.destroy', $baru));

    expect(KomponenBiaya::find($baru->id))->toBeNull();
});

test('komponen biaya yang sudah punya nominal tidak bisa dihapus', function () {
    expect($this->seragam->tarif()->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('super-admin.komponen-biaya.destroy', $this->seragam))
        ->assertSessionHas('error');

    expect(KomponenBiaya::find($this->seragam->id))->not->toBeNull();
});

/*
| Gelombang - jadwal, nominal dasar, kuota & minimal bayar
*/

test('gelombang baru lahir tertutup dan nominal dasarnya menyebar ke semua jalur', function () {
    $jumlahJalur = KategoriSiswa::count();

    $this->actingAs($this->admin)
        ->post(route('super-admin.gelombang.store'), [
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Gelombang 2',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-12-31',
            'batas_waktu_pembayaran' => '2027-01-31',
            'minimal_pembayaran' => 3_500_000,
            'nominal_dasar' => [$this->seragam->id => 800_000],
        ])
        ->assertSessionHasNoErrors();

    $baru = GelombangPpdb::where('nama', 'Gelombang 2')->firstOrFail();

    // Membuka gelombang berarti menutup yang sedang berjalan - terlalu besar
    // untuk jadi efek samping tombol "Simpan".
    expect($baru->status_buka)->toBeFalse()
        ->and($this->gelombang->refresh()->status_buka)->toBeTrue()
        // Satu angka mengisi seluruh jalur sekaligus.
        ->and(TarifKategori::where('gelombang_ppdb_id', $baru->id)->where('komponen_biaya_id', $this->seragam->id)->count())
        ->toBe($jumlahJalur);
});

test('harga boleh berbeda antar gelombang untuk komponen yang sama', function () {
    $baru = GelombangPpdb::create([
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'nama' => 'Gelombang 2',
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2026-12-31',
        'status_buka' => false,
    ]);

    TarifKategori::create([
        'gelombang_ppdb_id' => $baru->id,
        'komponen_biaya_id' => $this->seragam->id,
        'kategori_siswa_id' => $this->reguler->id,
        'nominal' => 800_000,
    ]);

    $lama = TarifKategori::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('komponen_biaya_id', $this->seragam->id)
        ->where('kategori_siswa_id', $this->reguler->id)
        ->firstOrFail();

    expect($lama->nominal)->toBe(750_000);
});

test('membuka gelombang menutup gelombang lain', function () {
    $lain = GelombangPpdb::create([
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'nama' => 'Gelombang 2',
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2026-12-31',
        'status_buka' => false,
    ]);

    $this->actingAs($this->admin)->post(route('super-admin.gelombang.status', $lain), ['status_buka' => true]);

    expect($lain->refresh()->status_buka)->toBeTrue()
        ->and(GelombangPpdb::where('status_buka', true)->count())->toBe(1);
});

test('menutup gelombang tidak menyentuh pendaftaran di dalamnya', function () {
    $sebelum = $this->gelombang->pendaftaran()->pluck('status', 'id');

    expect($sebelum)->not->toBeEmpty();

    $this->actingAs($this->admin)->post(route('super-admin.gelombang.status', $this->gelombang), ['status_buka' => false]);

    expect($this->gelombang->pendaftaran()->pluck('status', 'id')->all())->toBe($sebelum->all());
});

/**
 * Jatuh tempo yang mendahului penutupan pendaftaran berarti ada pendaftar yang
 * tenggat bayarnya sudah lewat pada hari dia mendaftar.
 */
test('jatuh tempo pembayaran tidak boleh mendahului penutupan pendaftaran', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.gelombang.store'), [
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Gelombang Salah',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-12-31',
            'batas_waktu_pembayaran' => '2026-12-01',
            'nominal_dasar' => [],
        ])
        ->assertSessionHasErrors('batas_waktu_pembayaran');
});

test('kuota dan minimal bayar tersimpan per jalur lewat halaman gelombang', function () {
    $this->actingAs($this->admin)
        ->put(route('super-admin.gelombang.update', $this->gelombang), muatanGelombang($this->gelombang, [
            'kebijakan' => [
                $this->reguler->id => ['kuota' => 40, 'minimal_bayar' => null],
                $this->yatim->id => ['kuota' => 7, 'minimal_bayar' => 300_000],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(KebijakanKategori::untuk($this->gelombang->id, $this->reguler->id)->kuota)->toBe(40)
        ->and(KebijakanKategori::minimalBayarUntuk($this->gelombang->id, $this->yatim->id))->toBe(300_000);
});

/**
 * Aturan yang paling gampang salah dipahami: kuota kosong berarti TIDAK
 * DIBATASI, bukan nol. Kalau terbalik, seluruh pendaftaran ikut tertutup cuma
 * gara-gara data yang belum sempat diisi.
 */
test('kuota kosong berarti tidak dibatasi, kuota nol berarti tertutup', function () {
    $kirim = fn (mixed $kuota) => $this->actingAs($this->admin)->put(
        route('super-admin.gelombang.update', $this->gelombang),
        muatanGelombang($this->gelombang, [
            'kebijakan' => [$this->reguler->id => ['kuota' => $kuota, 'minimal_bayar' => null]],
        ])
    );

    $kirim(null);
    expect(KebijakanKategori::penuhUntuk($this->gelombang->id, $this->reguler->id))->toBeFalse();

    $kirim(0);
    expect(KebijakanKategori::penuhUntuk($this->gelombang->id, $this->reguler->id))->toBeTrue();
});

test('mengubah syarat berkas satu gelombang tidak menyentuh gelombang lain', function () {
    // INI SELURUH ALASAN dokumen_wajib_kategori dikunci gelombang x jalur.
    // Waktu syaratnya masih melekat pada jalur saja, dua hal terbukti rusak dan
    // dua-duanya diam: menambah syarat bikin anak yang SUDAH DITERIMA tercatat
    // kurang berkas, dan mencabut syarat bikin berkas yang telanjur diunggah
    // hilang dari layar staf walau filenya masih tersimpan.
    $lama = PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('kategori_siswa_id', $this->reguler->id)
        ->firstOrFail();

    $syaratLama = $lama->dokumenWajib();

    $gelombangBaru = GelombangPpdb::create([
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'nama' => 'Gelombang 2',
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2026-12-31',
        'batas_waktu_pembayaran' => '2027-01-31',
        'minimal_pembayaran' => 3_000_000,
        'status_buka' => false,
    ]);

    BerkasPersyaratan::create(['kode' => 'rapor_paud', 'nama' => 'Rapor PAUD', 'urutan' => 9]);

    $this->actingAs($this->admin)
        ->put(route('super-admin.gelombang.update', $gelombangBaru), muatanGelombang($gelombangBaru, [
            'dokumen' => [$this->reguler->id => ['kartu_keluarga', 'rapor_paud']],
        ]))
        ->assertSessionHasNoErrors();

    expect($gelombangBaru->refresh()->dokumenWajibUntuk($this->reguler->id))->toContain('rapor_paud')
        // Gelombang 1 tidak bergeser sedikit pun.
        ->and($lama->refresh()->dokumenWajib())->toBe($syaratLama)
        ->and($lama->dokumenWajib())->not->toContain('rapor_paud');
});

test('gelombang baru mewarisi syarat berkas dari gelombang sebelumnya', function () {
    // Bawaannya diwarisi, bukan dikosongkan: gelombang yang tidak meminta berkas
    // apa pun kelihatan seperti sistem rusak, bukan seperti keputusan sekolah.
    $sebelumnya = $this->gelombang->dokumenWajibUntuk($this->yatim->id);

    expect($sebelumnya)->toContain('surat_kematian_ayah');

    $this->actingAs($this->admin)
        ->post(route('super-admin.gelombang.store'), [
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Gelombang 2',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-12-31',
            'batas_waktu_pembayaran' => '2027-01-31',
            'minimal_pembayaran' => 3_000_000,
            'nominal_dasar' => [],
        ])
        ->assertSessionHasNoErrors();

    $baru = GelombangPpdb::where('nama', 'Gelombang 2')->firstOrFail();

    expect($baru->dokumenWajibUntuk($this->yatim->id))->toBe($sebelumnya);
});

test('mencabut syarat berkas tidak menghapus berkas yang sudah diunggah', function () {
    $lama = PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang->id)
        ->whereHas('dokumen', fn ($q) => $q->where('jenis_dokumen', 'pas_foto'))
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('super-admin.gelombang.update', $this->gelombang), muatanGelombang($this->gelombang, [
            'dokumen' => [$lama->kategori_siswa_id => ['kartu_keluarga']],
        ]))
        ->assertSessionHasNoErrors();

    // Barisnya tetap ada di dokumen_ppdb - filenya tidak ikut dibuang. Yang
    // hilang cuma tempatnya di checklist, dan itu memang yang diminta.
    expect(DokumenPpdb::where('pendaftaran_ppdb_id', $lama->id)->where('jenis_dokumen', 'pas_foto')->exists())->toBeTrue();
});

/*
| Jalur pendaftaran
*/

test('jalur bisa ditambah dan namanya bebas diubah', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.jalur.store'), [
            'nama' => 'Jalur Prestasi',
            'urutan' => 5,
            'deskripsi' => 'Calon peserta didik berprestasi di bidang tahfiz.',
        ])
        ->assertSessionHasNoErrors();

    expect(KategoriSiswa::where('nama', 'Jalur Prestasi')->exists())->toBeTrue();
});

/**
 * Dulu nama 'Anak Yatim' dicocokkan sebagai teks oleh dokumenWajib(), jadi
 * menggantinya mematikan aturannya diam-diam. Sekarang dokumennya data.
 */
test('mengganti nama jalur tidak merusak dokumen wajibnya', function () {
    $this->actingAs($this->admin)
        ->put(route('super-admin.jalur.update', $this->yatim), [
            'nama' => 'Yatim Piatu',
            'urutan' => $this->yatim->urutan,
            'deskripsi' => 'Keterangan baru.',
        ])
        ->assertSessionHasNoErrors();

    $pendaftaran = PendaftaranPpdb::where('kategori_siswa_id', $this->yatim->id)->first();

    expect($this->yatim->refresh()->nama)->toBe('Yatim Piatu');

    if ($pendaftaran !== null) {
        expect($pendaftaran->refresh()->dokumenWajib())->toContain('surat_kematian_ayah');
    }
});

/**
 * Bug yang ditemukan 8 September 2026: layar mencocokkan
 * 'Anak Guru/Tenaga Kependidikan' padahal jalurnya bernama 'Anak Guru', jadi
 * isian pendukungnya tidak pernah muncul dan staf memverifikasi klaim itu
 * tanpa data pendukung apa pun.
 */
test('jalur Anak Guru benar-benar meminta isian pendukungnya', function () {
    $anakGuru = KategoriSiswa::where('nama', 'Anak Guru')->firstOrFail();

    expect($anakGuru->pertanyaan_khusus)->not->toBeNull();
});

test('pertanyaan khusus jalur bebas ditulis, bukan pilih dari daftar tetap', function () {
    // Ini seluruh alasan field_pendukung diganti. Dulu isinya kunci dari
    // konstanta berisi dua pilihan, dan halaman formulir mencocokkan kuncinya
    // sebagai teks - jadi jalur baru yang ditambahkan Admin tidak akan pernah
    // bisa bertanya apa pun tanpa menambah kolom dan cabang if di kode.
    $this->actingAs($this->admin)
        ->put(route('super-admin.jalur.update', $this->reguler), [
            'nama' => $this->reguler->nama,
            'urutan' => $this->reguler->urutan,
            'deskripsi' => $this->reguler->deskripsi,
            'pertanyaan_khusus' => 'Nomor peserta lomba tahfiz yang pernah diikuti',
        ])
        ->assertSessionHasNoErrors();

    expect($this->reguler->refresh()->pertanyaan_khusus)->toBe('Nomor peserta lomba tahfiz yang pernah diikuti');
});

test('jawaban khusus wajib diisi kalau jalurnya bertanya', function () {
    // Pertanyaan yang boleh dilewati begitu saja tidak menolong staf
    // memverifikasi klaimnya - dia cuma jadi kolom kosong di layar verifikasi.
    $pendaftaran = PendaftaranPpdb::where('status', 'draft')->firstOrFail();

    KategoriSiswa::whereKey($pendaftaran->kategori_siswa_id)
        ->update(['pertanyaan_khusus' => 'Nama saudara yang bersekolah di sini']);

    $this->actingAs($pendaftaran->user)
        ->put(route('wali-murid.pendaftaran.update', $pendaftaran), [
            ...$pendaftaran->only([
                'kategori_siswa_id', 'nama_pendaftar', 'nik', 'tempat_lahir', 'jenis_kelamin',
                'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota_kabupaten', 'provinsi',
                'asal_paud_id', 'asal_paud_lainnya', 'sumber_informasi', 'sumber_informasi_lainnya',
            ]),
            'tanggal_lahir' => $pendaftaran->tanggal_lahir->format('Y-m-d'),
            'tanpa_paud' => $pendaftaran->asal_paud_id === null && $pendaftaran->asal_paud_lainnya === null,
            'wali_murid' => $pendaftaran->waliMurid->map(fn ($w) => $w->only(['nama', 'nik', 'hubungan', 'telepon']))->all(),
            'jawaban_khusus' => '',
        ])
        ->assertSessionHasErrors('jawaban_khusus');
});

test('mengubah pertanyaan jalur tidak mengubah arti jawaban yang sudah masuk', function () {
    // Pelajaran yang sama dengan tagihan dan berkas: jawaban tanpa pertanyaannya
    // bukan sekadar kurang jelas - dia bisa SALAH. "Kakak Budi" di bawah
    // pertanyaan "NIS saudara" akan diverifikasi staf sebagai kebenaran.
    $pendaftaran = PendaftaranPpdb::whereNotNull('jawaban_khusus')->firstOrFail();

    $pertanyaanAsli = $pendaftaran->pertanyaan_khusus;
    $jawabanAsli = $pendaftaran->jawaban_khusus;

    expect($pertanyaanAsli)->not->toBeNull();

    $this->actingAs($this->admin)
        ->put(route('super-admin.jalur.update', $pendaftaran->kategori_siswa_id), [
            'nama' => $pendaftaran->kategoriSiswa->nama,
            'urutan' => $pendaftaran->kategoriSiswa->urutan,
            'deskripsi' => $pendaftaran->kategoriSiswa->deskripsi,
            'pertanyaan_khusus' => 'NIS saudara yang bersekolah di sini',
        ])
        ->assertSessionHasNoErrors();

    $pendaftaran->refresh();

    expect($pendaftaran->pertanyaan_khusus)->toBe($pertanyaanAsli)
        ->and($pendaftaran->jawaban_khusus)->toBe($jawabanAsli);
});

test('jenis berkas yang tidak dikenal ditolak saat menyimpan gelombang', function () {
    $this->actingAs($this->admin)
        ->put(route('super-admin.gelombang.update', $this->gelombang), muatanGelombang($this->gelombang, [
            'dokumen' => [$this->reguler->id => ['ijazah_s3']],
        ]))
        ->assertSessionHasErrors('dokumen.'.$this->reguler->id.'.0');
});

test('daftar pilihan jalur ikut urutan yang diatur, bukan abjad', function () {
    // Tanpa kolom urutan, susunannya ikut abjad - dan Reguler, jalur yang
    // dipakai mayoritas pendaftar, terdampar di tengah cuma gara-gara huruf R.
    // Urutan yang tidak dipilih siapa pun bukan urutan yang benar.
    $wali = User::where('role', 'wali_murid')->firstOrFail();

    $this->reguler->update(['urutan' => 1]);
    $this->yatim->update(['urutan' => 2]);

    $this->actingAs($wali)
        ->get(route('wali-murid.pendaftaran.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kategoriSiswa', function ($daftar) {
                $nama = collect($daftar)->pluck('nama');

                return $nama->first() === 'Reguler' && $nama->search('Anak Yatim') === 1;
            })
            ->etc());
});

test('jalur baru bawaannya aktif', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.jalur.store'), ['nama' => 'Jalur Prestasi', 'urutan' => 5, 'deskripsi' => 'Berprestasi di bidang tahfiz.'])
        ->assertSessionHasNoErrors();

    expect(KategoriSiswa::where('nama', 'Jalur Prestasi')->firstOrFail()->status_aktif)->toBeTrue();
});

test('jalur non-aktif tidak ditawarkan lagi ke pendaftar baru', function () {
    $this->yatim->update(['status_aktif' => false]);

    $wali = User::where('role', 'wali_murid')->firstOrFail();

    $this->actingAs($wali)
        ->get(route('wali-murid.pendaftaran.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kategoriSiswa', fn ($daftar) => collect($daftar)->pluck('nama')->doesntContain('Anak Yatim'))
            ->etc());
});

test('pendaftaran lama tetap bisa disimpan walau jalurnya sudah dimatikan', function () {
    // Request yang sama dipakai simpan DAN ubah. Tanpa pengecualian untuk jalur
    // yang sedang dipakai, wali yang jalurnya dimatikan sekolah belakangan tidak
    // bisa menyimpan formulirnya lagi sama sekali - bahkan cuma untuk
    // membetulkan ejaan namanya.
    $pendaftaran = PendaftaranPpdb::where('status', 'draft')->firstOrFail();

    KategoriSiswa::whereKey($pendaftaran->kategori_siswa_id)->update(['status_aktif' => false]);

    $this->actingAs($pendaftaran->user)
        ->put(route('wali-murid.pendaftaran.update', $pendaftaran), [
            ...$pendaftaran->only([
                'kategori_siswa_id', 'nama_pendaftar', 'nik', 'tempat_lahir', 'jenis_kelamin',
                'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota_kabupaten', 'provinsi',
                'asal_paud_id', 'asal_paud_lainnya', 'sumber_informasi', 'sumber_informasi_lainnya',
            ]),
            'nama_pendaftar' => 'Nama Dibetulkan',
            'tanggal_lahir' => $pendaftaran->tanggal_lahir->format('Y-m-d'),
            'tanpa_paud' => $pendaftaran->asal_paud_id === null && $pendaftaran->asal_paud_lainnya === null,
            'wali_murid' => $pendaftaran->waliMurid
                ->map(fn ($w) => $w->only(['nama', 'nik', 'hubungan', 'telepon']))
                ->all(),
        ])
        ->assertSessionHasNoErrors();

    expect($pendaftaran->refresh()->nama_pendaftar)->toBe('Nama Dibetulkan');
});

test('jalur yang belum dipakai boleh dihapus', function () {
    $baru = KategoriSiswa::create(['nama' => 'Jalur Prestasi', 'deskripsi' => 'Belum dipakai siapa pun.']);

    $this->actingAs($this->admin)->delete(route('super-admin.jalur.destroy', $baru));

    expect(KategoriSiswa::find($baru->id))->toBeNull();
});

/**
 * Riwayat, berkas, dan pembayaran pendaftar menggantung pada jalurnya. Foreign
 * key pendaftaran_ppdb.kategori_siswa_id sengaja TIDAK cascade, jadi ini
 * berlapis dua - pemeriksaan controller, lalu database.
 */
test('jalur yang sudah dipakai pendaftar tidak bisa dihapus', function () {
    expect($this->reguler->pendaftaran()->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('super-admin.jalur.destroy', $this->reguler))
        ->assertSessionHas('error');

    expect(KategoriSiswa::find($this->reguler->id))->not->toBeNull();
});

/*
| Tarif - per gelombang x jalur x komponen
*/

test('nominal tersimpan untuk gelombang yang dipilih saja', function () {
    $this->actingAs($this->admin)
        ->put(route('super-admin.jalur.tarif', $this->yatim), [
            'gelombang_ppdb_id' => $this->gelombang->id,
            'tarif' => [$this->seragam->id => 500_000],
        ])
        ->assertSessionHasNoErrors();

    $tarif = TarifKategori::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('komponen_biaya_id', $this->seragam->id)
        ->where('kategori_siswa_id', $this->yatim->id)
        ->firstOrFail();

    expect($tarif->nominal)->toBe(500_000)
        // Jalur lain tidak ikut berubah.
        ->and(TarifKategori::where('gelombang_ppdb_id', $this->gelombang->id)
            ->where('komponen_biaya_id', $this->seragam->id)
            ->where('kategori_siswa_id', $this->reguler->id)
            ->value('nominal'))->toBe(750_000);
});

/**
 * Nominal 0 SAH - artinya jalur dibebaskan dari pos itu dan tetap muncul di
 * rincian sebagai Rp0. Yang berarti "belum diatur" adalah barisnya yang tidak
 * ada, dan pos itu tidak muncul di tagihan sama sekali.
 */
test('nol berarti dibebaskan, kosong berarti belum diatur', function () {
    $kirim = fn (mixed $nominal) => $this->actingAs($this->admin)->put(route('super-admin.jalur.tarif', $this->yatim), [
        'gelombang_ppdb_id' => $this->gelombang->id,
        'tarif' => [$this->seragam->id => $nominal],
    ]);

    $kunci = [
        'gelombang_ppdb_id' => $this->gelombang->id,
        'komponen_biaya_id' => $this->seragam->id,
        'kategori_siswa_id' => $this->yatim->id,
    ];

    $kirim(0);
    expect(TarifKategori::where($kunci)->value('nominal'))->toBe(0);

    $kirim(null);
    expect(TarifKategori::where($kunci)->exists())->toBeFalse();
});

/*
| Integritas finansial - bagian paling penting di berkas ini.
|
| Tagihan di-snapshot ke tagihan_item dan minimal bayarnya dibekukan ke
| pendaftaran_ppdb.minimal_bayar saat wali pertama membuka halaman Pembayaran.
| Kalau angka orang lama ikut berubah tiap admin menyetel tarif, status
| 'diterima' yang sudah sah bisa tercabut sendiri.
*/

test('komponen non-aktif berhenti ikut ke tagihan baru', function () {
    $totalPenuh = TarifKategori::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('kategori_siswa_id', $this->reguler->id)
        ->sum('nominal');

    $hargaSeragam = TarifKategori::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('kategori_siswa_id', $this->reguler->id)
        ->where('komponen_biaya_id', $this->seragam->id)
        ->value('nominal');

    $this->seragam->update(['status_aktif' => false]);

    // Pendaftaran yang tagihannya BELUM terbit - dia yang merasakan akibatnya.
    $baru = PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('kategori_siswa_id', $this->reguler->id)
        ->whereDoesntHave('tagihanItem')
        ->firstOrFail();

    $baru->terbitkanTagihan();

    expect($baru->tagihanItem()->sum('nominal'))->toBe((int) ($totalPenuh - $hargaSeragam))
        ->and($baru->tagihanItem()->where('nama_komponen', 'Seragam')->exists())->toBeFalse()
        // Baris tarifnya TIDAK ikut hilang - dinyalakan lagi, harganya utuh.
        ->and(TarifKategori::where('komponen_biaya_id', $this->seragam->id)->count())->toBeGreaterThan(0);
});

test('menonaktifkan komponen tidak menyentuh tagihan yang sudah terbit', function () {
    // Ini seluruh alasan status_aktif ada, menggantikan tombol hapus: pos yang
    // sudah tidak ditagih sekolah dimatikan, dan wali yang tagihannya terlanjur
    // terbit tetap memegang rincian beserta total yang sama persis. Kalau ini
    // pecah, keluarga yang sudah mencicil tiba-tiba punya kewajiban berbeda.
    $pendaftaran = PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang->id)
        ->whereNotNull('minimal_bayar')
        ->whereHas('tagihanItem')
        ->firstOrFail();

    $totalSebelum = $pendaftaran->tagihanItem()->sum('nominal');
    $barisSebelum = $pendaftaran->tagihanItem()->count();
    $minimalSebelum = $pendaftaran->minimal_bayar;

    $this->seragam->update(['status_aktif' => false]);

    $pendaftaran->refresh();

    expect($pendaftaran->tagihanItem()->sum('nominal'))->toBe($totalSebelum)
        ->and($pendaftaran->tagihanItem()->count())->toBe($barisSebelum)
        ->and($pendaftaran->minimal_bayar)->toBe($minimalSebelum)
        ->and($pendaftaran->tagihanItem()->where('nama_komponen', 'Seragam')->exists())->toBeTrue();
});

test('komponen non-aktif bisa dinyalakan lagi lewat modal ubah', function () {
    $this->seragam->update(['status_aktif' => false]);

    $this->actingAs($this->admin)
        ->put(route('super-admin.komponen-biaya.update', $this->seragam), [
            'nama' => $this->seragam->nama,
            'urutan' => $this->seragam->urutan,
            'keterangan' => $this->seragam->keterangan,
            'status_aktif' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($this->seragam->refresh()->status_aktif)->toBeTrue();
});

test('komponen non-aktif tidak ditawarkan lagi di layar pengisian nominal', function () {
    // Dulu diuji lewat layar Ubah Jalur. Layar itu diparkir sejak formulir jalur
    // jadi modal, jadi yang diuji sekarang layar buat gelombang - satu-satunya
    // tempat nominal masih bisa diisi, dan penyaringnya sama.
    $this->seragam->update(['status_aktif' => false]);

    $this->actingAs($this->admin)
        ->get(route('super-admin.gelombang.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('komponen', fn ($komponen) => collect($komponen)->pluck('nama')->doesntContain('Seragam'))
            ->etc());
});

test('tambah dan ubah jalur tidak punya halaman sendiri', function (string $rute) {
    // Modal menggantikan dua halaman formulir, sama seperti tahun ajaran,
    // komponen biaya, dan berkas persyaratan.
    expect(Illuminate\Support\Facades\Route::has($rute))->toBeFalse();
})->with(['super-admin.jalur.create', 'super-admin.jalur.edit']);

test('komponen biaya baru bawaannya aktif', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.komponen-biaya.store'), ['nama' => 'Kegiatan Tahunan', 'urutan' => 9])
        ->assertSessionHasNoErrors();

    expect(KomponenBiaya::where('nama', 'Kegiatan Tahunan')->firstOrFail()->status_aktif)->toBeTrue();
});

test('tambah dan ubah komponen biaya tidak punya halaman sendiri', function (string $rute) {
    expect(Illuminate\Support\Facades\Route::has($rute))->toBeFalse();
})->with(['super-admin.komponen-biaya.create', 'super-admin.komponen-biaya.edit']);

test('mengubah tarif tidak mengubah tagihan yang sudah terbit', function () {
    $pendaftaran = PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang->id)
        ->whereNotNull('minimal_bayar')
        ->firstOrFail();

    $totalSebelum = $pendaftaran->tagihanItem()->sum('nominal');
    $minimalSebelum = $pendaftaran->minimal_bayar;

    $this->actingAs($this->admin)
        ->put(route('super-admin.jalur.tarif', $pendaftaran->kategori_siswa_id), [
            'gelombang_ppdb_id' => $this->gelombang->id,
            'tarif' => [$this->seragam->id => 99_000_000],
        ])
        ->assertSessionHasNoErrors();

    $pendaftaran->refresh();

    expect($pendaftaran->tagihanItem()->sum('nominal'))->toBe($totalSebelum)
        ->and($pendaftaran->minimal_bayar)->toBe($minimalSebelum);
});

test('status diterima tidak tercabut gara-gara konfigurasi diubah', function () {
    $diterima = PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang->id)
        ->where('status', 'diterima')
        ->firstOrFail();

    $this->actingAs($this->admin)->put(route('super-admin.jalur.tarif', $diterima->kategori_siswa_id), [
        'gelombang_ppdb_id' => $this->gelombang->id,
        'tarif' => [$this->seragam->id => 99_000_000],
    ]);

    expect($diterima->refresh()->status)->toBe('diterima');
});
