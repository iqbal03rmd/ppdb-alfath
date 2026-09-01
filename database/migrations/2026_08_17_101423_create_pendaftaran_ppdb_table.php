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
        Schema::create('pendaftaran_ppdb', function (Blueprint $table) {
            $table->id();
            // Relasi
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('gelombang_ppdb_id')
                ->constrained('gelombang_ppdb')
                ->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')
                ->constrained('kategori_siswa');
            $table->foreignId('diverifikasi_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Nomor pendaftaran
            $table->string('nomor_pendaftaran')->unique();
            // Data calon peserta didik
            $table->string('nama_pendaftar');
            $table->string('nik')->nullable();
            $table->date('tanggal_lahir');
            $table->string('tempat_lahir');
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan']);
            $table->string('agama')->nullable();
            $table->text('alamat');
            // Data pendukung klaim kategori
            $table->string('nama_saudara')->nullable();
            $table->string('nama_orang_tua_guru')->nullable();
            // Status pendaftaran
            $table->enum('status', [
                'draft',
                'diajukan',
                'diverifikasi',
                'perlu_perbaikan',
                'diterima',
                'ditolak',
            ])->default('draft');
            // Minimal bayar yang DIBEKUKAN buat pendaftaran ini, dihitung sekali
            // bersamaan dengan penerbitan tagihan_item. Alasannya sama dengan
            // snapshot tagihan: kebijakan yang diubah Admin belakangan nggak
            // boleh mengubah kewajiban orang yang tagihannya sudah terbit.
            // Null = tagihan belum terbit.
            $table->unsignedBigInteger('minimal_bayar')->nullable();
            // Catatan dari staf PPDB
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendaftaran_ppdb');
    }
};
