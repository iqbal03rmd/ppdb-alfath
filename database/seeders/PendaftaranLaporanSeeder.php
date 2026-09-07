<?php

namespace Database\Seeders;

use App\Models\AsalPaud;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\PendaftaranPpdb;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data VOLUME buat laporan Kepala Sekolah - bukan skenario alur.
 *
 * Dipisah dari PendaftaranPpdbSeeder dengan sengaja. Seeder itu berisi delapan
 * pendaftaran yang tiap barisnya mewakili satu keadaan yang perlu bisa dicoba
 * di modul Wali dan Staf; menambahkan puluhan baris ke sana bikin maksud tiap
 * barisnya tenggelam. Yang di sini kebalikannya: satu per satu tidak penting,
 * yang penting POLANYA - kartu temuan di halaman Rekapitulasi butuh sebaran
 * yang cukup supaya grafiknya berarti.
 *
 * Semuanya dipasang ke satu akun tersendiri (laporan@ppdbalfath.test), bukan ke
 * wali@ppdbalfath.test. Kalau ditumpuk ke akun demo itu, halaman "Pendaftaran
 * Saya" milik wali jadi berisi puluhan anak dan tidak bisa dipakai memperagakan
 * apa pun.
 *
 * Angkanya DITULIS TETAP, bukan diacak. Data acak bikin tangkapan layar hari ini
 * beda dengan yang muncul saat sidang, dan bikin test yang menguji angka jadi
 * goyah tanpa sebab.
 */
