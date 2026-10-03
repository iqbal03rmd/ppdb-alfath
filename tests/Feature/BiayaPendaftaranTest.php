<?php

use App\Models\AsalPaud;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KebijakanKategori;
use App\Models\PembayaranPendaftaranAwal;
use App\Models\PendaftaranPpdb;
use App\Models\PengaturanSistem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed();
    Storage::fake('public');

    PengaturanSistem::tersimpan()->update([
        'nama_bank' => 'BSI',
        'nomor_rekening' => '1234567890',
        'nama_pemilik_rekening' => 'Yayasan Al-Fath',
    ]);

    $this->gelombang = GelombangPpdb::menerimaPendaftar()->firstOrFail();

    $this->wali = User::factory()->create([
        'role' => 'wali_murid',
        'status_aktif' => true,
    ]);
    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();
});

function payloadAnak(array $tambahan = []): array
{
    return array_merge([
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Reguler')->value('id'),
        'nama_pendaftar' => 'Anak Bertiket',
        'nik' => '1471010101200097',
        'tanggal_lahir' => '2020-05-05',
        'tempat_lahir' => 'Pekanbaru',
        'jenis_kelamin' => 'laki-laki',
        'alamat' => 'Jl. Uji',
        'rt' => '001',
        'rw' => '002',
        'kelurahan' => 'Sukajadi',
        'kecamatan' => 'Sukajadi',
        'kota_kabupaten' => 'Pekanbaru',
        'provinsi' => 'Riau',
        'tanpa_paud' => false,
        'asal_paud_id' => AsalPaud::value('id'),
        'wali_murid' => [[
            'nama' => 'Wali Uji',
            'nik' => '1471010101800097',
            'hubungan' => 'Ayah',
            'telepon' => '081200000097',
        ]],
    ], $tambahan);
}

function reservasiJalurUji(User $wali): PembayaranPendaftaranAwal
{
    test()->actingAs($wali)->post(route('wali-murid.biaya-pendaftaran.reservasi'), [
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Reguler')->value('id'),
    ])->assertRedirect(route('wali-murid.biaya-pendaftaran.show'))->assertSessionHasNoErrors();

    return $wali->pembayaranPendaftaranAwal()->latest('id')->firstOrFail();
}

function buktiReservasiUji(PembayaranPendaftaranAwal $reservasi): array
{
    return [
        'reservasi_id' => $reservasi->id,
        'nominal_transfer' => $reservasi->nominal_tagihan,
        'tanggal_transfer' => today()->toDateString(),
        'bukti_transfer' => UploadedFile::fake()->image('bukti-reservasi.jpg'),
    ];
}

test('bukti yang gagal divalidasi belum masuk antrean staf', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), [
        'reservasi_id' => $reservasi->id,
        'nominal_transfer' => $reservasi->nominal_tagihan,
        'tanggal_transfer' => today()->toDateString(),
    ])->assertSessionHasErrors('bukti_transfer');

    expect($reservasi->refresh()->status)->toBe('menunggu_pembayaran')
        ->and($reservasi->bukti_transfer)->toBeNull();
    $this->actingAs($this->staf)->get(route('staf-ppdb.verifikasi-biaya-pendaftaran.index'))
        ->assertInertia(fn ($page) => $page->has('antrian', 0)->etc());
});

test('membuka halaman belum menahan slot dan tidak menampilkan rekening sebelum memilih jalur', function () {
    $this->actingAs($this->wali)->get(route('wali-murid.biaya-pendaftaran.show'))
        ->assertInertia(fn ($page) => $page->where('reservasi', null)->where('informasiPembayaran', null)
            ->where('bisaReservasi', true)->where('bisaMengirim', false)->etc());
    expect($this->wali->pembayaranPendaftaranAwal()->count())->toBe(0);
});

