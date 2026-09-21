<?php

namespace App\Http\Controllers\StafPpdb;

use App\Http\Controllers\Controller;
use App\Models\PembayaranPendaftaranAwal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VerifikasiBiayaPendaftaranController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('staf-ppdb/verifikasi-biaya-pendaftaran', [
            'antrian' => PembayaranPendaftaranAwal::with('wali:id,name,email,telepon')
                ->where('status', 'menunggu_verifikasi')
                ->oldest()
                ->get()
                ->map(fn (PembayaranPendaftaranAwal $p) => [
                    'id' => $p->id,
                    'nama_wali' => $p->wali->name,
                    'email' => $p->wali->email,
                    'nominal_transfer' => $p->nominal_transfer,
                    'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                    'menunggu_sejak' => $p->created_at->locale('id')->translatedFormat('d F Y'),
                ]),
        ]);
    }

    public function show(PembayaranPendaftaranAwal $pembayaran): Response
    {
        $pembayaran->load(['wali:id,name,email,telepon', 'pendaftaran:id,nama_pendaftar,nomor_pendaftaran', 'diverifikasiOleh:id,name']);

        return Inertia::render('staf-ppdb/verifikasi-biaya-pendaftaran-show', [
            'pembayaran' => [
                'id' => $pembayaran->id,
                'status' => $pembayaran->status,
                'nominal_tagihan' => $pembayaran->nominal_tagihan,
                'nominal_transfer' => $pembayaran->nominal_transfer,
                'tanggal_transfer' => $pembayaran->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                'diunggah_pada' => $pembayaran->created_at->locale('id')->translatedFormat('d F Y H:i'),
                'catatan_verifikasi' => $pembayaran->catatan_verifikasi,
                'diperiksa_oleh' => $pembayaran->diverifikasiOleh?->name,
                'bukti_url' => Storage::url($pembayaran->bukti_transfer),
                'bukti_gambar' => in_array(
                    strtolower(pathinfo($pembayaran->bukti_transfer, PATHINFO_EXTENSION)),
                    ['jpg', 'jpeg', 'png'],
                    true
                ),
                'sudah_digunakan' => $pembayaran->digunakan_pada !== null,
            ],
            'wali' => [
                'nama' => $pembayaran->wali->name,
                'email' => $pembayaran->wali->email,
                'telepon' => $pembayaran->wali->telepon,
            ],
            'pendaftaran' => $pembayaran->pendaftaran ? [
                'nama' => $pembayaran->pendaftaran->nama_pendaftar,
                'nomor' => $pembayaran->pendaftaran->nomor_pendaftaran,
            ] : null,
        ]);
    }

    public function sahkan(Request $request, PembayaranPendaftaranAwal $pembayaran): RedirectResponse
    {
        DB::transaction(function () use ($request, $pembayaran): void {
            $target = PembayaranPendaftaranAwal::query()->lockForUpdate()->findOrFail($pembayaran->id);

            abort_unless($target->status === 'menunggu_verifikasi', 403, 'Pembayaran ini sudah pernah diputuskan.');
            abort_if(
                $target->nominal_transfer < $target->nominal_tagihan,
                422,
                'Nominal transfer belum mencapai biaya pendaftaran yang ditagihkan.'
            );

            $target->update([
                'status' => 'terverifikasi',
                'diverifikasi_oleh' => $request->user()->id,
                'diverifikasi_pada' => now(),
                'catatan_verifikasi' => null,
            ]);
        });

        return to_route('staf-ppdb.verifikasi-biaya-pendaftaran.index')
            ->with('success', 'Pembayaran disahkan. Wali sekarang dapat mengisi satu formulir pendaftaran anak.');
    }

    public function tolak(Request $request, PembayaranPendaftaranAwal $pembayaran): RedirectResponse
    {
        $data = $request->validate(
            ['catatan_verifikasi' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'catatan_verifikasi.required' => 'Tuliskan alasan penolakan agar wali tahu apa yang harus diperbaiki.',
                'catatan_verifikasi.min' => 'Alasan penolakan terlalu pendek.',
            ]
        );

        DB::transaction(function () use ($request, $pembayaran, $data): void {
            $target = PembayaranPendaftaranAwal::query()->lockForUpdate()->findOrFail($pembayaran->id);

            abort_unless(
                in_array($target->status, ['menunggu_verifikasi', 'terverifikasi'], true),
                403,
                'Pembayaran ini sudah ditolak sebelumnya.'
            );
            abort_if(
                $target->digunakan_pada !== null || $target->pendaftaran_ppdb_id !== null,
                403,
                'Pengesahan tidak dapat dibatalkan karena tiket sudah digunakan untuk membuat pendaftaran anak.'
            );

            $target->update([
                'status' => 'ditolak',
                'catatan_verifikasi' => $data['catatan_verifikasi'],
                'diverifikasi_oleh' => $request->user()->id,
                'diverifikasi_pada' => now(),
            ]);
        });

        return to_route('staf-ppdb.verifikasi-biaya-pendaftaran.index')
            ->with('success', 'Bukti pembayaran ditolak dan wali dapat mengunggah bukti baru.');
    }
}
