<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ubahPilihanStatus([
            'draft', 'diajukan', 'diverifikasi', 'pembayaran', 'perlu_perbaikan', 'diterima', 'ditolak',
        ]);

        DB::table('pendaftaran_ppdb')
            ->where('status', 'diverifikasi')
            ->update(['status' => 'pembayaran']);

        $this->ubahPilihanStatus([
            'draft', 'diajukan', 'pembayaran', 'perlu_perbaikan', 'diterima', 'ditolak',
        ]);
    }

    public function down(): void
    {
        $this->ubahPilihanStatus([
            'draft', 'diajukan', 'diverifikasi', 'pembayaran', 'perlu_perbaikan', 'diterima', 'ditolak',
        ]);

        DB::table('pendaftaran_ppdb')
            ->where('status', 'pembayaran')
            ->update(['status' => 'diverifikasi']);

        $this->ubahPilihanStatus([
            'draft', 'diajukan', 'diverifikasi', 'perlu_perbaikan', 'diterima', 'ditolak',
        ]);
    }

    private function ubahPilihanStatus(array $status): void
    {
        Schema::table('pendaftaran_ppdb', function (Blueprint $table) use ($status) {
            $table->enum('status', $status)->default('draft')->change();
        });
    }
};