test('reservasi menahan satu slot selama 24 jam dan tidak dapat ditumpuk atau diperpanjang dengan klik ulang', function () {
    $jalur = KategoriSiswa::where('nama', 'Reguler')->firstOrFail();
    $awal = KebijakanKategori::terpakaiUntuk($this->gelombang->id, $jalur->id);
    $reservasi = reservasiJalurUji($this->wali);
    expect($reservasi->status)->toBe('menunggu_pembayaran')
        ->and($reservasi->reservasi_berakhir_pada->equalTo($reservasi->created_at->copy()->addDay()))->toBeTrue()
        ->and(KebijakanKategori::terpakaiUntuk($this->gelombang->id, $jalur->id))->toBe($awal + 1);
    $this->travel(1)->hours();
    reservasiJalurUji($this->wali);
    expect($this->wali->pembayaranPendaftaranAwal()->count())->toBe(1)
        ->and($reservasi->refresh()->reservasi_berakhir_pada->equalTo($reservasi->created_at->copy()->addDay()))->toBeTrue();
    $this->actingAs($this->wali)->get(route('wali-murid.dashboard'))
        ->assertInertia(fn ($page) => $page->where('tiketPendaftaran.status', 'menunggu_pembayaran')->etc());
    $this->actingAs($this->wali)->get(route('wali-murid.pendaftaran.create'))
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));
});

test('jalur penuh dicegah sebelum transfer termasuk jika pilihan di layar sudah kedaluwarsa', function () {
    $jalur = KategoriSiswa::where('nama', 'Reguler')->firstOrFail();
    $kebijakan = KebijakanKategori::untuk($this->gelombang->id, $jalur->id);
    $kebijakan->update(['kuota' => $kebijakan->terpakai() + 1]);
    reservasiJalurUji($this->wali);
    $lain = User::factory()->create(['role' => 'wali_murid', 'status_aktif' => true]);
    $this->actingAs($lain)->post(route('wali-murid.biaya-pendaftaran.reservasi'), ['kategori_siswa_id' => $jalur->id])
        ->assertSessionHasErrors('kategori_siswa_id');
    expect($lain->pembayaranPendaftaranAwal()->count())->toBe(0);
});

test('reservasi kedaluwarsa melepaskan slot tanpa cron dan bukti terlambat tidak diterima', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $awal = KebijakanKategori::terpakaiUntuk($this->gelombang->id, $reservasi->kategori_siswa_id);
    $this->travelTo($reservasi->reservasi_berakhir_pada);
    expect(KebijakanKategori::terpakaiUntuk($this->gelombang->id, $reservasi->kategori_siswa_id))->toBe($awal - 1);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))
        ->assertSessionHasErrors('reservasi_id');
    expect(Storage::disk('public')->allFiles('bukti-biaya-pendaftaran'))->toBe([]);
    $this->actingAs($this->wali)->get(route('wali-murid.biaya-pendaftaran.show'))
        ->assertInertia(fn ($page) => $page->where('reservasi', null)->where('bisaReservasi', true)->etc());
    $this->actingAs($this->wali)->get(route('wali-murid.dashboard'))
        ->assertInertia(fn ($page) => $page->where('tiketPendaftaran.status', 'belum_bayar')->etc());
    $baru = reservasiJalurUji($this->wali);
    expect($baru->id)->not->toBe($reservasi->id);
});

