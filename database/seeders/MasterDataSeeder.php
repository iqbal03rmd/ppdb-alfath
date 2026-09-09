<?php

namespace Database\Seeders;

use App\Models\BerkasPersyaratan;
use App\Models\DokumenWajibKategori;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KebijakanKategori;
use App\Models\KomponenBiaya;
use App\Models\TahunAjaran;
use App\Models\TarifKategori;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Data contoh Konfigurasi PPDB.
 *
 * Urutannya sengaja mengikuti struktur menu Super Admin, supaya berkas ini bisa
 * dibaca sebagai "apa yang harus diisi admin sebelum PPDB jalan": tahun ajaran
 * -> komponen biaya -> gelombang (kuota & minimal bayar) -> jalur (dokumen,
 * form khusus, tarif).
 */
class MasterDataSeeder extends Seeder
{
    /**
     * Dokumen yang dipakai hampir semua jalur.
     *
     * BUKAN konstanta aturan - cuma titik awal data contoh. Tiap jalur menyimpan
     * daftar dokumennya sendiri di tabel dokumen_wajib_kategori dan bebas
     * berbeda; tidak ada lagi "berkas dasar" yang tertanam di kode.
     */
    private const DOKUMEN_UMUM = ['kartu_keluarga', 'akta', 'ktp_orangtua', 'pas_foto'];

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
                // Setoran minimal BAWAAN; jalur yang butuh angka lain mengisi
                // minimal_bayar-nya sendiri di kebijakan_kategori.
                'minimal_pembayaran' => 3_000_000,
                'status_buka' => true,
            ]
        );

        $this->seedBerkasPersyaratan();
        $komponen = $this->seedKomponenBiaya();
        $jalur = $this->seedJalurPendaftaran();

        $this->seedKebijakanKategori($gelombang, $jalur);
        $this->seedBerkasWajib($gelombang, $jalur);
        $this->seedTarif($gelombang, $komponen, $jalur);
    }

    /**
     * Jenis berkas yang bisa diminta ke pendaftar - daftar master.
     *
     * Sampai 10 September 2026 daftar ini konstanta DokumenPpdb::LABEL. Kodenya
     * dipertahankan persis supaya baris dokumen_ppdb dan dokumen_wajib_kategori
     * yang sudah ada tetap ketemu jenisnya.
     */
    private function seedBerkasPersyaratan(): void
    {
        $daftar = [
            ['kode' => 'kartu_keluarga', 'nama' => 'Kartu Keluarga (KK)', 'urutan' => 1, 'keterangan' => 'Kartu Keluarga yang mencantumkan nama calon peserta didik.'],
            ['kode' => 'akta', 'nama' => 'Akta Kelahiran', 'urutan' => 2, 'keterangan' => 'Akta kelahiran calon peserta didik.'],
            ['kode' => 'ktp_orangtua', 'nama' => 'KTP Orang Tua / Wali', 'urutan' => 3, 'keterangan' => 'KTP ayah, ibu, atau wali yang mendaftarkan.'],
            ['kode' => 'pas_foto', 'nama' => 'Pas Foto Calon Peserta Didik', 'urutan' => 4, 'keterangan' => 'Pas foto berwarna terbaru, latar bebas.'],
            ['kode' => 'surat_kematian_ayah', 'nama' => 'Surat Kematian Ayah', 'urutan' => 5, 'keterangan' => 'Surat keterangan kematian dari kelurahan atau rumah sakit.'],
            ['kode' => 'surat_keterangan_tidak_mampu', 'nama' => 'Surat Keterangan Tidak Mampu', 'urutan' => 6, 'keterangan' => 'SKTM dari kelurahan setempat.'],
        ];

        foreach ($daftar as $d) {
            BerkasPersyaratan::updateOrCreate(['kode' => $d['kode']], [...$d, 'status_aktif' => true]);
        }
    }

    /**
     * Komponen biaya - daftar GLOBAL, dipakai semua gelombang. Yang berbeda tiap
     * gelombang cuma nominalnya, dan itu ada di seedTarif().
     *
     * @return Collection<string, KomponenBiaya>
     */
    private function seedKomponenBiaya(): Collection
    {
        $daftar = [
            ['nama' => 'Pembangunan', 'urutan' => 1, 'keterangan' => 'Biaya pembangunan & fasilitas sekolah, dibayar sekali saat diterima.'],
            ['nama' => 'Seragam', 'urutan' => 2, 'keterangan' => 'Satu set seragam sekolah (harian, olahraga, muslim).'],
            ['nama' => 'Buku', 'urutan' => 3, 'keterangan' => 'Paket buku pelajaran tahun pertama.'],
            ['nama' => 'SPP Bulan Pertama', 'urutan' => 4, 'keterangan' => 'Iuran bulanan pertama, dibayar di muka.'],
        ];

        return collect($daftar)
            ->map(fn (array $d) => KomponenBiaya::updateOrCreate(['nama' => $d['nama']], $d))
            ->keyBy('nama');
    }

    /**
     * Jalur pendaftaran beserta dokumen dan isian pendukungnya.
     *
     * Tidak ada satu pun aturan yang dicocokkan dari NAMA jalur - dokumen dan
     * form khususnya tersimpan sebagai data, jadi namanya bebas diubah admin
     * tanpa merusak apa pun.
     *
     * @return Collection<string, KategoriSiswa>
     */
    private function seedJalurPendaftaran(): Collection
    {
        $daftar = [
            [
                'nama' => 'Reguler',
                'urutan' => 1,
                'deskripsi' => 'Jalur pendaftaran umum tanpa persyaratan khusus.',
                'pertanyaan_khusus' => null,
                'dokumen' => self::DOKUMEN_UMUM,
            ],
            [
                'nama' => 'Saudara',
                'urutan' => 2,
                'deskripsi' => 'Calon peserta didik yang memiliki saudara kandung terdaftar di sekolah ini.',
                'pertanyaan_khusus' => 'Nama saudara yang bersekolah di sini',
                'dokumen' => self::DOKUMEN_UMUM,
            ],
            [
                'nama' => 'Anak Yatim',
                'urutan' => 4,
                'deskripsi' => 'Calon peserta didik yang ayah kandungnya telah meninggal dunia.',
                'pertanyaan_khusus' => null,
                'dokumen' => [...self::DOKUMEN_UMUM, 'surat_kematian_ayah'],
            ],
            [
                'nama' => 'Anak Guru',
                'urutan' => 3,
                'deskripsi' => 'Calon peserta didik dari anak guru atau tenaga kependidikan sekolah.',
                // Isian ini dulu TIDAK PERNAH muncul: layar mencocokkan
                // 'Anak Guru/Tenaga Kependidikan' padahal jalurnya bernama
                // 'Anak Guru'. Sekarang pemicunya data, bukan ejaan.
                'pertanyaan_khusus' => 'Nama orang tua yang mengajar di sini',
                'dokumen' => self::DOKUMEN_UMUM,
            ],
        ];

        return collect($daftar)
            ->map(function (array $d) {
                // Syarat berkasnya TIDAK disimpan di sini lagi - itu milik
                // gelombang x jalur, diisi seedBerkasWajib().
                unset($d['dokumen']);

                return KategoriSiswa::updateOrCreate(['nama' => $d['nama']], $d);
            })
            ->keyBy('nama');
    }

    /**
     * Syarat berkas tiap jalur, untuk SATU gelombang.
     *
     * Kuncinya gelombang x jalur (10 September 2026). Sebelumnya melekat pada
     * jalur saja, dan itu bikin syarat yang berubah berlaku surut ke angkatan
     * yang sudah lewat - lihat komentar di migration dokumen_wajib_kategori.
     *
     * @param  Collection<string, KategoriSiswa>  $jalur
     */
    private function seedBerkasWajib(GelombangPpdb $gelombang, Collection $jalur): void
    {
        $syarat = [
            'Reguler' => self::DOKUMEN_UMUM,
            'Saudara' => self::DOKUMEN_UMUM,
            'Anak Yatim' => [...self::DOKUMEN_UMUM, 'surat_kematian_ayah'],
            'Anak Guru' => self::DOKUMEN_UMUM,
        ];

        foreach ($syarat as $namaJalur => $daftarJenis) {
            foreach ($daftarJenis as $jenis) {
                DokumenWajibKategori::updateOrCreate([
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kategori_siswa_id' => $jalur[$namaJalur]->id,
                    'jenis_dokumen' => $jenis,
                ]);
            }
        }
    }

    /**
     * Kuota + minimal bayar per jalur untuk Gelombang 1.
     *
     * Kuota sengaja dibikin kecil buat jalur non-reguler biar kondisi "kuota
     * penuh" gampang diuji tanpa perlu bikin puluhan pendaftaran.
     *
     * minimal_bayar null = ikut bawaan gelombang (3jt). Cuma Anak Yatim yang
     * punya angkanya sendiri: total tagihannya 925rb, jadi 3jt mustahil dipenuhi.
     *
     * @param  Collection<string, KategoriSiswa>  $jalur
     */
    private function seedKebijakanKategori(GelombangPpdb $gelombang, Collection $jalur): void
    {
        $kebijakan = [
            'Reguler' => ['kuota' => 30, 'minimal_bayar' => null],
            'Saudara' => ['kuota' => 10, 'minimal_bayar' => null],
            'Anak Yatim' => ['kuota' => 5, 'minimal_bayar' => 462_500],
            'Anak Guru' => ['kuota' => 5, 'minimal_bayar' => null],
        ];

        foreach ($kebijakan as $nama => $nilai) {
            KebijakanKategori::updateOrCreate(
                ['gelombang_ppdb_id' => $gelombang->id, 'kategori_siswa_id' => $jalur[$nama]->id],
                $nilai
            );
        }
    }

    /**
     * Nominal tiap komponen, per jalur, untuk Gelombang 1.
     *
     * Nominal 0 SAH - artinya jalur itu dibebaskan dari pos tersebut, bukan
     * "belum diisi". Total per jalur:
     *
     *   Reguler     4.500.000  -> minimal 3.000.000, sisa cicilan 1.500.000
     *   Saudara     4.000.000  -> minimal 3.000.000, sisa cicilan 1.000.000
     *   Anak Guru   4.000.000  -> minimal 3.000.000, sisa cicilan 1.000.000
     *   Anak Yatim    925.000  -> minimal   462.500, sisa cicilan   462.500
     *
     * @param  Collection<string, KomponenBiaya>  $komponen
     * @param  Collection<string, KategoriSiswa>  $jalur
     */
    private function seedTarif(GelombangPpdb $gelombang, Collection $komponen, Collection $jalur): void
    {
        $urutanJalur = ['Reguler', 'Saudara', 'Anak Yatim', 'Anak Guru'];

        $tarif = [
            //                     Reguler   Saudara  A.Yatim   A.Guru
            // Seluruh selisih antar jalur ditaruh di sini: Reguler 4,5jt dan
            // Saudara/Anak Guru 4jt, jadi bedanya pas 500rb dan gampang
            // dijelaskan ke wali. Anak Yatim dibebaskan sepenuhnya.
            'Pembangunan' => [3_000_000, 2_500_000, 0, 2_500_000],
            'Seragam' => [750_000, 750_000, 750_000, 750_000],
            'Buku' => [400_000, 400_000, 0, 400_000],
            'SPP Bulan Pertama' => [350_000, 350_000, 175_000, 350_000],
        ];

        foreach ($tarif as $namaKomponen => $nominalPerJalur) {
            foreach ($urutanJalur as $i => $namaJalur) {
                TarifKategori::updateOrCreate(
                    [
                        'gelombang_ppdb_id' => $gelombang->id,
                        'komponen_biaya_id' => $komponen[$namaKomponen]->id,
                        'kategori_siswa_id' => $jalur[$namaJalur]->id,
                    ],
                    ['nominal' => $nominalPerJalur[$i]]
                );
            }
        }
    }
}
