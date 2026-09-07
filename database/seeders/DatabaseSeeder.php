<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MasterDataSeeder::class,
            // Daftar sekolah asal harus lebih dulu daripada dua seeder
            // pendaftaran di bawah - keduanya merujuk sekolah menurut namanya.
            AsalPaudSeeder::class,
            PendaftaranPpdbSeeder::class,
            // Data volume buat laporan Kepala Sekolah. Dipisah dari seeder di
            // atas - alasannya di kepala berkasnya.
            PendaftaranLaporanSeeder::class,
        ]);
    }
}