test('slot bertahan saat pemeriksaan hingga draft dan submit tanpa dihitung dua kali', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $kebijakan = KebijakanKategori::untuk($this->gelombang->id, $reservasi->kategori_siswa_id);
    $terpakai = $kebijakan->terpakai();
    $kebijakan->update(['kuota' => $terpakai]);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))
        ->assertSessionHasNoErrors();
    $this->travel(25)->hours();
    expect($kebijakan->terpakai())->toBe($terpakai);
    $this->actingAs($this->staf)->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', $reservasi))->assertRedirect();
    $this->actingAs($this->wali)->get(route('wali-murid.pendaftaran.create'))
        ->assertInertia(fn ($page) => $page->where('jalurReservasi', $reservasi->kategori_siswa_id)
            ->where('kategoriSiswa', fn ($daftar) => ! collect($daftar)->firstWhere('id', $reservasi->kategori_siswa_id)['penuh'])->etc());
    $this->actingAs($this->wali)->post(route('wali-murid.pendaftaran.store'), payloadAnak())->assertSessionHasNoErrors();
    $pendaftaran = $reservasi->refresh()->pendaftaran;
    expect($pendaftaran->status)->toBe('draft')->and($kebijakan->terpakai())->toBe($terpakai);
    $this->actingAs($this->wali)->put(route('wali-murid.pendaftaran.update', $pendaftaran), payloadAnak())
        ->assertSessionHasNoErrors();
    foreach ($pendaftaran->dokumenWajib() as $jenis) {
        $pendaftaran->dokumen()->create(['jenis_dokumen' => $jenis, 'berkas' => "uji/{$jenis}.jpg"]);
    }
    $this->actingAs($this->wali)->post(route('wali-murid.pendaftaran.unggah-berkas.submit', $pendaftaran))
        ->assertRedirect(route('wali-murid.pendaftaran.index', ['expand' => $pendaftaran->id]));
    expect($pendaftaran->refresh()->status)->toBe('diajukan')->and($kebijakan->terpakai())->toBe($terpakai);
    $pendaftaran->update(['status' => 'ditolak']);
    expect($kebijakan->terpakai())->toBe($terpakai - 1);
    // Tiket yang sudah dipakai tidak menghalangi reservasi anak berikutnya.
    expect(reservasiJalurUji($this->wali)->id)->not->toBe($reservasi->id);
});

test('bukti ditolak dapat diganti tanpa kehilangan kursi maupun menghapus riwayat', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $terpakai = KebijakanKategori::terpakaiUntuk($this->gelombang->id, $reservasi->kategori_siswa_id);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))->assertSessionHasNoErrors();
    $this->actingAs($this->staf)->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.tolak', $reservasi), [
        'catatan_verifikasi' => 'Foto bukti buram, mohon kirim ulang.',
    ])->assertRedirect();
    $this->travel(25)->hours();
    expect(KebijakanKategori::terpakaiUntuk($this->gelombang->id, $reservasi->kategori_siswa_id))->toBe($terpakai);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))->assertSessionHasNoErrors();
    expect($reservasi->refresh()->status)->toBe('ditolak')->and($reservasi->reservasi_berakhir_pada)->toBeNull()
        ->and(KebijakanKategori::terpakaiUntuk($this->gelombang->id, $reservasi->kategori_siswa_id))->toBe($terpakai);
    $baru = $this->wali->pembayaranPendaftaranAwal()->latest('id')->firstOrFail();
    expect($baru->status)->toBe('menunggu_verifikasi');
    $this->actingAs($this->wali)->get(route('wali-murid.biaya-pendaftaran.show'))
        ->assertInertia(fn ($page) => $page->has('riwayat', 2)->where('reservasi.id', $baru->id)->etc());
});

test('reservasi milik wali lain tidak dapat dipakai dan jalur formulir harus sesuai tiket', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $lain = User::factory()->create(['role' => 'wali_murid', 'status_aktif' => true]);
    $this->actingAs($lain)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))
        ->assertSessionHasErrors('reservasi_id');
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))->assertSessionHasNoErrors();
    $this->actingAs($this->staf)->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', $reservasi))->assertRedirect();
    $this->actingAs($this->wali)->post(route('wali-murid.pendaftaran.store'), payloadAnak([
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Saudara')->value('id'),
        'jawaban_khusus' => 'Saudara di kelas 3',
    ]))->assertSessionHasErrors('kategori_siswa_id');
    expect($reservasi->refresh()->digunakan_pada)->toBeNull();
});

test('reservasi tetap memakai nominal saat dipesan dan jalur yang sudah ditahan boleh diselesaikan meski dinonaktifkan', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $this->gelombang->update(['biaya_pendaftaran' => 200000]);
    $reservasi->kategoriSiswa->update(['status_aktif' => false]);
    $this->actingAs($this->wali)->get(route('wali-murid.biaya-pendaftaran.show'))
        ->assertInertia(fn ($page) => $page->where('biaya', 125000)->etc());
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))->assertSessionHasNoErrors();
    $this->actingAs($this->staf)->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', $reservasi))->assertRedirect();
    $this->actingAs($this->wali)->post(route('wali-murid.pendaftaran.store'), payloadAnak())->assertSessionHasNoErrors();
    expect($reservasi->refresh()->digunakan_pada)->not->toBeNull();
});

