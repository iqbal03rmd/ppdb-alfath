<?php

use App\Http\Controllers\KepalaSekolah\LaporanController;
use Illuminate\Support\Facades\Route;

/*
| Modul Kepala Sekolah - MONITORING SAJA.
|
| Perhatikan: seluruhnya GET. Tidak ada satu pun POST/PUT/DELETE di modul ini,
| dan itu memang disengaja - Kepala Sekolah tidak ikut memutuskan diterima atau
| ditolak (PRD 8.3, keputusan 1 September 2026). Kalau suatu saat ada route
| non-GET muncul di berkas ini, kemungkinan besar itu keliru.
*/
Route::middleware(['auth', 'role:kepala_sekolah'])
    ->prefix('kepala-sekolah')
    ->name('kepala-sekolah.')
    ->group(function () {
        Route::get('/dashboard', [LaporanController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/rekapitulasi', [LaporanController::class, 'rekapitulasi'])
            ->name('rekapitulasi');
    });
