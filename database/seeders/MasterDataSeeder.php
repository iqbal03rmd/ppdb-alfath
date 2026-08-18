<?php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\TahunAjaran;
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

        GelombangPpdb::updateOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1'],
            [
                'tanggal_mulai' => '2026-03-01',
                'tanggal_selesai' => '2026-06-30',
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
                'nama' => 'Anak Guru/Tenaga Kependidikan',
                'deskripsi' => 'Calon peserta didik dari anak guru atau tenaga kependidikan sekolah.',
            ],
        ];

        foreach ($kategoriList as $data) {
            KategoriSiswa::updateOrCreate(['nama' => $data['nama']], $data);
        }
    }
}