test('reservasi tidak memperpanjang gelombang dan waktu bayar dibatasi tanggal penutupan', function () {
    $this->gelombang->update(['tanggal_selesai' => today()]);
    $reservasi = reservasiJalurUji($this->wali);
    expect($reservasi->reservasi_berakhir_pada->toDateTimeString())->toBe(today()->endOfDay()->toDateTimeString());
    $this->gelombang->tutup();
    expect(PembayaranPendaftaranAwal::menahanKursi()->whereKey($reservasi->id)->exists())->toBeFalse();
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'))->assertSessionHas('error');
});

test('jalur nonaktif tidak dapat dipesan dan jalur dengan reservasi tidak dapat dihapus', function () {
    $jalur = KategoriSiswa::create(['nama' => 'Jalur Reservasi Uji', 'deskripsi' => 'Untuk pengujian', 'status_aktif' => false]);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.reservasi'), ['kategori_siswa_id' => $jalur->id])
        ->assertSessionHasErrors('kategori_siswa_id');
    $jalur->update(['status_aktif' => true]);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.reservasi'), ['kategori_siswa_id' => $jalur->id])
        ->assertSessionHasNoErrors();
    expect($jalur->bisaDihapus())->toBeFalse();
    $admin = User::where('role', 'super_admin')->firstOrFail();
    $this->actingAs($admin)->delete(route('super-admin.jalur.destroy', $jalur))
        ->assertRedirect()->assertSessionHas('error');
    expect($jalur->fresh())->not->toBeNull();
});

test('mengubah jalur draft memindahkan slot tanpa menggandakan reservasi', function () {
    $reservasi = reservasiJalurUji($this->wali);
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.store'), buktiReservasiUji($reservasi))->assertSessionHasNoErrors();
    $this->actingAs($this->staf)->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', $reservasi))->assertRedirect();
    $this->actingAs($this->wali)->post(route('wali-murid.pendaftaran.store'), payloadAnak())->assertSessionHasNoErrors();
    $pendaftaran = $reservasi->refresh()->pendaftaran;
    $asal = KebijakanKategori::untuk($this->gelombang->id, $reservasi->kategori_siswa_id);
    $tujuan = KebijakanKategori::untuk($this->gelombang->id, KategoriSiswa::where('nama', 'Saudara')->value('id'));
    $jumlahAsal = $asal->terpakai();
    $jumlahTujuan = $tujuan->terpakai();
    $payload = payloadAnak(['kategori_siswa_id' => $tujuan->kategori_siswa_id, 'jawaban_khusus' => 'Saudara di kelas 3']);
    $tujuan->update(['kuota' => $jumlahTujuan]);
    $this->actingAs($this->wali)->put(route('wali-murid.pendaftaran.update', $pendaftaran), $payload)
        ->assertSessionHasErrors('kategori_siswa_id');
    expect($asal->terpakai())->toBe($jumlahAsal);
    $tujuan->update(['kuota' => $jumlahTujuan + 1]);
    $this->actingAs($this->wali)->put(route('wali-murid.pendaftaran.update', $pendaftaran), $payload)
        ->assertSessionHasNoErrors();
    expect($asal->terpakai())->toBe($jumlahAsal - 1)->and($tujuan->terpakai())->toBe($jumlahTujuan + 1)
        ->and($pendaftaran->refresh()->memilikiReservasiKursi())->toBeTrue();
});

test('wali tanpa tiket diarahkan membayar dan pendaftaran lama tetap bisa dibuka', function () {
    $lama = PendaftaranPpdb::firstOrFail();
    $lama->update(['user_id' => $this->wali->id]);

    $this->actingAs($this->wali)
        ->get(route('wali-murid.pendaftaran.create'))
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));

    $this->actingAs($this->wali)
        ->get(route('wali-murid.pendaftaran.show', $lama))
        ->assertOk();
});

