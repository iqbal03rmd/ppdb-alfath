<?php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KomponenBiaya;
use App\Models\KuotaKategori;
use App\Models\TahunAjaran;
use App\Models\TarifKategori;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {

        $tahunAjaran = TahunAjaran::updateOrCreate(
            ['nama' => '2026/2027'],
            [
                'status_aktif' => true,
                'tahun_mulai' => 2026,
            ]
        );

        $gelombang = GelombangPpdb::updateOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1'],
            [
                'tanggal_mulai' => '2026-03-01',
                'tanggal_selesai' => '2026-06-30',
                // Sengaja di masa depan biar tampilan normalnya kelihatan saat demo.
                // Ubah ke tanggal lampau kalau mau menguji peringatan "batas waktu terlewat".
                'batas_waktu_pembayaran' => '2026-09-30',
                'status_buka' => true,
            ]
        );

        $kategoriList = [
            [
                'nama' => 'Reguler',
                'deskripsi' => 'Jalur pendaftaran umum tanpa persyaratan khusus.',
            ],
            [
                'nama' => 'Saudara',
                'deskripsi' => 'Calon peserta didik yang memiliki saudara kandung terdaftar di sekolah ini.',
            ],
            [
                'nama' => 'Anak Yatim',
                'deskripsi' => 'Calon peserta didik yang ayah kandungnya telah meninggal dunia.',
            ],
            [
                'nama' => 'Anak Guru',
                'deskripsi' => 'Calon peserta didik dari anak guru atau tenaga kependidikan sekolah.',
            ],
        ];

        foreach ($kategoriList as $data) {
            KategoriSiswa::updateOrCreate(['nama' => $data['nama']], $data);
        }

        $this->seedKuotaKategori($gelombang);
        $this->seedTarifKomponenBiaya($gelombang);
    }

    /**
     * Daya tampung per kategori untuk Gelombang 1 - angka dummy demo.
     * Sengaja dibikin kecil buat kategori non-reguler biar kondisi "kuota penuh"
     * gampang diuji tanpa perlu bikin puluhan pendaftaran.
     */
    private function seedKuotaKategori(GelombangPpdb $gelombang): void
    {
        $kuotaPerKategori = [
            'Reguler' => 30,
            'Saudara' => 10,
            'Anak Yatim' => 5,
            'Anak Guru' => 5,
        ];

        $kategoriIdByNama = KategoriSiswa::pluck('id', 'nama');

        foreach ($kuotaPerKategori as $namaKategori => $kuota) {
            KuotaKategori::updateOrCreate(
                ['gelombang_ppdb_id' => $gelombang->id, 'kategori_siswa_id' => $kategoriIdByNama[$namaKategori]],
                ['kuota' => $kuota]
            );
        }
    }

    /**
     * Komponen biaya + tarif per kategori buat Gelombang 1 - dummy demo (nominal
     * belum tentu sesuai kebijakan sekolah sebenarnya), sekadar biar modul
     * Pembayaran ada data buat ditampilkan tiap kali migrate:fresh --seed.
     * Belum ada modul Admin buat kelola tarif ini, jadi sementara lewat seeder.
     */
    private function seedTarifKomponenBiaya(GelombangPpdb $gelombang): void
    {
        $kategoriIdByNama = KategoriSiswa::pluck('id', 'nama');

        $komponenBiayaList = [
            [
                'nama' => 'Uang Pendaftaran',
                'keterangan' => 'Biaya administrasi pendaftaran, dibayar sekali di awal.',
                'tarif' => ['Reguler' => 150_000, 'Saudara' => 150_000, 'Anak Yatim' => 0, 'Anak Guru' => 0],
            ],
            [
                'nama' => 'Uang Pangkal',
                'keterangan' => 'Biaya pembangunan & fasilitas sekolah, dibayar sekali saat diterima.',
                'tarif' => ['Reguler' => 5_000_000, 'Saudara' => 3_500_000, 'Anak Yatim' => 0, 'Anak Guru' => 2_000_000],
            ],
            [
                'nama' => 'Seragam',
                'keterangan' => 'Satu set seragam sekolah (harian, olahraga, muslim).',
                'tarif' => ['Reguler' => 750_000, 'Saudara' => 750_000, 'Anak Yatim' => 750_000, 'Anak Guru' => 750_000],
            ],
            [
                'nama' => 'SPP Bulan Pertama',
                'keterangan' => 'Iuran bulanan pertama, dibayar di muka.',
                'tarif' => ['Reguler' => 350_000, 'Saudara' => 350_000, 'Anak Yatim' => 175_000, 'Anak Guru' => 250_000],
            ],
        ];

        foreach ($komponenBiayaList as $data) {
            $komponen = KomponenBiaya::updateOrCreate(
                ['gelombang_ppdb_id' => $gelombang->id, 'nama' => $data['nama']],
                ['keterangan' => $data['keterangan']]
            );

            foreach ($data['tarif'] as $kategoriNama => $nominal) {
                TarifKategori::updateOrCreate(
                    ['komponen_biaya_id' => $komponen->id, 'kategori_siswa_id' => $kategoriIdByNama[$kategoriNama]],
                    ['nominal' => $nominal]
                );
            }
        }
    }
}