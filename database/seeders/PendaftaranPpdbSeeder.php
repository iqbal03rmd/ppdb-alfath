<?php

namespace Database\Seeders;

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

        if (! $wali || ! $gelombang || $kategoriIdByNama->isEmpty()) {
            return; // UserSeeder/MasterDataSeeder belum jalan - jangan seed data yatim piatu.
        }

        // Placeholder buat semua file dokumen & bukti transfer di seeder ini,
        // biar link "Lihat Berkas"/"Lihat Berkas Terunggah" nggak 404.
        $placeholderPath = 'seed-placeholder.png';
        if (! Storage::disk('public')->exists($placeholderPath)) {
            Storage::disk('public')->put(
                $placeholderPath,
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            );
        }

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
                    'nominal_transfer' => 6_250_000,
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
                    'nominal_transfer' => 4_750_000,
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
                    'nominal_transfer' => 2_500_000,
                    'tanggal_transfer' => now()->subDays(6)->toDateString(),
                    'status' => 'ditolak',
                    'catatan_verifikasi' => 'Nominal transfer tidak sesuai dan bukti transfer buram. Silakan unggah ulang.',
                ],
            ],
        ];

        foreach ($pendaftaranList as $data) {
            $pendaftaran = PendaftaranPpdb::updateOrCreate(
                ['nomor_pendaftaran' => "PPDB-2026-{$data['nomor']}"],
                [
                    'user_id' => $wali->id,
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kategori_siswa_id' => $kategoriIdByNama[$data['kategori']],
                    'nama_pendaftar' => $data['nama'],
                    'nik' => '32750101160000' . substr($data['nomor'], -2),
                    'tanggal_lahir' => '2020-04-15',
                    'tempat_lahir' => 'Bandung',
                    'jenis_kelamin' => 'laki-laki',
                    'agama' => 'Islam',
                    'alamat' => 'Jl. Contoh Alamat No. 10, Bandung',
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
}
