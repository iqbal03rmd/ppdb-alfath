<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komponen biaya - daftar GLOBAL, bukan milik satu gelombang.
     *
     * Dulu tiap gelombang punya salinan komponennya sendiri
     * (komponen_biaya.gelombang_ppdb_id). Itu berarti "Pembangunan" di
     * Gelombang 1 dan di Gelombang 2 adalah dua baris berbeda yang tidak saling
     * kenal - laporan tidak bisa menjumlahkan uang pangkal lintas angkatan, dan
     * tiap gelombang baru harus mengetik ulang seluruh daftarnya.
     *
     * Sekarang komponennya satu daftar; yang berbeda tiap gelombang cuma
     * NOMINALNYA, dan itu tinggal di tarif_kategori.
     */
    public function up(): void
    {
        Schema::create('komponen_biaya', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('keterangan')->nullable();

            // Urutan tampil di rincian tagihan yang dibaca wali. Perlu eksplisit
            // begitu komponennya jadi daftar global: tanpa ini urutannya ikut id,
            // jadi komponen yang ditambahkan belakangan selalu nempel di bawah
            // walau seharusnya di tengah.
            $table->unsignedSmallInteger('urutan')->default(0);

            // Saklar penuh DUA ARAH - beda dari status_aktif milik tahun ajaran
            // dan gelombang, yang cuma penunjuk "yang mana yang sedang jalan".
            // Di sini boleh nol yang aktif, boleh semuanya aktif.
            //
            // Gunanya menggantikan penghapusan: pos yang sudah tidak ditagih
            // sekolah dimatikan, bukan dibuang. Menghapusnya ikut menghapus
            // seluruh baris tarifnya (cascade), dan itu mengubah total tagihan
            // pendaftar yang tagihannya belum sempat terbit. Dimatikan, dia
            // cuma berhenti ikut ke tagihan BARU - tagihan yang sudah terbit
            // tidak bergeser sepeser pun, karena tagihan_item menyimpan nama
            // dan nominalnya sebagai salinan, bukan foreign key ke sini.
            $table->boolean('status_aktif')->default(true);


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_biaya');
    }
};
