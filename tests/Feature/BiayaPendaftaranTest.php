<?php

use App\Models\AsalPaud;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
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
    $this->actingAs($this->wali)
        ->post(route('wali-murid.biaya-pendaftaran.store'), [
            'nominal_transfer' => 125000,
            'tanggal_transfer' => today()->format('Y-m-d'),
            'bukti_transfer' => UploadedFile::fake()->image('bukti.jpg'),
        ])
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));

    $pembayaran = PembayaranPendaftaranAwal::where('user_id', $this->wali->id)->firstOrFail();

    expect($pembayaran->status)->toBe('menunggu_verifikasi')
        ->and($pembayaran->nominal_tagihan)->toBe(125000);

    $this->actingAs($this->wali)
        ->post(route('wali-murid.biaya-pendaftaran.store'), [
            'nominal_transfer' => 125000,
            'tanggal_transfer' => today()->format('Y-m-d'),
            'bukti_transfer' => UploadedFile::fake()->image('bukti-lagi.jpg'),
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

    $this->actingAs($this->wali)
        ->post(route('wali-murid.biaya-pendaftaran.store'), [
            'nominal_transfer' => 175000,
            'tanggal_transfer' => today()->format('Y-m-d'),
            'bukti_transfer' => UploadedFile::fake()->image('gelombang-baru.jpg'),
        ])
        ->assertRedirect(route('wali-murid.biaya-pendaftaran.show'));

    $baru = PembayaranPendaftaranAwal::where('user_id', $this->wali->id)
        ->where('gelombang_ppdb_id', $gelombangBaru->id)
        ->firstOrFail();

    expect($baru->nominal_tagihan)->toBe(175000);
});
