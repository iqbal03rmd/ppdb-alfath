<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        if (
            $request->user()->isDirty('notifikasi_whatsapp_aktif')
            && $request->user()->notifikasi_whatsapp_aktif
            && $request->user()->persetujuan_whatsapp_pada === null
        ) {
            $request->user()->persetujuan_whatsapp_pada = now();
        }

        $request->user()->save();

        return to_route('profile.edit')->with('success', 'Profil berhasil diperbarui.');
    }

    /*
     * destroy() SENGAJA TIDAK ADA - lihat komentar di routes/settings.php.
     * Ringkasnya: menghapus akun sendiri ikut memusnahkan pendaftaran dan
     * ledger pembayarannya lewat cascade. Jangan ditambahkan kembali.
     */
}
