<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StorePembayaranRequest;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\TagihanItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PembayaranController extends Controller
{
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

        // Sengaja pakai guard LIHAT, bukan guard BAYAR - pendaftaran yang
        // ditolak tetap boleh dibuka read-only supaya wali yang terlanjur
        // menyetor uang masih bisa melihat catatan pembayarannya.
        if ($redirect = $this->guardBolehLihat($pendaftaran)) {
            return $redirect;
        }

        // Terbitkan tagihan kalau ini pertama kali wali melihat tagihannya.
        // Sesudah ini nominalnya beku - perubahan tarif oleh Admin nggak akan
        // mengubah tagihan yang sudah pernah ditampilkan/dibayar.
        $pendaftaran->terbitkanTagihan();
        $pendaftaran->load(['pembayaran', 'tagihanItem', 'gelombang.tahunAjaran']);

        $rincianTagihan = $pendaftaran->tagihanItem()
            ->orderBy('id')
            ->get()
            ->map(fn (TagihanItem $i) => [
                'nama' => $i->nama_komponen,
                'keterangan' => $i->keterangan,
                'nominal' => $i->nominal,
            ]);

        $totalTagihan = $pendaftaran->totalTagihan();
        $totalTerbayar = $pendaftaran->totalTerbayar();
        $sisaTagihan = max(0, $totalTagihan - $totalTerbayar);
        $adaPending = $pendaftaran->adaPembayaranPending();
        // Admin belum menyiapkan komponen biaya untuk gelombang ini - tagihan
        // belum bisa diterbitkan, jadi wali belum bisa bayar apa-apa.
        $tagihanTersedia = $pendaftaran->tagihanSudahTerbit();

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

        // DUA tenggat yang berbeda, dan bedanya penting buat wali:
        //   - batas minimal bayar (milik gelombang pendaftaran ini) -> lewat
        //     tanggal ini tanpa memenuhi minimal, staf boleh menolak.
        //   - batas pelunasan (milik tahun ajaran) -> cuma menagih sisa cicilan,
        //     nggak pernah membatalkan pendaftaran yang sudah diterima.
        $batasMinimal = $pendaftaran->batasMinimalBayar();
        $batasPelunasan = $pendaftaran->batasPelunasan();

        return Inertia::render('wali-murid/pembayaran', [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
            ],
            'batasWaktuPembayaran' => $batasMinimal?->locale('id')->translatedFormat('d F Y'),
            'batasWaktuLewat' => $batasMinimal ? $batasMinimal->isPast() : false,
            'batasPelunasan' => $batasPelunasan?->locale('id')->translatedFormat('d F Y'),
            // Minimal bayar: ambang yang menentukan diterima/tidak. Dihitung di
            // model dari snapshot, jangan dihitung ulang di TSX.
            'minimalBayar' => $pendaftaran->minimalBayar(),
            'kurangMinimal' => $pendaftaran->kurangMinimal(),
            'sudahPenuhiMinimal' => $pendaftaran->sudahPenuhiMinimal(),
            'menunggak' => $pendaftaran->menunggak(),
            'statusPendaftaran' => $pendaftaran->status,
            'catatanVerifikasi' => $pendaftaran->catatan_verifikasi,
            'rincianTagihan' => $rincianTagihan,
            'totalTagihan' => $totalTagihan,
            'totalTerbayar' => $totalTerbayar,
            'sisaTagihan' => $sisaTagihan,
            'riwayatTransfer' => $riwayatTransfer,
            'tagihanTersedia' => $tagihanTersedia,
            // Cicilan: boleh transfer lagi selama pendaftarannya masih boleh
            // dibayar, masih ada sisa, DAN nggak ada transfer lain yang masih
            // menunggu diverifikasi staf (biar nggak numpuk pending sekaligus).
            'bisaBayar' => $pendaftaran->bolehBayar() && $tagihanTersedia && $sisaTagihan > 0 && ! $adaPending,
        ]);
    }

    public function store(StorePembayaranRequest $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

        if ($redirect = $this->guardBolehBayar($pendaftaran)) {
            return $redirect;
        }

        // Kalau wali POST langsung tanpa pernah buka halaman tagihan (idempotent).
        $pendaftaran->terbitkanTagihan();
        abort_unless($pendaftaran->tagihanSudahTerbit(), 403, 'Tagihan untuk pendaftaran ini belum tersedia.');

        $path = $request->file('bukti_transfer')->store('bukti-transfer', 'public');

        try {
            DB::transaction(function () use ($request, $pendaftaran, $path) {
                // Kunci baris pendaftaran selama transaksi. Tanpa ini, dua submit
                // bersamaan (double-click / koneksi lambat) bisa dua-duanya lolos
                // cek di bawah, bikin dua transfer kembar - dan kalau staf
                // memverifikasi dua-duanya, pembayarannya kehitung dobel.
                PendaftaranPpdb::whereKey($pendaftaran->getKey())->lockForUpdate()->first();

                // Baca ulang di dalam kunci - relasi yang mungkin sudah ter-load
                // sebelum transaksi nggak boleh dipakai buat keputusan ini.
                $pendaftaran->load(['pembayaran', 'tagihanItem']);

                abort_if($pendaftaran->sisaTagihan() <= 0, 403, 'Tagihan pendaftaran ini sudah lunas.');
                abort_if(
                    $pendaftaran->adaPembayaranPending(),
                    403,
                    'Masih ada transfer yang menunggu diverifikasi staf, tunggu sampai itu diproses dulu sebelum mengirim transfer baru.'
                );

                $pendaftaran->pembayaran()->create([
                    'nominal_transfer' => $request->nominal_transfer,
                    'tanggal_transfer' => $request->tanggal_transfer,
                    'bukti_transfer' => $path,
                    'status' => 'menunggu_verifikasi',
                ]);
            });
        } catch (\Throwable $e) {
            // Transaksi batal - jangan tinggalkan file bukti transfer yatim di disk.
            Storage::disk('public')->delete($path);

            throw $e;
        }

        return to_route('wali-murid.pembayaran.show', $pendaftaran);
    }

    /**
     * Untuk show() - lebih longgar, pendaftaran yang ditolak tetap boleh
     * membuka halamannya (read-only) buat melihat catatan pembayaran.
     */
    private function guardBolehLihat(PendaftaranPpdb $pendaftaran): ?RedirectResponse
    {
        if ($pendaftaran->bolehLihatTagihan()) {
            return null;
        }

        return to_route('wali-murid.pendaftaran.show', $pendaftaran)
            ->with('error', 'Rincian pembayaran baru tersedia setelah berkas pendaftaran diverifikasi Staf PPDB.');
    }

    /**
     * Untuk store() - lebih ketat. Aturannya ada di PendaftaranPpdb::bolehBayar();
     * di sini cuma soal "kalau belum boleh, mau diapain". Dilempar balik dengan
     * flash message, bukan 403 mentah.
     */
    private function guardBolehBayar(PendaftaranPpdb $pendaftaran): ?RedirectResponse
    {
        if ($pendaftaran->bolehBayar()) {
            return null;
        }

        return to_route('wali-murid.pendaftaran.show', $pendaftaran)
            ->with('error', 'Pendaftaran ini sudah tidak menerima pembayaran baru. Silakan hubungi Staf PPDB.');
    }

    private function authorizeAccess(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless($pendaftaran->user_id === request()->user()->id, 403);
    }
}
