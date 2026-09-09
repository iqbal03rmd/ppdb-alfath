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
            // belum menyentuh minimal_pembayaran di bawah. Beda dari
            // tanggal_selesai, yang menutup jendela PENDAFTARAN; beda pula dari
            // tahun_ajaran.batas_pelunasan, yang menagih sisa cicilan dan TIDAK
            // memicu penolakan.
            $table->date('batas_waktu_pembayaran')->nullable();
            // Nominal minimal BAWAAN yang harus terbayar supaya pendaftaran
            // berstatus 'diterima'. Sisanya boleh dicicil sampai
            // tahun_ajaran.batas_pelunasan.
            //
            // Ini nilai BAWAAN, bukan satu-satunya: jalur yang butuh angka lain
            // (mis. Anak Yatim, yang dibebaskan uang pendaftaran & uang pangkal
            // sehingga tagihannya jauh lebih kecil) mengisi
            // kebijakan_kategori.minimal_bayar miliknya sendiri.
            //
            // Kolom 'minimal_bayar_persen_yatim' yang dulu ada di sini DIBUANG
            // 8 September 2026. Dua sebabnya: namanya menyebut satu jalur
            // sehingga jalur baru yang ditambahkan Admin lewat UI tidak akan
            // pernah kebagian, dan persentase lebih susah dijelaskan ke wali
            // daripada nominal. Penggantinya nominal per jalur.
            $table->unsignedBigInteger('minimal_pembayaran')->nullable();
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
