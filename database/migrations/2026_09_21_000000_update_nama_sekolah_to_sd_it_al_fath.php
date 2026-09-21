<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengaturan_sistem')
            ->where('nama_sekolah', 'SDIT Al-Fath')
            ->update([
                'nama_sekolah' => 'SD IT AL FATH',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('pengaturan_sistem')
            ->where('nama_sekolah', 'SD IT AL FATH')
            ->update([
                'nama_sekolah' => 'SDIT Al-Fath',
                'updated_at' => now(),
            ]);
    }
};
