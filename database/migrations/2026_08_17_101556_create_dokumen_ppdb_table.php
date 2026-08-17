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
            $table->enum('jenis_dokumen', [
                'akta', 'kartu_keluarga', 'ktp_orangtua', 'pas_foto',
                'surat_kematian_ayah', 'surat_keterangan_tidak_mampu',
            ]);
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
