<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StorePembayaranPendaftaranAwalRequest;
use App\Models\PembayaranPendaftaranAwal;
use App\Models\PengaturanSistem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BiayaPendaftaranController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $pengaturan = PengaturanSistem::saatIni();
        $tiket = $user->tiketPendaftaranTersedia()->oldest()->first();
        $pending = $user->pembayaranPendaftaranAwal()
            ->where('status', 'menunggu_verifikasi')
            ->latest()
            ->first();
        $terakhir = $user->pembayaranPendaftaranAwal()->latest()->first();

        $status = match (true) {
            $tiket !== null => 'siap_digunakan',
            $pending !== null => 'menunggu_verifikasi',
            $terakhir?->status === 'ditolak' => 'ditolak',
            default => 'belum_bayar',
        };

        return Inertia::render('wali-murid/biaya-pendaftaran', [
            'status' => $status,
            'biaya' => (int) $pengaturan->biaya_pendaftaran_awal,
            'bisaMengirim' => $tiket === null
                && $pending === null
                && $pengaturan->informasiRekeningLengkap(),
            'informasiPembayaran' => $pengaturan->informasiRekeningLengkap() ? [
                'nama_bank' => $pengaturan->nama_bank,
                'nomor_rekening' => $pengaturan->nomor_rekening,
                'nama_pemilik_rekening' => $pengaturan->nama_pemilik_rekening,
                'instruksi' => $pengaturan->instruksi_pembayaran,
            ] : null,
            'catatanPenolakan' => $status === 'ditolak' ? $terakhir?->catatan_verifikasi : null,
            'riwayat' => $user->pembayaranPendaftaranAwal()
                ->with('pendaftaran:id,nama_pendaftar,nomor_pendaftaran')
                ->latest()
                ->get()
                ->map(fn (PembayaranPendaftaranAwal $p) => [
                    'id' => $p->id,
                    'nominal_transfer' => $p->nominal_transfer,
                    'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                    'status' => $p->status,
                    'catatan_verifikasi' => $p->catatan_verifikasi,
                    'digunakan_untuk' => $p->pendaftaran ? [
                        'nama' => $p->pendaftaran->nama_pendaftar,
                        'nomor' => $p->pendaftaran->nomor_pendaftaran,
                    ] : null,
                ]),
        ]);
    }

    public function store(StorePembayaranPendaftaranAwalRequest $request): RedirectResponse
    {
        $pengaturan = PengaturanSistem::saatIni();

        abort_unless(
            $pengaturan->informasiRekeningLengkap(),
            422,
            'Rekening pembayaran belum dilengkapi sekolah. Hubungi Staf PPDB sebelum melakukan transfer.'
        );

        $path = $request->file('bukti_transfer')->store('bukti-biaya-pendaftaran', 'public');

        try {
            DB::transaction(function () use ($request, $pengaturan, $path): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

                abort_if(
                    $user->tiketPendaftaranTersedia()->exists(),
                    403,
                    'Anda sudah memiliki pembayaran yang disetujui dan belum digunakan untuk mendaftarkan anak.'
                );

                abort_if(
                    $user->pembayaranPendaftaranAwal()->where('status', 'menunggu_verifikasi')->exists(),
                    403,
                    'Masih ada bukti pembayaran yang menunggu diperiksa Staf PPDB.'
                );

                $user->pembayaranPendaftaranAwal()->create([
                    'nominal_tagihan' => (int) $pengaturan->biaya_pendaftaran_awal,
                    'nominal_transfer' => $request->integer('nominal_transfer'),
                    'tanggal_transfer' => $request->date('tanggal_transfer'),
                    'bukti_transfer' => $path,
                    'status' => 'menunggu_verifikasi',
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        return to_route('wali-murid.biaya-pendaftaran.show')
            ->with('success', 'Bukti pembayaran dikirim dan sedang menunggu verifikasi Staf PPDB.');
    }
}
