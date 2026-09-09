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
        Schema::create('dokumen_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_ppdb_id')->constrained('pendaftaran_ppdb')->cascadeOnDelete();
            // Kode jenis berkas, bukan foreign key ke berkas_persyaratan.
            // Dokumen yang sudah diunggah adalah CATATAN - jenisnya boleh
            // dipensiunkan Admin tanpa membuat berkas lama menggantung.
            // Alasannya persis sama dengan tagihan_item menyimpan nama komponen
            // sebagai teks. Dulu enum; itu yang bikin daftarnya tidak bisa
            // ditambah tanpa migration.
            $table->string('jenis_dokumen');
            $table->string('berkas'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_ppdb');
    }
};
