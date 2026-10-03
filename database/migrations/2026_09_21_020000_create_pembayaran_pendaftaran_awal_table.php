<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_pendaftaran_awal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')->nullable()->constrained('kategori_siswa')->restrictOnDelete();
            $table->timestamp('reservasi_berakhir_pada')->nullable();
            $table->foreignId('pendaftaran_ppdb_id')->nullable()->unique()->constrained('pendaftaran_ppdb')->nullOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();

            // nominal_tagihan adalah snapshot kebijakan saat kursi direservasi;
            // nominal_transfer adalah pengakuan wali atas jumlah yang ditransfer.
            $table->unsignedBigInteger('nominal_tagihan');
            $table->unsignedBigInteger('nominal_transfer')->nullable();
            $table->date('tanggal_transfer')->nullable();
            $table->string('bukti_transfer')->nullable();
            $table->timestamp('bukti_dikirim_pada')->nullable();
            $table->enum('status', ['menunggu_pembayaran', 'menunggu_verifikasi', 'terverifikasi', 'ditolak'])
                ->default('menunggu_verifikasi');
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamp('digunakan_pada')->nullable();
            $table->timestamps();

            // Nama eksplisit dijaga pendek karena MySQL membatasi identifier
            // (termasuk nama index) maksimal 64 karakter.
            $table->index(['user_id', 'gelombang_ppdb_id', 'status'], 'ppa_user_gelombang_status_idx');
            $table->index(['user_id', 'digunakan_pada'], 'ppa_user_digunakan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_pendaftaran_awal');
    }
};
