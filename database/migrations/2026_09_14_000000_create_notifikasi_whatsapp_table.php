<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_whatsapp', function (Blueprint $table) {
            $table->id();

            // Riwayat notifikasi tetap berguna sebagai audit walau referensi
            // asalnya suatu saat hilang, jadi foreign key dibuat nullable dan
            // tidak ikut menghapus baris notifikasi.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pendaftaran_ppdb_id')->nullable()->constrained('pendaftaran_ppdb')->nullOnDelete();

            $table->string('jenis');
            $table->string('idempotency_key')->unique();
            $table->string('nomor_tujuan')->nullable();
            $table->text('pesan');
            $table->string('status')->default('menunggu')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('provider_request_id')->nullable();
            $table->unsignedSmallInteger('jumlah_percobaan')->default(0);
            $table->text('keterangan_status')->nullable();
            $table->timestamp('diterima_gateway_pada')->nullable();
            $table->timestamp('gagal_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_whatsapp');
    }
};
