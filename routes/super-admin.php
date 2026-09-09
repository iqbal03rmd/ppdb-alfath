<?php

use App\Http\Controllers\SuperAdmin\BerkasPersyaratanController;
use App\Http\Controllers\SuperAdmin\GelombangController;
use App\Http\Controllers\SuperAdmin\JalurController;
use App\Http\Controllers\SuperAdmin\KomponenBiayaController;
use App\Http\Controllers\SuperAdmin\PenggunaController;
use App\Http\Controllers\SuperAdmin\TahunAjaranController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Modul Super Admin.
|
| Route DELETE cuma ada dua, dan dua-duanya pada data yang TIDAK memegang uang:
| komponen biaya dan jalur pendaftaran. Foreign key-nya sengaja tidak cascade,
| jadi database sendiri menolak menghapus yang sudah dipakai pendaftaran atau
| tagihan - controller-nya menangkap penolakan itu jadi pesan yang bisa dibaca.
|
| Yang ketiga berkas persyaratan, dengan syarat sama: belum pernah diunggah.
|
| Yang TIDAK boleh punya DELETE: pengguna, tahun ajaran, dan gelombang. Ketiganya
| cascade sampai ke pendaftaran_ppdb -> pembayaran_ppdb, jadi satu penghapusan
| bisa memusnahkan ledger transfer tanpa jejak. Yang dipakai di sana menonaktifkan
| atau menutup, bukan menghapus.
*/
Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('super-admin/dashboard');
        })->name('dashboard');

        Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/tambah', [PenggunaController::class, 'create'])->name('pengguna.create');
        Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        Route::get('/pengguna/{pengguna}/ubah', [PenggunaController::class, 'edit'])->name('pengguna.edit');
        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])->name('pengguna.update');

        // Mencabut/mengembalikan hak masuk. Ini pengganti "hapus akun".
        Route::post('/pengguna/{pengguna}/status', [PenggunaController::class, 'status'])->name('pengguna.status');

        /*
        | Konfigurasi PPDB - lima menu yang berdiri sendiri, bukan satu halaman
        | induk yang menyembunyikan sisanya. Urutannya di sidebar mengikuti
        | urutan PENGISIAN:
        |
        |   tahun ajaran -> komponen biaya -> berkas persyaratan -> jalur
        |   -> GELOMBANG
        |
        | Gelombang di paling belakang karena dialah yang merakit keempatnya jadi
        | satu angkatan: jadwal, kuota & minimal bayar tiap jalur, berkas wajib
        | tiap jalur, dan nominal tiap komponen. Empat yang di depan cuma
        | mendefinisikan bahannya.
        */
        Route::prefix('konfigurasi')->group(function () {
            // Tambah dan ubah tahun ajaran tidak punya halaman sendiri -
            // formulirnya modal di atas daftarnya, jadi yang tersisa cuma route
            // yang benar-benar menyimpan. Datanya sudah ikut terkirim di index.
            //
            // Status aktif ikut berpindah lewat store/update: centang "Aktifkan
            // tahun ajaran ini" di modal, dan TahunAjaran::aktifkan() yang
            // mematikan sisanya. Tidak ada route "nonaktifkan" - status aktif
            // itu penunjuk, bukan saklar, dan nol yang aktif bikin Beranda
            // Kepala Sekolah kosong.
            Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
            Route::post('/tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
            Route::put('/tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])->name('tahun-ajaran.update');

            // Formulirnya modal juga, sama seperti tahun ajaran. DELETE-nya tetap
            // ada - tapi cuma buat komponen yang belum punya nominal sama sekali.
            // Yang sudah dipakai DIMATIKAN lewat status_aktif, bukan dihapus.
            Route::get('/komponen-biaya', [KomponenBiayaController::class, 'index'])->name('komponen-biaya.index');
            Route::post('/komponen-biaya', [KomponenBiayaController::class, 'store'])->name('komponen-biaya.store');
            Route::put('/komponen-biaya/{komponenBiaya}', [KomponenBiayaController::class, 'update'])->name('komponen-biaya.update');
            Route::delete('/komponen-biaya/{komponenBiaya}', [KomponenBiayaController::class, 'destroy'])->name('komponen-biaya.destroy');

            // Daftar master jenis berkas. Yang diatur di sini cuma PILIHANNYA;
            // jalur mana yang mewajibkan apa tetap di Jalur Pendaftaran.
            // DELETE-nya cuma buat jenis yang belum pernah diunggah siapa pun -
            // yang sudah dipakai DINONAKTIFKAN, biar berkas yang telanjur masuk
            // tidak kehilangan namanya.
            Route::get('/berkas-persyaratan', [BerkasPersyaratanController::class, 'index'])->name('berkas-persyaratan.index');
            Route::post('/berkas-persyaratan', [BerkasPersyaratanController::class, 'store'])->name('berkas-persyaratan.store');
            Route::put('/berkas-persyaratan/{berkasPersyaratan}', [BerkasPersyaratanController::class, 'update'])->name('berkas-persyaratan.update');
            Route::delete('/berkas-persyaratan/{berkasPersyaratan}', [BerkasPersyaratanController::class, 'destroy'])->name('berkas-persyaratan.destroy');

            Route::get('/gelombang', [GelombangController::class, 'index'])->name('gelombang.index');
            Route::get('/gelombang/tambah', [GelombangController::class, 'create'])->name('gelombang.create');
            Route::post('/gelombang', [GelombangController::class, 'store'])->name('gelombang.store');
            Route::get('/gelombang/{gelombang}/ubah', [GelombangController::class, 'edit'])->name('gelombang.edit');
            Route::put('/gelombang/{gelombang}', [GelombangController::class, 'update'])->name('gelombang.update');
            Route::post('/gelombang/{gelombang}/status', [GelombangController::class, 'status'])->name('gelombang.status');

            // Formulirnya modal juga - tidak ada halaman tambah/ubah sendiri.
            Route::get('/jalur', [JalurController::class, 'index'])->name('jalur.index');
            Route::post('/jalur', [JalurController::class, 'store'])->name('jalur.store');
            Route::put('/jalur/{jalur}', [JalurController::class, 'update'])->name('jalur.update');
            Route::delete('/jalur/{jalur}', [JalurController::class, 'destroy'])->name('jalur.destroy');

            // DIPARKIR: belum dituju layar mana pun sejak formulir jalur jadi
            // modal. Nominal bersifat gelombang x jalur, jadi tempatnya nanti di
            // layar Ubah Gelombang - bareng kuota, minimal bayar, dan berkas
            // wajib. Sengaja tidak dibuang: aturan "kosong bukan nol" di
            // dalamnya gampang salah kalau ditulis ulang dari nol.
            Route::put('/jalur/{jalur}/tarif', [JalurController::class, 'simpanTarif'])->name('jalur.tarif');
        });
    });
