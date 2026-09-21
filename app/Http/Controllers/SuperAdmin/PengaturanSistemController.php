<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanSistem;
use App\Rules\NomorWhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanSistemController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('super-admin/pengaturan-sistem', [
            'pengaturan' => PengaturanSistem::saatIni()->only(array_keys(PengaturanSistem::bawaan())),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_sekolah' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],

            // Ketiganya satu paket. Menampilkan nomor tanpa bank atau pemilik
            // justru membuat wali ragu apakah tujuan transfernya benar.
            'nama_bank' => ['nullable', 'required_with:nomor_rekening,nama_pemilik_rekening', 'string', 'max:100'],
            'nomor_rekening' => ['nullable', 'required_with:nama_bank,nama_pemilik_rekening', 'string', 'max:100'],
            'nama_pemilik_rekening' => ['nullable', 'required_with:nama_bank,nomor_rekening', 'string', 'max:150'],
            'instruksi_pembayaran' => ['nullable', 'string', 'max:2000'],

            'judul_landing' => ['required', 'string', 'max:200'],
            'deskripsi_landing' => ['required', 'string', 'max:2000'],
            'pengumuman_landing' => ['nullable', 'string', 'max:1000'],
            'whatsapp_kontak' => ['nullable', 'string', 'max:30', new NomorWhatsApp],
        ], [
            'nama_sekolah.required' => 'Nama sekolah wajib diisi.',
            'nama_bank.required_with' => 'Nama bank wajib dilengkapi bersama informasi rekening.',
            'nomor_rekening.required_with' => 'Nomor rekening wajib dilengkapi bersama informasi rekening.',
            'nama_pemilik_rekening.required_with' => 'Nama pemilik rekening wajib dilengkapi bersama informasi rekening.',
            'judul_landing.required' => 'Judul utama landing page wajib diisi.',
            'deskripsi_landing.required' => 'Deskripsi landing page wajib diisi.',
        ]);

        PengaturanSistem::tersimpan()->update($data);

        return to_route('super-admin.pengaturan-sistem.edit')
            ->with('success', 'Pengaturan sistem berhasil diperbarui.');
    }
}
