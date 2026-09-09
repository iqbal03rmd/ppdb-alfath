<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nominal satu komponen biaya, untuk satu jalur, di satu gelombang.
     *
     * Tiga kunci sekaligus, dan ketiganya perlu:
     *
     *   gelombang  - harga naik tiap angkatan
     *   kategori   - tiap jalur punya keringanannya sendiri
     *   komponen   - tiap pos biaya berdiri sendiri
     *
     * gelombang_ppdb_id BARU ditambahkan (8 September 2026). Sebelumnya
     * gelombang tersirat lewat komponen_biaya yang punya gelombang_ppdb_id -
     * begitu komponen jadi daftar global, gelombangnya harus disebut di sini
     * atau nominalnya kehilangan konteks tahun.
     *
     * Nominal 0 SAH dan berarti jalur itu dibebaskan dari pos ini - bukan
     * "belum diisi". Yang belum diisi adalah barisnya yang tidak ada, dan itu
     * ditangani terbitkanTagihan(): tagihan kosong tidak diterbitkan.
     */
    public function up(): void
    {
        Schema::create('tarif_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('komponen_biaya_id')->constrained('komponen_biaya')->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')->constrained('kategori_siswa')->cascadeOnDelete();
            $table->unsignedBigInteger('nominal');
            $table->timestamps();

            $table->unique(['gelombang_ppdb_id', 'komponen_biaya_id', 'kategori_siswa_id'], 'tarif_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_kategori');
    }
};
