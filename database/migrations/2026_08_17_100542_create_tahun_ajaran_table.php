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
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama'); 
            $table->boolean('status_aktif')->default(false);
            $table->year('tahun_mulai');
            // Tenggat PELUNASAN sisa cicilan, berlaku lintas gelombang: wali
            // yang daftar di Gelombang 1 maupun 2 punya tanggal jatuh tempo
            // yang sama. Sengaja di tahun ajaran, bukan di gelombang - kalau
            // diturunkan dari "gelombang terakhir", tenggat semua orang ikut
            // mundur diam-diam tiap admin menambah gelombang baru.
            //
            // WAJIB, bukan nullable: tanggal ini dicetak ke halaman Pembayaran
            // dan Beranda wali sebagai pengingat kapan cicilan harus lunas.
            // Boleh kosong berarti sebagian wali melihat kalimat pengingat yang
            // tidak menyebut tanggal apa pun - pengingat yang tidak mengingatkan.
            $table->date('batas_pelunasan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
    }
};
