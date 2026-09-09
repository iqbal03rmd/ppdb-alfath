<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kebijakan sebuah gelombang untuk SATU jalur pendaftaran.
     *
     * Dulu bernama kuota_kategori dan cuma memuat kuota. Namanya diganti waktu
     * minimal bayar ikut pindah ke sini (8 September 2026): dua-duanya keputusan
     * yang berlaku untuk satu kombinasi gelombang x kategori, dan memisahnya ke
     * dua tabel berarti dua baris yang harus dijaga sinkron untuk kunci yang
     * sama persis.
     */
    public function up(): void
    {
        Schema::create('kebijakan_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')->constrained('kategori_siswa')->cascadeOnDelete();

            // NULL = daya tampung TIDAK DIBATASI, bukan nol. Bedanya besar:
            // kalau yang belum diisi dianggap nol, seluruh pendaftaran ikut
            // tertutup cuma gara-gara data master belum sempat diisi.
            //
            // Dulu "tidak dibatasi" diwakili oleh barisnya yang tidak ada.
            // Sekarang barisnya harus tetap hidup karena dia juga memegang
            // minimal_bayar - jadi ketidakterbatasannya pindah ke kolom ini.
            $table->unsignedInteger('kuota')->nullable();

            // Minimal bayar khusus jalur ini. NULL = ikut nilai bawaan di
            // gelombang_ppdb.minimal_pembayaran.
            //
            // Menggantikan gelombang_ppdb.minimal_bayar_persen_yatim yang lama.
            // Kolom itu memakai PERSENTASE dan namanya menyebut satu jalur, jadi
            // jalur baru yang ditambahkan Admin lewat UI tidak akan pernah bisa
            // punya minimal bayar sendiri tanpa mengubah kode. Nominal per jalur
            // menyelesaikan keduanya sekaligus, dan lebih gampang dijelaskan ke
            // wali daripada persentase (keputusan user, 8 September 2026).
            $table->unsignedBigInteger('minimal_bayar')->nullable();

            $table->timestamps();

            $table->unique(['gelombang_ppdb_id', 'kategori_siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebijakan_kategori');
    }
};
