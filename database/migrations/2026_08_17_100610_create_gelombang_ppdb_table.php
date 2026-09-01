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
            // Nominal minimal yang harus terbayar supaya pendaftaran berstatus
            // 'diterima'. Sisanya boleh dicicil sampai tahun_ajaran.batas_pelunasan.
            $table->unsignedBigInteger('minimal_pembayaran')->nullable();
            // Pengecualian KHUSUS jalur Anak Yatim, yang dibebaskan uang
            // pendaftaran & uang pangkal sehingga tagihannya jauh lebih kecil -
            // nominal di atas mustahil dipenuhi jalur itu, jadi minimalnya
            // dihitung sebagai persen dari tagihannya sendiri.
            //
            // Sengaja dinamai eksplisit, bukan 'minimal_bayar_persen' yang
            // generik: kolom generik mengundang staf memasang persentase buat
            // jalur lain juga, padahal cuma jalur ini yang memakainya. Ditaruh
            // di sini bareng nominalnya supaya sekali bikin gelombang, semua
            // kebijakan minimal bayar selesai di satu tempat.
            $table->unsignedTinyInteger('minimal_bayar_persen_yatim')->nullable();
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
