<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar satuan PAUD asal calon murid.
 *
 * "PAUD", bukan "TK". TK cuma SATU jenis di bawah payung PAUD; yang lain RA
 * (Raudhatul Athfal), KB (Kelompok Bermain), TPA (Taman Penitipan Anak), dan
 * SPS (Satuan PAUD Sejenis).
 *
 * Bedanya penting justru buat sekolah ini: RA didata Kemenag lewat EMIS,
 * sedangkan TK/KB/TPA/SPS didata Kemendikdasmen lewat Dapodik. Kalau daftar ini
 * diisi dari Dapodik saja, SELURUH RA hilang - dan untuk SD Islam Terpadu, RA
 * kemungkinan besar justru penyumbang murid terbesar. Grafiknya akan terlihat
 * wajar padahal diam-diam memotong satu golongan penuh.
 *
 * Daftar ini TIDAK perlu lengkap sejak hari pertama. Wali yang sekolah asalnya
 * belum terdaftar mengetiknya sendiri, tersimpan di
 * pendaftaran_ppdb.asal_paud_lainnya. Isian yang sering muncul di situ nanti
 * tinggal dinaikkan jadi baris di tabel ini. Sengaja TIDAK dibuat menambah baris
 * ke sini secara otomatis: satu typo wali akan langsung jadi "sekolah" baru yang
 * memecah hitungan, persis data kotor yang mau dihindari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asal_paud', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // TK/RA/KB/TPA/SPS. Kolom ini sendiri sudah jadi temuan: berapa
            // banyak murid yang datang dari jalur madrasah (RA) dibanding TK umum.
            $table->enum('jenis', ['TK', 'RA', 'KB', 'TPA', 'SPS']);
            // Nomor Pokok Sekolah Nasional. Nullable karena daftar awal boleh
            // diisi dari ingatan orang sekolah dulu, NPSN-nya menyusul.
            $table->string('npsn', 12)->nullable()->unique();
            // Kecamatan tempat sekolahnya berada, ikut dari data Dapodik.
            // Sekadar keterangan - dipakai membedakan dua sekolah yang namanya
            // sama persis, bukan untuk diagregasi. Karena itu cukup teks, tanpa
            // perlu tabel wilayah tersendiri.
            $table->string('kecamatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asal_paud');
    }
};
