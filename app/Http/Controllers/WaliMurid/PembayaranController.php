<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StorePembayaranRequest;
use App\Models\KomponenBiaya;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PembayaranController extends Controller
{
    /**
     * Statusnya sama kayak sudahBolehBayar di accordion pendaftaran-index.tsx -
     * pembayaran baru boleh dilakukan setelah berkas diverifikasi staf, biar
     * nggak ada duit "nyangkut" buat pendaftaran yang ternyata perlu diperbaiki.
     * 'ditolak' SENGAJA nggak dimasukin - itu statusnya khusus dipakai staf buat
     * nutup pendaftaran yang nggak dibayar sampai batas waktu, jadi pendaftaran
     * yang ditolak nggak seharusnya bisa diakses buat bayar lagi. Kalau bukti
     * transfer wali yang ditolak (bukan pendaftarannya), pendaftaran tetap di
     * status diverifikasi - itu ditangani lewat pembayaran.status, bukan di sini.
     */
    private const STATUS_BOLEH_BAYAR = ['diverifikasi', 'diterima'];

    /**
     * Riwayat Pembayaran - log semua transfer yang pernah diajukan wali ini,
     * lintas semua pendaftaran (anak) miliknya. Ini BUKAN halaman buat milih
     * pendaftaran yang mau dibayar (itu sekarang lewat accordion di
     * pendaftaran-index.tsx -> tombol "Bayar Sekarang" -> show() di bawah).
     */
    public function index(Request $request): Response
    {
        $riwayat = PembayaranPpdb::with('pendaftaran')
            ->whereHas('pendaftaran', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest('tanggal_transfer')
            ->get()
            ->map(fn (PembayaranPpdb $p) => [
                'id' => $p->id,
                'pendaftaran_id' => $p->pendaftaran_ppdb_id,
                'nomor_pendaftaran' => $p->pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $p->pendaftaran->nama_pendaftar,
                'nominal_transfer' => $p->nominal_transfer,
                'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                'status' => $p->status,
            ]);

        return Inertia::render('wali-murid/riwayat-pembayaran', [
            'riwayat' => $riwayat,
        ]);
    }

    public function show(PendaftaranPpdb $pendaftaran): Response|RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

        if ($redirect = $this->guardBolehBayar($pendaftaran)) {
            return $redirect;
        }

        $rincianTagihan = KomponenBiaya::with(['tarif' => fn ($q) => $q->where('kategori_siswa_id', $pendaftaran->kategori_siswa_id)])
            ->where('gelombang_ppdb_id', $pendaftaran->gelombang_ppdb_id)
            ->get()
            ->map(fn (KomponenBiaya $k) => [
                'nama' => $k->nama,
                'keterangan' => $k->keterangan,
                // Rp0 kalau Admin belum setting tarif buat kombinasi gelombang+kategori ini.
                'nominal' => $k->tarif->first()?->nominal ?? 0,
            ]);

        $totalTagihan = $rincianTagihan->sum('nominal');
        $totalTerbayar = $pendaftaran->totalTerbayar();
        $sisaTagihan = max(0, $totalTagihan - $totalTerbayar);
        $adaPending = $pendaftaran->pembayaran()->where('status', 'menunggu_verifikasi')->exists();

        $riwayatTransfer = $pendaftaran->pembayaran()
            ->latest('tanggal_transfer')
            ->get()
            ->map(fn (PembayaranPpdb $p) => [
                'nominal_transfer' => $p->nominal_transfer,
                'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                'bukti_transfer_url' => Storage::url($p->bukti_transfer),
                'status' => $p->status,
                'catatan_verifikasi' => $p->catatan_verifikasi,
            ]);

        return Inertia::render('wali-murid/pembayaran', [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
            ],
            'rincianTagihan' => $rincianTagihan,
            'totalTagihan' => $totalTagihan,
            'totalTerbayar' => $totalTerbayar,
            'sisaTagihan' => $sisaTagihan,
            'riwayatTransfer' => $riwayatTransfer,
            // Cicilan: boleh transfer lagi selama masih ada sisa DAN nggak ada
            // transfer lain yang masih menunggu diverifikasi staf (biar nggak
            // numpuk beberapa transfer pending sekaligus).
            'bisaBayar' => $sisaTagihan > 0 && ! $adaPending,
        ]);
    }

    public function store(StorePembayaranRequest $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

        if ($redirect = $this->guardBolehBayar($pendaftaran)) {
            return $redirect;
        }

        abort_if($pendaftaran->sisaTagihan() <= 0, 403, 'Tagihan pendaftaran ini sudah lunas.');
        abort_if(
            $pendaftaran->pembayaran()->where('status', 'menunggu_verifikasi')->exists(),
            403,
            'Masih ada transfer yang menunggu diverifikasi staf, tunggu sampai itu diproses dulu sebelum mengirim transfer baru.'
        );

        $path = $request->file('bukti_transfer')->store('bukti-transfer', 'public');

        $pendaftaran->pembayaran()->create([
            'nominal_transfer' => $request->nominal_transfer,
            'tanggal_transfer' => $request->tanggal_transfer,
            'bukti_transfer' => $path,
            'status' => 'menunggu_verifikasi',
        ]);

        return to_route('wali-murid.pembayaran.show', $pendaftaran);
    }

    /**
     * Pembayaran baru boleh diakses kalau berkas pendaftaran udah diverifikasi
     * staf (diverifikasi/diterima/ditolak) - bukan draft/diajukan/perlu_perbaikan.
     * Kalau diakses sebelum waktunya, lempar balik ke detail pendaftaran
     * dengan flash message, bukan 403 mentah.
     */
    private function guardBolehBayar(PendaftaranPpdb $pendaftaran): ?RedirectResponse
    {
        if (in_array($pendaftaran->status, self::STATUS_BOLEH_BAYAR)) {
            return null;
        }

        return to_route('wali-murid.pendaftaran.show', $pendaftaran)
            ->with('error', 'Pembayaran baru bisa dilakukan setelah berkas pendaftaran diverifikasi Staf PPDB.');
    }

    private function authorizeAccess(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless($pendaftaran->user_id === request()->user()->id, 403);
    }
}