test('wali dapat mengirim satu bukti yang menunggu verifikasi', function () {
    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.reservasi'), [
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Reguler')->value('id'),
    ])->assertSessionHasNoErrors();
    $reservasiId = $this->wali->pembayaranPendaftaranAwal()->latest('id')->value('id');
    $this->actingAs($this->wali)
        ->post(route('wali-murid.biaya-pendaftaran.store'), [
            'reservasi_id' => $reservasiId,
            'nominal_transfer' => 125000,
            'tanggal_transfer' => today()->format('Y-m-d'),
            'bukti_transfer' => UploadedFile::fake()->image('bukti.jpg'),
        ])
        ->assertRedirect(route('wali-murid.dashboard'));

    $pembayaran = PembayaranPendaftaranAwal::where('user_id', $this->wali->id)->firstOrFail();

    expect($pembayaran->status)->toBe('menunggu_verifikasi')
        ->and($pembayaran->nominal_tagihan)->toBe(125000);

    $this->actingAs($this->wali)->get(route('wali-murid.dashboard'))
        ->assertInertia(fn ($page) => $page->where('tiketPendaftaran.status', 'menunggu_verifikasi')->etc());
    $this->actingAs($this->staf)->get(route('staf-ppdb.verifikasi-biaya-pendaftaran.index'))
        ->assertInertia(fn ($page) => $page->has('antrian', 1)->etc());

    $this->actingAs($this->wali)
        ->post(route('wali-murid.biaya-pendaftaran.store'), [
            'nominal_transfer' => 125000,
            'tanggal_transfer' => today()->format('Y-m-d'),
            'bukti_transfer' => UploadedFile::fake()->image('bukti-lagi.jpg'),
            'reservasi_id' => $reservasiId,
        ])
        ->assertForbidden();
});

test('satu pembayaran yang disahkan hanya dapat membuat satu pendaftaran anak', function () {
    $pembayaran = PembayaranPendaftaranAwal::create([
        'user_id' => $this->wali->id,
        'gelombang_ppdb_id' => $this->gelombang->id,
        'nominal_tagihan' => 125000,
        'nominal_transfer' => 125000,
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'bukti/awal.jpg',
        'status' => 'menunggu_verifikasi',
    ]);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', $pembayaran))
        ->assertRedirect(route('staf-ppdb.verifikasi-biaya-pendaftaran.index'));

    $this->actingAs($this->wali)
        ->get(route('wali-murid.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('tiketPendaftaran.status', 'siap_digunakan')
            ->where('tiketPendaftaran.boleh_mendaftar', true)
            ->etc());

    $this->actingAs($this->wali)
        ->get(route('wali-murid.biaya-pendaftaran.show'))
        ->assertOk();

    expect($pembayaran->refresh()->digunakan_pada)->toBeNull();

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), payloadAnak())
        ->assertSessionHasNoErrors();

    $pendaftaran = PendaftaranPpdb::where('user_id', $this->wali->id)->where('nik', '1471010101200097')->firstOrFail();
    $pembayaran->refresh();

    expect($pembayaran->pendaftaran_ppdb_id)->toBe($pendaftaran->id)
        ->and($pembayaran->digunakan_pada)->not->toBeNull();

    // Membuka halaman status tidak menghabiskan tiket. Yang menghilangkan
    // penanda status baru adalah tiket benar-benar dipakai untuk menyimpan
    // formulir anak, lalu tombol kembali menjadi "+ Daftarkan Anak".
    $this->actingAs($this->wali)
        ->get(route('wali-murid.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('tiketPendaftaran.status', 'belum_bayar')
            ->where('tiketPendaftaran.boleh_mendaftar', false)
            ->etc());

    $this->actingAs($this->wali)
        ->post(route('wali-murid.pendaftaran.store'), payloadAnak([
            'nama_pendaftar' => 'Anak Kedua',
            'nik' => '1471010101200098',
        ]))
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));
});