class PendaftaranLaporanSeeder extends Seeder
{
    /**
     * Satu baris = satu pendaftaran. Urutan kolomnya:
     *
     *   0 nama
     *   1 kategori           Reguler | Saudara | Anak Yatim | Anak Guru
     *   2 domisili           nama kecamatan, atau '*' untuk luar Provinsi Riau
     *   3 asal PAUD          'TK ADZKIYA' dst | '-' tidak lewat PAUD | '?Teks' diketik sendiri
     *   4 tahu dari          kunci PendaftaranPpdb::SUMBER_INFORMASI, '' = tidak menjawab
     *   5 status
     *   6 diverifikasi       berapa hari lalu berkasnya disetujui (null = belum)
     *   7 transfer           [hari setelah verifikasi => nominal] yang SUDAH disahkan staf.
     *                        Lebih dari satu entri = mencicil.
     *
     * Sebarannya sengaja dibuat timpang, bukan rata: kalau tiap PAUD dan tiap
     * saluran informasi jumlahnya sama, grafiknya rapi tapi tidak mengandung
     * temuan apa pun - dan justru ketimpangan itulah yang mau diperlihatkan ke
     * Kepala Sekolah.
     */
    private const BARIS = [
        // --- Yang lancar: diverifikasi lalu cepat membayar -------------------
        ['Naufal Hafizh', 'Reguler', 'Marpoyan Damai', 'RA AL-FALAH', 'keluarga_teman', 'diterima', 40, [1 => 4_500_000]],
        ['Khalisa Zahra', 'Reguler', 'Bukit Raya', 'RA AL-FALAH', 'alumni_wali', 'diterima', 39, [2 => 4_500_000]],
        ['Arkan Dzaki', 'Reguler', 'Marpoyan Damai', 'TK ADZKIYA', 'keluarga_teman', 'diterima', 38, [1 => 3_000_000]],
        ['Shafiyya Nur', 'Reguler', 'Tenayan Raya', 'RA AL FITRAH 2', 'guru_paud', 'diterima', 37, [3 => 3_000_000]],
        ['Hamzah Alfarizi', 'Saudara', 'Bukit Raya', 'RA AL-FALAH', 'alumni_wali', 'diterima', 36, [2 => 4_000_000]],

        // --- Yang mencicil: minimal tercapai lewat beberapa transfer ---------
        ['Aqila Humaira', 'Reguler', 'Binawidya', 'TK ADZKIYA', 'media_sosial', 'diterima', 35, [3 => 1_500_000, 12 => 1_500_000]],
        ['Faiz Abdurrahman', 'Reguler', 'Tuahmadani', 'RA ADINDA', 'keluarga_teman', 'diterima', 34, [5 => 1_000_000, 14 => 2_000_000]],
        ['Zayn Musyaffa', 'Reguler', 'Kulim', 'RA AL MUKMINUN', 'keluarga_teman', 'diterima', 33, [4 => 1_000_000, 11 => 1_000_000, 19 => 1_000_000]],
        ['Alesha Kirana', 'Saudara', 'Marpoyan Damai', 'TK AL AZHAR SYIFA BUDI', 'alumni_wali', 'diterima', 32, [6 => 2_000_000, 15 => 2_000_000]],
        // Anak Yatim minimalnya persentase dari tagihannya sendiri (925rb),
        // jadi 500rb sudah melewatinya - bukan salah tulis.
        ['Ibrahim Malik', 'Anak Yatim', 'Payung Sekaki', 'RA ABDUL RAHMAN', 'guru_paud', 'diterima', 31, [7 => 500_000]],

        // --- Yang menggantung lama sebelum akhirnya membayar -----------------
        ['Rafa Athallah', 'Reguler', 'Tenayan Raya', 'TK ABDUL MULUK', 'brosur_spanduk', 'diterima', 30, [18 => 3_000_000]],
        ['Nadira Salma', 'Reguler', 'Rumbai', 'TK AISYIYAH XII', 'media_sosial', 'diterima', 29, [21 => 3_000_000]],
        ['Yusuf Hibban', 'Reguler', 'Sail', 'KB ALUMNA INDONESIA ISLAMIC SCHOOL', 'acara_sekolah', 'diterima', 28, [24 => 4_500_000]],

        // --- Sudah boleh bayar, tapi belum menyetor sepeser pun --------------
        // Kelompok inilah yang paling perlu ditelepon staf, dan di laporan dia
        // sengaja tidak ikut ke rata-rata jeda karena jedanya belum selesai.
        ['Danish Pratama', 'Reguler', 'Marpoyan Damai', 'TK ADZKIYA', 'keluarga_teman', 'diverifikasi', 20, []],
        ['Hanan Syakira', 'Reguler', 'Bukit Raya', 'RA AL FITRAH 2', 'alumni_wali', 'diverifikasi', 18, []],
        ['Elvano Rizky', 'Reguler', 'Binawidya', '-', '', 'diverifikasi', 16, []],
        ['Kayla Azzahra', 'Anak Yatim', 'Kulim', 'RA AL MUKMINUN', 'guru_paud', 'diverifikasi', 15, []],

        // --- Sudah menyetor tapi belum mencapai minimal ----------------------
        ['Zhafran Adib', 'Reguler', 'Tenayan Raya', 'TK ABDUL MULUK', 'media_sosial', 'diverifikasi', 22, [4 => 1_000_000]],
        ['Alifa Ramadhani', 'Reguler', 'Limapuluh', 'KB AL-MUTTAQIN', 'keluarga_teman', 'diverifikasi', 19, [8 => 1_500_000]],
        ['Rasyid Hakim', 'Saudara', 'Senapelan', 'RA LA-TAHZAN', 'alumni_wali', 'diverifikasi', 17, [6 => 2_000_000]],

        // --- Masih diproses staf --------------------------------------------
        ['Talita Syifa', 'Reguler', 'Marpoyan Damai', 'RA AL-FALAH', 'keluarga_teman', 'diajukan', null, []],
        ['Ghaisan Fatih', 'Reguler', 'Bukit Raya', 'TK AISYIYAH VIII', 'brosur_spanduk', 'diajukan', null, []],
        ['Nabila Aurel', 'Reguler', 'Kampar', 'TK AL FAJAR', 'keluarga_teman', 'diajukan', null, []],
        ['Umar Fadhil', 'Anak Guru', 'Sukajadi', 'TK AL AZHAR SYIFA BUDI', '', 'perlu_perbaikan', null, []],

        // --- Dari luar Pekanbaru dan luar Riau -------------------------------
        // Dua-duanya ada isinya dengan sengaja: angka di kelompok "luar" itu
        // sendiri sudah jadi temuan - berapa keluarga yang rela menempuh jarak.
        ['Azzam Ghifari', 'Reguler', 'Siak Hulu', 'RA ADINDA', 'keluarga_teman', 'diterima', 27, [3 => 4_500_000]],
        ['Syakila Putri', 'Reguler', 'Tambang', '?TK Bina Insani Kampar', 'guru_paud', 'diterima', 26, [5 => 3_000_000]],
        ['Rayyan Abqary', 'Reguler', '*', '?TK Al-Azhar Batam', 'media_sosial', 'diverifikasi', 14, [9 => 2_000_000]],

        // --- Tidak lewat PAUD sama sekali ------------------------------------
        ['Fathir Ramadhan', 'Reguler', 'Rumbai Barat', '-', 'keluarga_teman', 'diterima', 25, [2 => 3_000_000]],
        ['Aisyah Nadhifa', 'Anak Guru', 'Tuahmadani', '-', '', 'diterima', 24, [4 => 3_000_000]],

        // --- Ditutup staf ----------------------------------------------------
        ['Bilal Maulana', 'Reguler', 'Tenayan Raya', 'TK AISYIYAH XII', 'lainnya', 'ditolak', 23, []],
        ['Zahira Alifa', 'Saudara', 'Pekanbaru Kota', 'KB ABIDARI ISLAMIC CREATIVE SCHOOL 2', 'lainnya', 'ditolak', 21, []],
    ];

