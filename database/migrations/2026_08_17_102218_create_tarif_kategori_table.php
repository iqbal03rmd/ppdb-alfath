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
        Schema::create('tarif_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_biaya_id')->constrained('komponen_biaya')->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')->constrained('kategori_siswa')->cascadeOnDelete();
            $table->unsignedBigInteger('nominal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarif_kategori');
    }
};
