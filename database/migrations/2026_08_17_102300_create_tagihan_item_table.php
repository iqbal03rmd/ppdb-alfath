<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot tagihan per pendaftaran. Nilainya DISALIN dari komponen_biaya +
     * tarif_kategori pada saat tagihan diterbitkan, lalu dibekukan - jadi
     * perubahan tarif oleh Admin sesudahnya tidak mengubah tagihan orang yang
     * sudah terlanjur menerima/membayar tagihannya.
     */
    public function up(): void
    {
        Schema::create('tagihan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_ppdb_id')->constrained('pendaftaran_ppdb')->cascadeOnDelete();
            $table->string('nama_komponen');
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('nominal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan_item');
    }
};
