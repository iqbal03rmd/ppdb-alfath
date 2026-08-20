<?php

use App\Http\Controllers\WaliMurid\PendaftaranController;
use App\Http\Controllers\WaliMurid\DokumenController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\PendaftaranPpdb;

Route::middleware(['auth', 'role:wali_murid'])
    ->prefix('wali-murid')
    ->name('wali-murid.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('wali-murid/dashboard');
        })->name('dashboard');

        Route::get('/formulir', [PendaftaranController::class, 'create'])->name('formulir');
        Route::post('/formulir', [PendaftaranController::class, 'store'])->name('formulir.store');

        Route::get('/unggah-berkas/{pendaftaran}', [DokumenController::class, 'index'])->name('unggah-berkas');
        Route::post('/unggah-berkas/{pendaftaran}', [DokumenController::class, 'store'])->name('unggah-berkas.store');
        Route::post('/unggah-berkas/{pendaftaran}/submit', [DokumenController::class, 'submit'])->name('unggah-berkas.submit');

        // Sementara placeholder, controller aslinya kita bikin di langkah berikutnya
        Route::get('/status-pendaftaran/{pendaftaran}', function (PendaftaranPpdb $pendaftaran) {
            return Inertia::render('wali-murid/status-pendaftaran', [
                'pendaftaran' => $pendaftaran,
            ]);
        })->name('status-pendaftaran');
    });