<?php

use App\Http\Controllers\SuperAdmin\PenggunaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Modul Super Admin.
|
| Perhatikan: TIDAK ADA route DELETE di berkas ini, dan itu disengaja. Foreign
| key dari users dan gelombang_ppdb ke pendaftaran_ppdb bersifat cascade, dan
| pendaftaran_ppdb sendiri cascade ke pembayaran_ppdb - jadi satu penghapusan
| di modul ini bisa memusnahkan ledger transfer tanpa jejak. Yang dipakai
| menonaktifkan (status_aktif), bukan menghapus. Kalau suatu saat ada route
| DELETE muncul di sini, kemungkinan besar itu keliru.
*/
Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('super-admin/dashboard');
        })->name('dashboard');

        Route::get('/pengguna', [PenggunaController::class, 'index'])
            ->name('pengguna.index');

        Route::get('/pengguna/tambah', [PenggunaController::class, 'create'])
            ->name('pengguna.create');

        Route::post('/pengguna', [PenggunaController::class, 'store'])
            ->name('pengguna.store');

        Route::get('/pengguna/{pengguna}/ubah', [PenggunaController::class, 'edit'])
            ->name('pengguna.edit');

        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])
            ->name('pengguna.update');

        // Mencabut/mengembalikan hak masuk. Ini pengganti "hapus akun" - lihat
        // komentar panjang di PenggunaController.
        Route::post('/pengguna/{pengguna}/status', [PenggunaController::class, 'status'])
            ->name('pengguna.status');
    });
