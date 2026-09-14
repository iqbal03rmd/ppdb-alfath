<?php

use App\Models\GelombangPpdb;
use App\Models\PengaturanSistem;
use App\Rules\NomorWhatsApp;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $pengaturan = PengaturanSistem::saatIni();
    $gelombang = GelombangPpdb::with('tahunAjaran')
        ->menerimaPendaftar()
        ->latest('tanggal_mulai')
        ->first();
    $nomorWhatsApp = NomorWhatsApp::normalisasi($pengaturan->whatsapp_kontak);

    return Inertia::render('welcome', [
        'pengaturan' => [
            'nama_sekolah' => $pengaturan->nama_sekolah,
            'tagline' => $pengaturan->tagline,
            'alamat' => $pengaturan->alamat,
            'telepon' => $pengaturan->telepon,
            'email' => $pengaturan->email,
            'judul_landing' => $pengaturan->judul_landing,
            'deskripsi_landing' => $pengaturan->deskripsi_landing,
            'pengumuman_landing' => $pengaturan->pengumuman_landing,
            'whatsapp_kontak' => $pengaturan->whatsapp_kontak,
            'whatsapp_url' => $nomorWhatsApp ? "https://wa.me/{$nomorWhatsApp}" : null,
        ],
        // Jadwal tidak disalin ke Pengaturan Sistem. Landing membaca langsung
        // gelombang yang sungguh sedang menerima pendaftar.
        'gelombang' => $gelombang ? [
            'nama' => $gelombang->nama,
            'tahun_ajaran' => $gelombang->tahunAjaran->nama,
            'tanggal_mulai' => $gelombang->tanggal_mulai->locale('id')->translatedFormat('d F Y'),
            'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
        ] : null,
    ]);
})->name('home');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/wali-murid.php';
require __DIR__.'/staf-ppdb.php';
require __DIR__.'/kepala-sekolah.php';
require __DIR__.'/super-admin.php';
