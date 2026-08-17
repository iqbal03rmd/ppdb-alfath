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
        Schema::create('pembayaran_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_ppdb_id')->constrained('pendaftaran_ppdb')->cascadeOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
 
            $table->unsignedBigInteger('nominal_transfer');
            $table->date('tanggal_transfer');
            $table->string('bukti_transfer'); 
 
            $table->enum('status', ['menunggu_verifikasi', 'terverifikasi', 'ditolak'])
                ->default('menunggu_verifikasi');
            $table->text('catatan_verifikasi')->nullable();
 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_ppdb');
    }
};
