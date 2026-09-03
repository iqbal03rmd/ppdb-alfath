<?php

use App\Http\Controllers\StafPpdb\VerifikasiPembayaranController;
use App\Http\Controllers\StafPpdb\VerifikasiPendaftaranController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:staf_ppdb'])
    ->prefix('staf-ppdb')
    ->name('staf-ppdb.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('staf-ppdb/dashboard');
        })->name('dashboard');

        // Antrian pendaftaran menunggu diverifikasi (formulir + berkas). Aksi setujui/minta perbaikan
        // menyusul di langkah berikutnya - halaman ini baru menampilkan daftar.
        Route::get('/verifikasi-pendaftaran', [VerifikasiPendaftaranController::class, 'index'])
            ->name('verifikasi-pendaftaran.index');

        Route::get('/verifikasi-pendaftaran/{pendaftaran}', [VerifikasiPendaftaranController::class, 'show'])
            ->name('verifikasi-pendaftaran.show');

        Route::post('/verifikasi-pendaftaran/{pendaftaran}/setujui', [VerifikasiPendaftaranController::class, 'setujui'])
            ->name('verifikasi-pendaftaran.setujui');

        Route::post('/verifikasi-pendaftaran/{pendaftaran}/minta-perbaikan', [VerifikasiPendaftaranController::class, 'mintaPerbaikan'])
            ->name('verifikasi-pendaftaran.minta-perbaikan');

        // Antrian bukti transfer. Satu baris = satu transfer, bukan satu
        // pendaftaran - wali yang mencicil muncul beberapa kali, dan memang
        // tiap transfernya diperiksa sendiri-sendiri.
        Route::get('/verifikasi-pembayaran', [VerifikasiPembayaranController::class, 'index'])
            ->name('verifikasi-pembayaran.index');

        Route::get('/verifikasi-pembayaran/{pembayaran}', [VerifikasiPembayaranController::class, 'show'])
            ->name('verifikasi-pembayaran.show');

        Route::post('/verifikasi-pembayaran/{pembayaran}/sahkan', [VerifikasiPembayaranController::class, 'sahkan'])
            ->name('verifikasi-pembayaran.sahkan');

        // Dipakai juga buat MEMBATALKAN pengesahan yang terlanjur salah - lihat
        // komentar di controller-nya.
        Route::post('/verifikasi-pembayaran/{pembayaran}/tolak', [VerifikasiPembayaranController::class, 'tolak'])
            ->name('verifikasi-pembayaran.tolak');
    });