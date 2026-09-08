<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Rute DELETE (hapus akun sendiri) DIBUANG - bawaan starter kit, bukan
    // kebutuhan sistem ini. users cascade ke pendaftaran_ppdb, yang cascade lagi
    // ke pembayaran_ppdb + tagihan_item: wali yang sudah mentransfer bisa
    // menghapus akunnya dengan modal kata sandinya sendiri, dan catatan uang
    // yang sudah masuk ikut lenyap - sekolah kehilangan buktinya, wali kehilangan
    // dasar klaimnya. Wali yang mengundurkan diri punya jalur yang benar: staf
    // menutup pendaftarannya lewat StafPpdb\PendaftaranController::tutup(),
    // kursi kuota lepas, riwayat tetap utuh. Jangan dihidupkan lagi.

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Rute "settings/appearance" dihapus: aplikasi ini terang saja.
});
