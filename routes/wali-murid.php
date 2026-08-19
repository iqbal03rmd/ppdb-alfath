<?php

use App\Http\Controllers\WaliMurid\PendaftaranController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:wali_murid'])
    ->prefix('wali-murid')
    ->name('wali-murid.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('wali-murid/dashboard');
        })->name('dashboard');

        Route::get('/formulir', [PendaftaranController::class, 'create'])->name('formulir');
        Route::post('/formulir', [PendaftaranController::class, 'store'])->name('formulir.store');

        // Sementara placeholder, controller aslinya kita bikin di langkah berikutnya
        Route::get('/unggah-berkas/{pendaftaran}', function (\App\Models\PendaftaranPpdb $pendaftaran) {
            return Inertia::render('wali-murid/unggah-berkas', [
                'pendaftaran' => $pendaftaran,
            ]);
        })->name('unggah-berkas');
    });