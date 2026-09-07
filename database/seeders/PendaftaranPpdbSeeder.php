<?php

namespace Database\Seeders;

use App\Models\AsalPaud;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\PendaftaranPpdb;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data dummy buat demo alur Pendaftaran + Unggah Berkas + Pembayaran tanpa
 * harus daftar manual lewat UI tiap kali migrate:fresh --seed. Nomornya
 * sengaja dibikin nyakup semua status pendaftaran (termasuk yang belum
 * boleh bayar) supaya modul Pembayaran bisa langsung dicek dari berbagai
 * kondisi: belum ada tagihan yang boleh diakses, boleh bayar tapi belum
 * bayar, menunggu verifikasi, sudah terverifikasi, dan ditolak.
 */
class PendaftaranPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $wali = User::where('email', 'wali@ppdbalfath.test')->first();
        $gelombang = GelombangPpdb::where('nama', 'Gelombang 1')->first();
        $kategoriIdByNama = KategoriSiswa::pluck('id', 'nama');

        // Isian laporan buat delapan baris di bawah. Nilainya tidak penting satu
        // per satu - yang penting TIDAK dibiarkan kosong, karena delapan baris
        // "Belum diisi" cukup untuk nangkring di puncak grafik asal PAUD dan
        // menutupi temuan yang sebenarnya.
        // Dicari menurut jenis + nama, BUKAN lewat namaLengkap(). Label tampilan
        // boleh berubah kapan saja (kecamatannya baru saja ikut ditambahkan);
        // kalau seeder ikut bergantung padanya, tiap perubahan label memaksa
        // puluhan baris di bawah ikut diedit.
        $paudId = AsalPaud::get()->mapWithKeys(fn (AsalPaud $p) => ["{$p->jenis} {$p->nama}" => $p->id]);
        $isianLaporan = [
            ['Bukit Raya', 'TK ADZKIYA', 'keluarga_teman'],
            ['Marpoyan Damai', 'RA AL-FALAH', 'alumni_wali'],
            ['Tenayan Raya', 'TK ABDUL MULUK', 'media_sosial'],
            ['Sukajadi', 'RA LA-TAHZAN', 'guru_paud'],
            ['Binawidya', 'TK AISYIYAH VIII', 'keluarga_teman'],
            ['Marpoyan Damai', 'RA AL-FALAH', 'alumni_wali'],
            ['Payung Sekaki', 'RA ABDUL RAHMAN', 'brosur_spanduk'],
            ['Bukit Raya', 'TK ADZKIYA', 'acara_sekolah'],
        ];

        if (! $wali || ! $gelombang || $kategoriIdByNama->isEmpty()) {
            return; // UserSeeder/MasterDataSeeder belum jalan - jangan seed data yatim piatu.
        }

        // Placeholder buat semua file dokumen & bukti transfer di seeder ini,
        // biar link "Lihat Berkas" dan pratinjau bukti transfer nggak kosong.
        //
        // Sengaja ditimpa tiap kali di-seed, bukan dilewati kalau sudah ada:
        // dulu isinya gambar 1x1 piksel, dan file itu terlanjur mengendap di
        // storage sehingga halaman staf menampilkan gambar yang tak terlihat.
        $placeholderPath = 'seed-placeholder.png';
        Storage::disk('public')->put($placeholderPath, $this->gambarPlaceholder());

        $pendaftaranList = [
            [
                'nomor' => '00001',
                'nama' => 'Fulan bin Ahmad',
                'kategori' => 'Reguler',
                'status' => 'draft',
                'catatan' => null,
                'berkasLengkap' => false,
                'pembayaran' => null,
            ],
            [
                'nomor' => '00002',
                'nama' => 'Zahra Aulia Ramadhani',
                'kategori' => 'Saudara',
                'status' => 'diajukan',
                'catatan' => null,
                'berkasLengkap' => true,
                'pembayaran' => null,
            ],
            [
                'nomor' => '00003',
                'nama' => 'Rizky Pratama Putra',
                'kategori' => 'Reguler',
                'status' => 'perlu_perbaikan',
                'catatan' => 'NIK yang diunggah tidak terbaca jelas, mohon unggah ulang Kartu Keluarga dengan foto yang lebih jelas.',
                'berkasLengkap' => true,
                'pembayaran' => null,
            ],
            [
                'nomor' => '00004',
                'nama' => 'Aisyah Putri Lestari',
                'kategori' => 'Anak Yatim',
                'status' => 'diverifikasi',
                'catatan' => null,
                'berkasLengkap' => true,
                'pembayaran' => null,
            ],
            [
                'nomor' => '00005',
                'nama' => 'Bagus Setiawan',
                'kategori' => 'Reguler',
                // Tetap 'diverifikasi', BUKAN 'diterima' - staf belum sempat menilai
                // pembayaran ini (masih menunggu_verifikasi). 'diterima' baru dipakai
                // setelah staf manual memutuskan pembayarannya cukup (lihat 00006).
                'status' => 'diverifikasi',
                'catatan' => null,
                'berkasLengkap' => true,
                'pembayaran' => [
                    // PAS minimal bayar Reguler (3jt dari total 4,5jt). Sengaja
                    // begitu: begitu staf memverifikasi transfer ini, status
                    // pendaftaran naik sendiri jadi 'diterima' - skenario itu
                    // yang paling perlu bisa dicoba saat demo.
                    'nominal_transfer' => 3_000_000,
                    'tanggal_transfer' => now()->subDays(3)->toDateString(),
                    'status' => 'menunggu_verifikasi',
                    'catatan_verifikasi' => null,
                ],
            ],
            [
                'nomor' => '00006',
                'nama' => 'Citra Maharani',
                'kategori' => 'Saudara',
                'status' => 'diterima',
                'catatan' => null,
                'berkasLengkap' => true,
                'pembayaran' => [
                    // Lunas penuh: total tagihan jalur Saudara memang 4jt.
                    'nominal_transfer' => 4_000_000,
                    'tanggal_transfer' => now()->subDays(10)->toDateString(),
                    'status' => 'terverifikasi',
                    'catatan_verifikasi' => null,
                ],
            ],
            [
                'nomor' => '00007',
                'nama' => 'Dimas Prakoso',
                'kategori' => 'Anak Guru',
                // 'ditolak' di sini BUKAN karena bukti transfer ditolak (itu tetap
                // 'diverifikasi', lihat 00008) - ini staf yang manual nutup
                // pendaftaran karena nggak dibayar sampai batas waktu sekolah.
                // Makanya nggak ada record pembayaran sama sekali di bawah.
                'status' => 'ditolak',
                'catatan' => 'Tidak melakukan pembayaran hingga batas waktu yang ditentukan sekolah.',
                'berkasLengkap' => true,
                'pembayaran' => null,
            ],
            [
                'nomor' => '00008',
                'nama' => 'Rania Salsabila',
                'kategori' => 'Reguler',
                // Bukti transfer ditolak (nominal/foto bermasalah) - pendaftaran
                // TETAP diverifikasi, wali cuma perlu unggah ulang bukti transfer.
                'status' => 'diverifikasi',
                'catatan' => null,
                'berkasLengkap' => true,
                'pembayaran' => [
                    // Di bawah minimal bayar - buktinya ditolak, jadi nominal ini
                    // memang tidak pernah terhitung.
                    'nominal_transfer' => 2_500_000,
                    'tanggal_transfer' => now()->subDays(6)->toDateString(),
                    'status' => 'ditolak',
                    'catatan_verifikasi' => 'Nominal transfer tidak sesuai dan bukti transfer buram. Silakan unggah ulang.',
                ],
            ],
        ];

        foreach ($pendaftaranList as $urutan => $data) {
            [$kecamatan, $paud, $tahuDari] = $isianLaporan[$urutan];

            $pendaftaran = PendaftaranPpdb::updateOrCreate(
                ['nomor_pendaftaran' => "PPDB-2026-{$data['nomor']}"],
                [
                    'user_id' => $wali->id,
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kategori_siswa_id' => $kategoriIdByNama[$data['kategori']],
                    'nama_pendaftar' => $data['nama'],
                    'nik' => '32750101160000' . substr($data['nomor'], -2),
                    'tanggal_lahir' => '2020-04-15',
                    'tempat_lahir' => 'Pekanbaru',
                    'jenis_kelamin' => 'laki-laki',
                    'agama' => 'Islam',
                    'alamat' => 'Jl. Contoh Alamat No. 10, Pekanbaru',
                    'kelurahan' => 'Sidomulyo Timur',
                    'kecamatan' => $kecamatan,
                    'kota_kabupaten' => 'Kota Pekanbaru',
                    'provinsi' => 'Riau',
                    'rt' => '003',
                    'rw' => '005',
                    'asal_paud_id' => $paudId[$paud] ?? null,
                    'tahu_dari' => $tahuDari,
                    'nama_saudara' => $data['kategori'] === 'Saudara' ? 'Kakak ' . $data['nama'] : null,
                    'nama_orang_tua_guru' => $data['kategori'] === 'Anak Guru' ? 'Orang Tua ' . $data['nama'] : null,
                    'status' => $data['status'],
                    'catatan_verifikasi' => $data['catatan'],
                ]
            );

            $pendaftaran->waliMurid()->updateOrCreate(
                ['pendaftaran_ppdb_id' => $pendaftaran->id],
                ['nama' => 'Wali ' . $data['nama'], 'nik' => '3275010101800001', 'hubungan' => 'Ayah', 'telepon' => '081200000099']
            );

            if ($data['berkasLengkap']) {
                $jenisDokumen = ['kartu_keluarga', 'akta', 'ktp_orangtua', 'pas_foto'];
                if ($data['kategori'] === 'Anak Yatim') {
                    $jenisDokumen[] = 'surat_kematian_ayah';
                }

                foreach ($jenisDokumen as $jenis) {
                    $pendaftaran->dokumen()->updateOrCreate(
                        ['pendaftaran_ppdb_id' => $pendaftaran->id, 'jenis_dokumen' => $jenis],
                        ['berkas' => $placeholderPath]
                    );
                }
            }

            // Tagihan diterbitkan (snapshot tarif dibekukan) buat pendaftaran yang
            // statusnya sudah boleh bayar - meniru apa yang terjadi di aplikasi
            // waktu wali pertama kali membuka halaman Pembayaran.
            if (in_array($data['status'], ['diverifikasi', 'diterima'])) {
                $pendaftaran->terbitkanTagihan();
            }

            if ($data['pembayaran']) {
                $pendaftaran->pembayaran()->updateOrCreate(
                    ['pendaftaran_ppdb_id' => $pendaftaran->id],
                    [
                        'nominal_transfer' => $data['pembayaran']['nominal_transfer'],
                        'tanggal_transfer' => $data['pembayaran']['tanggal_transfer'],
                        'bukti_transfer' => $placeholderPath,
                        'status' => $data['pembayaran']['status'],
                        'catatan_verifikasi' => $data['pembayaran']['catatan_verifikasi'],
                    ]
                );
            }
        }
    }

    /**
     * Gambar contoh berukuran wajar buat dipakai sebagai dokumen dan bukti
     * transfer di data seed. Digambar sendiri pakai GD supaya tidak perlu
     * menyimpan berkas gambar di repo.
     *
     * Ukurannya dibikin tegak seperti tangkapan layar HP, karena itu bentuk
     * bukti transfer yang paling sering diunggah wali - biar tata letak halaman
     * staf teruji dengan bentuk yang mendekati aslinya.
     */
    private function gambarPlaceholder(): string
    {
        $lebar = 640;
        $tinggi = 880;

        $gambar = imagecreatetruecolor($lebar, $tinggi);

        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $navy = imagecolorallocate($gambar, 10, 57, 129);
        $abu = imagecolorallocate($gambar, 214, 221, 230);

        imagefill($gambar, 0, 0, $putih);

        // Bilah judul
        imagefilledrectangle($gambar, 0, 0, $lebar, 90, $navy);
        imagestring($gambar, 5, 30, 38, 'CONTOH BERKAS - DATA SEED', $putih);

        // Garis-garis abu meniru baris teks pada dokumen/struk
        for ($baris = 0; $baris < 12; $baris++) {
            $y = 150 + $baris * 55;
            imagefilledrectangle($gambar, 40, $y, $lebar - 40 - ($baris % 3) * 120, $y + 14, $abu);
        }

        ob_start();
        imagepng($gambar);
        imagedestroy($gambar);

        return ob_get_clean();
    }
}
