<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kategori_siswa', function (Blueprint $table) {
            $table->id();
            // Nama BEBAS diubah Admin. Dulu tidak: 'Anak Yatim' sempat
            // dicocokkan sebagai teks oleh dokumenWajib() dan hitungMinimalBayar(),
            // jadi menggantinya mematikan dua aturan itu diam-diam. Dua kolom di
            // bawah memindahkan aturannya jadi data, sehingga nama kembali jadi
            // sekadar label.
            $table->string('nama');
            $table->text('deskripsi')->nullable();

            // Dokumen wajib tambahan TIDAK di sini - dia tabel relasi
            // tersendiri, dokumen_wajib_kategori. Sempat dibuat kolom JSON
            // berisi larik di sini, lalu diganti: kolom JSON tidak bisa dijamin
            // database dan tidak bisa digambar sebagai relasi di ERD.

            // Pertanyaan khusus jalur ini, ditulis bebas Admin - mis. "Nama
            // saudara yang bersekolah di sini". NULL = jalur ini tidak menanyakan
            // apa-apa di luar formulir biasa.
            //
            // Dulu kolom ini bernama `field_pendukung` dan isinya KUNCI dari
            // konstanta KategoriSiswa::FIELD_PENDUKUNG, yang cuma berisi dua
            // pilihan tetap. Dua cacatnya:
            //
            //   1. Jalur baru yang ditambahkan Admin lewat UI tidak akan pernah
            //      bisa punya pertanyaannya sendiri - kuncinya harus ada dulu di
            //      kode, lengkap dengan kolom penyimpanannya di pendaftaran_ppdb.
            //   2. Halaman formulir mencocokkan kuncinya sebagai teks
            //      (`field_pendukung === 'nama_saudara'`), jadi menambah pilihan
            //      ketiga berarti menambah cabang if lagi di TSX.
            //
            // Sekarang pertanyaannya kalimat, dan jawabannya satu kolom bebas di
            // pendaftaran_ppdb. Tidak ada lagi yang perlu dicocokkan kode.
            $table->string('pertanyaan_khusus')->nullable();

            // Urutan tampil di daftar pilihan jalur yang dibaca wali waktu
            // mengisi formulir. Perlu eksplisit karena jalur tidak punya urutan
            // alami: tanpa ini susunannya ikut abjad, dan jalur yang paling
            // banyak dipakai bisa terdampar di tengah cuma gara-gara huruf
            // awalnya. Beda dari tahun ajaran dan gelombang, yang memang sudah
            // terurut sendiri oleh waktu - keduanya sengaja tidak punya kolom ini.
            $table->unsignedSmallInteger('urutan')->default(0);

            // Saklar dua arah, sama seperti komponen_biaya dan berkas_persyaratan.
            // Jalur yang sudah dipakai pendaftar TIDAK BISA dihapus - riwayat,
            // berkas, dan pembayaran mereka menggantung padanya - jadi yang
            // dipakai saat sekolah berhenti membuka sebuah jalur ini:
            // dimatikan, bukan dibuang. Pendaftar lama tetap memegang jalurnya,
            // yang berhenti cuma penawarannya ke pendaftar baru.
            $table->boolean('status_aktif')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_siswa');
    }
};