    /**
     * Isian bebas untuk baris yang memilih 'lainnya'. Ditulis terpisah supaya
     * tabel di atas tidak melebar - dan supaya kelihatan bahwa inilah bahan yang
     * nanti dipakai menaikkan pilihan baru: kalau "Pengajian" sering muncul, dia
     * pantas jadi pilihan tetap tahun depan.
     */
    private const TAHU_DARI_LAINNYA = [
        'Bilal Maulana' => 'Pengajian ibu-ibu komplek',
        'Zahira Alifa' => 'Pengajian ibu-ibu komplek',
    ];

    public function run(): void
    {
        $gelombang = GelombangPpdb::where('nama', 'Gelombang 1')->first();
        $kategoriId = KategoriSiswa::pluck('id', 'nama');
        // Dicari menurut jenis + nama, BUKAN lewat namaLengkap(). Label tampilan
        // boleh berubah kapan saja (kecamatannya baru saja ikut ditambahkan);
        // kalau seeder ikut bergantung padanya, tiap perubahan label memaksa
        // puluhan baris di bawah ikut diedit.
        $paudId = AsalPaud::get()->mapWithKeys(fn (AsalPaud $p) => ["{$p->jenis} {$p->nama}" => $p->id]);

        if (! $gelombang || $kategoriId->isEmpty()) {
            return; // Seeder pendahulunya belum jalan.
        }

        $pemilik = User::updateOrCreate(
            ['email' => 'laporan@ppdbalfath.test'],
            [
                'name' => 'Data Laporan (contoh)',
                'password' => Hash::make('password'),
                'role' => 'wali_murid',
                'email_verified_at' => now(),
            ]
        );

        $staf = User::where('email', 'staf@ppdbalfath.test')->first();

        foreach (self::BARIS as $i => [$nama, $kategori, $domisili, $paud, $tahuDari, $status, $hariVerifikasi, $transfer]) {
            // Nomor mulai dari 00101 supaya tidak pernah berebut dengan
            // PendaftaranPpdbSeeder yang memakai 00001-00008.
            $nomor = 'PPDB-2026-'.str_pad((string) (101 + $i), 5, '0', STR_PAD_LEFT);
            $luarRiau = $domisili === '*';
            $diverifikasiPada = $hariVerifikasi === null ? null : now()->subDays($hariVerifikasi);

            $pendaftaran = PendaftaranPpdb::updateOrCreate(
                ['nomor_pendaftaran' => $nomor],
                [
                    'user_id' => $pemilik->id,
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kategori_siswa_id' => $kategoriId[$kategori],
                    'nama_pendaftar' => $nama,
                    'tanggal_lahir' => '2020-06-01',
                    'tempat_lahir' => 'Pekanbaru',
                    'jenis_kelamin' => $i % 2 === 0 ? 'laki-laki' : 'perempuan',
                    'alamat' => 'Jl. Contoh No. '.($i + 1).', Pekanbaru',
                    'kelurahan' => 'Kelurahan Contoh',
                    'kecamatan' => $luarRiau ? 'Batam Kota' : $domisili,
                    'kota_kabupaten' => $luarRiau ? 'Kota Batam' : 'Kota Pekanbaru',
                    'provinsi' => $luarRiau ? 'Kepulauan Riau' : 'Riau',
                    'rt' => '00'.(($i % 9) + 1),
                    'rw' => '00'.(($i % 5) + 1),
                    'asal_paud_id' => $paudId[$paud] ?? null,
                    'asal_paud_lainnya' => str_starts_with($paud, '?') ? substr($paud, 1) : null,
                    'tanpa_paud' => $paud === '-',
                    'tahu_dari' => $tahuDari ?: null,
                    'tahu_dari_lainnya' => self::TAHU_DARI_LAINNYA[$nama] ?? null,
                    // 'diterima' TIDAK ditulis langsung walau kolom 5 menyebutnya.
                    // Status itu milik aturan domain - hasil dari uang yang sudah
                    // disahkan - jadi di sini semua yang sudah lolos berkas
                    // dipasang 'diverifikasi' dulu, lalu dinaikkan sendiri oleh
                    // segarkanStatusPenerimaan() sesudah transfernya dibuat.
                    // Dengan begitu data seed tidak mungkin memuat pendaftaran
                    // "diterima" yang ternyata belum mencapai minimal bayar.
                    'status' => $status === 'diterima' ? 'diverifikasi' : $status,
                    'catatan_verifikasi' => $status === 'ditolak'
                        ? 'Tidak mencapai minimal pembayaran sampai batas waktu yang ditentukan sekolah.'
                        : null,
                    'diverifikasi_oleh' => $diverifikasiPada ? $staf?->id : null,
                    'diverifikasi_pada' => $diverifikasiPada,
                ]
            );

            $pendaftaran->waliMurid()->updateOrCreate(
                ['pendaftaran_ppdb_id' => $pendaftaran->id],
                ['nama' => 'Wali '.$nama, 'nik' => '1471010101800001', 'hubungan' => 'Ayah', 'telepon' => '081200000100']
            );

            if (in_array($status, ['diverifikasi', 'diterima', 'ditolak'])) {
                $pendaftaran->terbitkanTagihan();
            }

            foreach ($transfer as $hariSetelah => $nominal) {
                $waktu = $diverifikasiPada?->copy()->addDays($hariSetelah) ?? now();

                $baris = $pendaftaran->pembayaran()->updateOrCreate(
                    ['pendaftaran_ppdb_id' => $pendaftaran->id, 'nominal_transfer' => $nominal, 'tanggal_transfer' => $waktu->toDateString()],
                    [
                        'bukti_transfer' => 'seed-placeholder.png',
                        'status' => 'terverifikasi',
                        'diverifikasi_oleh' => $staf?->id,
                    ]
                );

                // created_at ditulis manual: itu yang dipakai
                // PendaftaranPpdb::hariSampaiTransferPertama(), dan kalau
                // dibiarkan terisi waktu seeding, seluruh jedanya jadi nol
                // dan kartu "lama menggantung" kehilangan isinya.
                $baris->forceFill(['created_at' => $waktu])->save();
            }

            // Inilah yang menentukan siapa yang jadi 'diterima' - persis jalur
            // yang dipakai aplikasi sungguhan saat staf mengesahkan transfer.
            $pendaftaran->load(['pembayaran', 'tagihanItem', 'kategoriSiswa', 'gelombang']);
            $pendaftaran->segarkanStatusPenerimaan();
        }
    }
}
