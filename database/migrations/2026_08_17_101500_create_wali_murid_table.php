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
        Schema::create('wali_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_ppdb_id')->constrained('pendaftaran_ppdb')->cascadeOnDelete();
            $table->string('nama');
            $table->string('nik', 16);
            $table->string('hubungan'); // Ayah, Ibu, Wali lainnya
            $table->string('telepon');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wali_murid');
    }
};
