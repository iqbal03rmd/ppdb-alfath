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
        Schema::create('gelombang_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            // Jatuh tempo MINIMAL BAYAR (bukan pelunasan). Dasar staf untuk
            // menetapkan status 'ditolak' kalau sampai tanggal ini pembayaran
            // belum mencapai kebijakan_kategori.minimal_bayar milik jalurnya.
            // Beda dari tanggal_selesai, yang menutup jendela PENDAFTARAN; beda
            // pula dari tahun_ajaran.batas_pelunasan, yang menagih sisa cicilan
            // dan TIDAK memicu penolakan.
            $table->date('batas_waktu_pembayaran')->nullable();
            // NOMINAL MINIMAL BAWAAN yang dulu ada di sini DIBUANG 11 September
            // 2026 (keputusan user). Minimal bayar sekarang SATU sumber saja -
            // kebijakan_kategori.minimal_bayar, per gelombang x jalur - jadi
            // tidak ada lagi dua tempat yang bisa berbeda pendapat, dan tidak
            // ada angka yang berlaku diam-diam tanpa pernah diketik Admin.
            //
            // Kolom 'minimal_bayar_persen_yatim' sudah lebih dulu dibuang
            // 8 September 2026: namanya menyebut satu jalur sehingga jalur baru
            // yang ditambahkan Admin lewat UI tidak akan pernah kebagian.
            $table->boolean('status_buka')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gelombang_ppdb');
    }
};
