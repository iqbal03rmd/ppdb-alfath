<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menendang keluar pengguna yang akunnya dinonaktifkan Super Admin.
 *
 * Dipasang di GRUP web, bukan cuma di route bermodul, dan itu disengaja:
 * penonaktifan yang cuma berlaku di sebagian halaman bukan penonaktifan.
 * Kalau ditaruh menempel di 'role:' saja, akun mati masih bisa membuka
 * Pengaturan Profil dan mengganti email dirinya sendiri.
 *
 * Bekerja bersama LoginRequest::authenticate(): di sana pintu masuknya yang
 * ditutup, di sini sesi yang TERLANJUR hidup dihabisi. Dua-duanya perlu -
 * tanpa yang ini, staf yang dinonaktifkan siang hari tetap bisa memverifikasi
 * pembayaran sampai dia sendiri menekan Keluar.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->status_aktif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                'Akun Anda sedang dinonaktifkan. Hubungi admin sekolah kalau ini keliru.'
            );
        }

        return $next($request);
    }
}
