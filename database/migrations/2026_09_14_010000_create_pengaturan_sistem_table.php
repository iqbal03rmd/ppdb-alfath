<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_sistem', function (Blueprint $table) {
            $table->id();

            // Satu baris global untuk identitas sekolah, informasi pembayaran,
            // dan teks landing page yang memang mungkin berubah. Jadwal PPDB
            // tetap dibaca dari gelombang agar tidak punya sumber kedua.
            $table->string('nama_sekolah', 150);
            $table->string('tagline', 200)->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email')->nullable();

            $table->string('nama_bank', 100)->nullable();
            $table->string('nomor_rekening', 100)->nullable();
            $table->string('nama_pemilik_rekening', 150)->nullable();
            $table->text('instruksi_pembayaran')->nullable();

            // Berlaku global untuk seluruh gelombang. Scheduler membaca angka
            // ini, lalu mencocokkannya dengan tenggat milik gelombang masing-masing.
            $table->unsignedTinyInteger('hari_pengingat_jatuh_tempo')->default(7);

            $table->string('judul_landing', 200);
            $table->text('deskripsi_landing');
            $table->text('pengumuman_landing')->nullable();
            $table->string('whatsapp_kontak', 30)->nullable();
            $table->timestamps();
        });

        // Baris singleton langsung tersedia sesudah deploy. Informasi yang
        // belum diketahui sengaja null dan dapat dilengkapi Super Admin.
        DB::table('pengaturan_sistem')->insert([
            'nama_sekolah' => 'SD IT AL FATH',
            'tagline' => 'Mendidik Generasi Berilmu, Beriman, dan Berakhlak',
            'judul_landing' => 'Penerimaan Peserta Didik Baru',
            'deskripsi_landing' => 'Daftarkan putra-putri Anda secara daring dan pantau seluruh proses PPDB dalam satu tempat.',
            'hari_pengingat_jatuh_tempo' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_sistem');
    }
};
