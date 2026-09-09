<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar master jenis berkas yang bisa diminta ke pendaftar.
     *
     * Sebelum 10 September 2026 daftar ini konstanta di kode
     * (DokumenPpdb::LABEL) dan kolomnya enum di dua tabel. Alasan yang ditulis
     * waktu itu: "tiap jenis dokumen butuh label dan penanganan unggahannya
     * sendiri di kode, jadi menambah jenis baru memang pekerjaan programmer."
     *
     * Alasan itu TIDAK terbukti. Unggahannya sepenuhnya generik - satu input
     * berkas, satu aturan validasi (pdf/jpg/png, maks 2 MB), satu folder
     * penyimpanan - dan dibedakan cuma oleh string jenis_dokumen. Tidak ada
     * satu baris pun kode khusus per jenis. Yang benar-benar dibutuhkan sebuah
     * jenis baru cuma LABELNYA, dan itu justru yang paling pantas jadi data.
     *
     * `kode` yang disimpan di dokumen_ppdb dan dokumen_wajib_kategori, bukan id.
     * Sengaja, dan alasannya sama dengan tagihan_item menyimpan nama komponen
     * sebagai teks: dokumen yang sudah diunggah adalah CATATAN. Kalau jenisnya
     * dihapus atau diganti, berkas lama harus tetap bisa dibuka dan tetap
     * kelihatan dulu diminta sebagai apa.
     */
    public function up(): void
    {
        Schema::create('berkas_persyaratan', function (Blueprint $table) {
            $table->id();

            // Dipakai sebagai nilai jenis_dokumen di dokumen_ppdb dan
            // dokumen_wajib_kategori. Tidak ikut berubah walau namanya diganti
            // Admin - kalau ikut, seluruh berkas yang sudah diunggah kehilangan
            // jenisnya sekaligus.
            $table->string('kode')->unique();

            // Yang dibaca wali di halaman Unggah Berkas. Ini yang boleh diubah
            // Admin kapan saja.
            $table->string('nama');

            $table->text('keterangan')->nullable();

            // Urutan tampil di checklist Unggah Berkas. Tanpa ini urutannya ikut
            // apa pun yang dikembalikan database, dan susunan checklist yang
            // dibaca wali bisa berpindah-pindah tanpa sebab.
            $table->unsignedSmallInteger('urutan')->default(0);

            // Saklar dua arah, sama seperti komponen_biaya. Jenis yang sudah
            // tidak diminta sekolah dimatikan, bukan dihapus: menghapusnya bikin
            // berkas yang sudah telanjur diunggah kehilangan namanya. Dimatikan,
            // dia cuma berhenti ditawarkan buat pendaftaran baru.
            $table->boolean('status_aktif')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkas_persyaratan');
    }
};
