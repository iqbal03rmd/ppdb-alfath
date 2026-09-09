<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen wajib TAMBAHAN per jalur pendaftaran - di luar berkas dasar yang
     * diminta ke semua pendaftar.
     *
     * Bentuknya tabel relasi, sama seperti tarif_kategori dan kebijakan_kategori.
     * Sempat dibuat sebagai kolom JSON berisi larik di kategori_siswa, lalu
     * diganti (8 September 2026): kolom JSON tidak bisa dijamin database, tidak
     * bisa ditanya terbalik ("jalur mana saja yang minta surat kematian?"), dan
     * tidak bisa digambar di ERD - dia cuma muncul sebagai satu kotak.
     *
     * `jenis_dokumen` menyimpan KODE dari berkas_persyaratan, bukan foreign
     * key-nya. Dulu enum dengan daftar disalin dari dokumen_ppdb, dengan alasan
     * "menambah jenis baru memang pekerjaan programmer" - alasan yang ternyata
     * salah: unggahannya generik, yang dibutuhkan jenis baru cuma labelnya.
     * Sejak 10 September 2026 daftarnya jadi data, diatur di menu Berkas
     * Persyaratan.
     *
     * Kuncinya GELOMBANG x JALUR, bukan jalur saja (10 September 2026). Sebelum
     * itu syarat berkas melekat pada jalur, jadi mengubahnya berlaku SURUT ke
     * semua pendaftaran yang pernah ada: menambah satu syarat bikin anak yang
     * sudah diterima tercatat kurang berkas, dan mencabut satu syarat bikin
     * berkas yang telanjur diunggah hilang dari layar staf walau filenya masih
     * tersimpan. Dua-duanya terbukti waktu diuji, bukan dugaan.
     *
     * Dengan gelombang ikut jadi kunci, pendaftar Gelombang 1 memegang syarat
     * Gelombang 1 walau Gelombang 2 memakai syarat yang lain - sama persis
     * dengan tarif dan kuota, yang memang sudah berbentuk gelombang x jalur.
     */
    public function up(): void
    {
        Schema::create('dokumen_wajib_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')->constrained('kategori_siswa')->cascadeOnDelete();
            $table->string('jenis_dokumen');
            $table->timestamps();

            // Satu jalur di satu gelombang tidak mungkin mewajibkan dokumen yang
            // sama dua kali.
            $table->unique(['gelombang_ppdb_id', 'kategori_siswa_id', 'jenis_dokumen'], 'dokumen_wajib_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_wajib_kategori');
    }
};
