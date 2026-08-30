<?php

use App\Http\Controllers\WaliMurid\DokumenController;
use App\Http\Controllers\WaliMurid\PembayaranController;
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

        Route::prefix('pendaftaran')->name('pendaftaran.')->group(function () {
            Route::get('/', [PendaftaranController::class, 'index'])->name('index');
            Route::get('/create', [PendaftaranController::class, 'create'])->name('create');
            Route::post('/', [PendaftaranController::class, 'store'])->name('store');
            Route::get('/{pendaftaran}', [PendaftaranController::class, 'show'])->name('show');
            Route::get('/{pendaftaran}/edit', [PendaftaranController::class, 'edit'])->name('edit');
            Route::put('/{pendaftaran}', [PendaftaranController::class, 'update'])->name('update');
            Route::post('/{pendaftaran}/kirim-perbaikan', [PendaftaranController::class, 'submitPerbaikan'])->name('kirim-perbaikan');

            // Unggah Berkas, nested di bawah pendaftaran karena memang sub-resource-nya
            Route::get('/{pendaftaran}/unggah-berkas', [DokumenController::class, 'index'])->name('unggah-berkas');
            Route::post('/{pendaftaran}/unggah-berkas', [DokumenController::class, 'store'])->name('unggah-berkas.store');
            Route::post('/{pendaftaran}/unggah-berkas/submit', [DokumenController::class, 'submit'])->name('unggah-berkas.submit');
        });

        // Pembayaran - namespace sendiri (sejajar pendaftaran.*), karena juga
        // diakses langsung dari menu sidebar, bukan cuma dari dalam satu pendaftaran.
        Route::prefix('pembayaran')->name('pembayaran.')->group(function () {
            Route::get('/', [PembayaranController::class, 'index'])->name('index');
            Route::get('/{pendaftaran}', [PembayaranController::class, 'show'])->name('show');
            Route::post('/{pendaftaran}', [PembayaranController::class, 'store'])->name('store');
        });

    });