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
                // Tenggat pelunasan sisa cicilan, berlaku buat semua gelombang
                // tahun ini - sengaja jauh sesudah Gelombang 2 berakhir.
                'batas_pelunasan' => '2027-03-31',
            ]
        );

        $gelombang = GelombangPpdb::updateOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1'],
            [
                // Sengaja mengapit tanggal hari ini supaya gelombang benar-benar
                // sedang berjalan saat didemokan - kalau tanggal_selesai lewat
                // sementara status_buka masih true, datanya jadi saling bertentangan.
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-10-31',
                // Sengaja di masa depan biar tampilan normalnya kelihatan saat demo.
                // Ubah ke tanggal lampau kalau mau menguji peringatan "batas waktu terlewat".
                'batas_waktu_pembayaran' => '2026-11-30',
                // Setoran minimal supaya pendaftaran berstatus 'diterima';
                // sisanya boleh dicicil sampai tahun_ajaran.batas_pelunasan.
                // Jalur Anak Yatim mengabaikan angka ini, pakai persen di bawah.
                'minimal_pembayaran' => 3_000_000,
                // Khusus jalur Anak Yatim - lihat komentar di migration-nya.
                'minimal_bayar_persen_yatim' => 50,
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
     * Komponen biaya + tarif per kategori buat Gelombang 1. Belum ada modul Admin
     * buat kelola tarif ini, jadi sementara lewat seeder.
     *
     * Total per jalur sengaja dipasang begini (minimal bayar 3jt):
     *
     *   Reguler     4.500.000  -> sisa cicilan 1.500.000
     *   Saudara     4.000.000  -> sisa cicilan 1.000.000
     *   Anak Guru   4.000.000  -> sisa cicilan 1.000.000
     *   Anak Yatim    925.000  -> minimal 50% = 462.500, sisa 462.500
     */
    private function seedTarifKomponenBiaya(GelombangPpdb $gelombang): void
    {
        $kategoriIdByNama = KategoriSiswa::pluck('id', 'nama');

        $komponenBiayaList = [
            [
                'nama' => 'Uang Pendaftaran',
                'keterangan' => 'Biaya administrasi pendaftaran, dibayar sekali di awal.',
                'tarif' => ['Reguler' => 150_000, 'Saudara' => 150_000, 'Anak Yatim' => 0, 'Anak Guru' => 150_000],
            ],
            [
                // Seluruh selisih antar jalur ditaruh di sini: Reguler 4,5jt dan
                // Saudara/Anak Guru 4jt, jadi bedanya pas 500rb dan gampang
                // dijelaskan ke wali. Anak Yatim dibebaskan sepenuhnya.
                'nama' => 'Uang Pangkal',
                'keterangan' => 'Biaya pembangunan & fasilitas sekolah, dibayar sekali saat diterima.',
                'tarif' => ['Reguler' => 3_250_000, 'Saudara' => 2_750_000, 'Anak Yatim' => 0, 'Anak Guru' => 2_750_000],
            ],
            [
                'nama' => 'Seragam',
                'keterangan' => 'Satu set seragam sekolah (harian, olahraga, muslim).',
                'tarif' => ['Reguler' => 750_000, 'Saudara' => 750_000, 'Anak Yatim' => 750_000, 'Anak Guru' => 750_000],
            ],
            [
                'nama' => 'SPP Bulan Pertama',
                'keterangan' => 'Iuran bulanan pertama, dibayar di muka.',
                'tarif' => ['Reguler' => 350_000, 'Saudara' => 350_000, 'Anak Yatim' => 175_000, 'Anak Guru' => 350_000],
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