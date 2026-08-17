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
        Schema::create('kuota_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')->constrained('kategori_siswa')->cascadeOnDelete();
            $table->unsignedInteger('kuota');
            $table->timestamps();
 
            $table->unique(['gelombang_ppdb_id', 'kategori_siswa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuota_kategori');
    }
};