test('pengesahan yang sudah dipakai tidak dapat dibatalkan', function () {
    $pendaftaran = PendaftaranPpdb::firstOrFail();
    $pendaftaran->update(['user_id' => $this->wali->id]);
    $pembayaran = PembayaranPendaftaranAwal::create([
        'user_id' => $this->wali->id,
        'gelombang_ppdb_id' => $pendaftaran->gelombang_ppdb_id,
        'pendaftaran_ppdb_id' => $pendaftaran->id,
        'nominal_tagihan' => 125000,
        'nominal_transfer' => 125000,
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'bukti/awal.jpg',
        'status' => 'terverifikasi',
        'digunakan_pada' => now(),
    ]);

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.tolak', $pembayaran), [
            'catatan_verifikasi' => 'Bukti ternyata tidak dapat dicocokkan.',
        ])
        ->assertForbidden();
});

test('staf melihat antrian dan tidak dapat mengesahkan pembayaran yang kurang', function () {
    $pembayaran = PembayaranPendaftaranAwal::create([
        'user_id' => $this->wali->id,
        'gelombang_ppdb_id' => $this->gelombang->id,
        'nominal_tagihan' => 125000,
        'nominal_transfer' => 100000,
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'bukti/kurang.jpg',
        'status' => 'menunggu_verifikasi',
    ]);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-biaya-pendaftaran.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staf-ppdb/verifikasi-biaya-pendaftaran')
            ->has('antrian', 1));

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.verifikasi-biaya-pendaftaran.show', $pembayaran))
        ->assertOk();

    $this->actingAs($this->staf)
        ->post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', $pembayaran))
        ->assertStatus(422);

    expect($pembayaran->refresh()->status)->toBe('menunggu_verifikasi');
});

test('biaya dan tiket pendaftaran hanya berlaku untuk gelombangnya', function () {
    PembayaranPendaftaranAwal::create([
        'user_id' => $this->wali->id,
        'gelombang_ppdb_id' => $this->gelombang->id,
        'nominal_tagihan' => 125000,
        'nominal_transfer' => 125000,
        'tanggal_transfer' => today(),
        'bukti_transfer' => 'bukti/gelombang-lama.jpg',
        'status' => 'terverifikasi',
        'diverifikasi_pada' => now(),
    ]);

    $gelombangBaru = GelombangPpdb::create([
        'tahun_ajaran_id' => $this->gelombang->tahun_ajaran_id,
        'nama' => 'Gelombang 2',
        'tanggal_mulai' => today()->toDateString(),
        'tanggal_selesai' => today()->addMonth()->toDateString(),
        'batas_waktu_pembayaran' => today()->addMonths(2)->toDateString(),
        'biaya_pendaftaran' => 175000,
        'status_buka' => false,
    ]);
    $gelombangBaru->buka();

    expect($gelombangBaru->refresh()->sedangMenerimaPendaftar())->toBeTrue()
        ->and(GelombangPpdb::menerimaPendaftar()->pluck('nama')->all())->toBe(['Gelombang 2']);

    $this->actingAs($this->wali)
        ->get(route('wali-murid.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('gelombangDibuka.nama', 'Gelombang 2')
            ->where('tiketPendaftaran.status', 'belum_bayar')
            ->etc());

    $this->actingAs($this->wali)
        ->get(route('wali-murid.biaya-pendaftaran.show'))
        ->assertInertia(fn ($page) => $page
            ->where('gelombang.nama', 'Gelombang 2')
            ->where('biaya', 175000)
            ->etc());

    $this->actingAs($this->wali)->post(route('wali-murid.biaya-pendaftaran.reservasi'), [
        'kategori_siswa_id' => KategoriSiswa::where('nama', 'Reguler')->value('id'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->wali)
        ->post(route('wali-murid.biaya-pendaftaran.store'), [
            'reservasi_id' => $this->wali->pembayaranPendaftaranAwal()->latest('id')->value('id'),
            'nominal_transfer' => 175000,
            'tanggal_transfer' => today()->format('Y-m-d'),
            'bukti_transfer' => UploadedFile::fake()->image('gelombang-baru.jpg'),
        ])
        ->assertRedirect(route('wali-murid.dashboard'));

    $baru = PembayaranPendaftaranAwal::where('user_id', $this->wali->id)
        ->where('gelombang_ppdb_id', $gelombangBaru->id)
        ->firstOrFail();

    expect($baru->nominal_tagihan)->toBe(175000);
});
