<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StorePembayaranPendaftaranAwalRequest;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KebijakanKategori;
use App\Models\PembayaranPendaftaranAwal;
use App\Models\PengaturanSistem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BiayaPendaftaranController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $pengaturan = PengaturanSistem::saatIni();
        $gelombang = GelombangPpdb::menerimaPendaftar()->latest()->first();
        $reservasi = $user->pembayaranPendaftaranAwal()
            ->when($gelombang, fn ($query) => $query->where('gelombang_ppdb_id', $gelombang->id))
            ->when(! $gelombang, fn ($query) => $query->whereRaw('1 = 0'))
            ->belumDigunakan()->dapatDilanjutkan()->with('kategoriSiswa')->latest('id')
            ->first();

        $status = $reservasi?->status === 'terverifikasi' ? 'siap_digunakan' : ($reservasi?->status ?? 'belum_bayar');

        return Inertia::render('wali-murid/biaya-pendaftaran', [
            'status' => $status,
            'biaya' => $reservasi?->nominal_tagihan ?? $gelombang?->biaya_pendaftaran,
            'gelombang' => $gelombang ? ['nama' => $gelombang->nama] : null,
            'kategoriSiswa' => $gelombang ? KategoriSiswa::where('status_aktif', true)->terurut()->get()
                ->map(fn (KategoriSiswa $k) => [
                    'id' => $k->id, 'nama' => $k->nama, 'deskripsi' => $k->deskripsi,
                    'penuh' => KebijakanKategori::penuhUntuk($gelombang->id, $k->id),
                ]) : [],
            'reservasi' => $reservasi ? [
                'id' => $reservasi->id,
                'jalur' => $reservasi->kategoriSiswa?->nama,
                'batas_bayar' => $status === 'menunggu_pembayaran'
                    ? $reservasi->reservasi_berakhir_pada?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i').' WIB' : null,
                'berakhir_pada' => $status === 'menunggu_pembayaran' ? $reservasi->reservasi_berakhir_pada?->toIso8601String() : null,
            ] : null,
            'bisaReservasi' => $gelombang !== null && $reservasi === null && $pengaturan->informasiRekeningLengkap(),
            'bisaMengirim' => $gelombang !== null
                && in_array($status, ['menunggu_pembayaran', 'ditolak'], true)
                && $pengaturan->informasiRekeningLengkap(),
            'informasiPembayaran' => $reservasi && $pengaturan->informasiRekeningLengkap() ? [
                'nama_bank' => $pengaturan->nama_bank,
                'nomor_rekening' => $pengaturan->nomor_rekening,
                'nama_pemilik_rekening' => $pengaturan->nama_pemilik_rekening,
                'instruksi' => $pengaturan->instruksi_pembayaran,
            ] : null,
            'catatanPenolakan' => $status === 'ditolak' ? $reservasi?->catatan_verifikasi : null,
            'riwayat' => $user->pembayaranPendaftaranAwal()
                ->whereNotNull('bukti_transfer')
                ->with(['gelombang:id,nama', 'pendaftaran:id,nama_pendaftar,nomor_pendaftaran'])
                ->latest()
                ->get()
                ->map(fn (PembayaranPendaftaranAwal $p) => [
                    'id' => $p->id,
                    'nominal_transfer' => $p->nominal_transfer,
                    'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                    'status' => $p->status,
                    'gelombang' => $p->gelombang->nama,
                    'catatan_verifikasi' => $p->catatan_verifikasi,
                    'digunakan_untuk' => $p->pendaftaran ? [
                        'nama' => $p->pendaftaran->nama_pendaftar,
                        'nomor' => $p->pendaftaran->nomor_pendaftaran,
                    ] : null,
                ]),
        ]);
    }

    public function reservasi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kategori_siswa_id' => ['required', Rule::exists('kategori_siswa', 'id')->where('status_aktif', true)],
        ], ['kategori_siswa_id.required' => 'Pilih jalur pendaftaran terlebih dahulu.']);
        $gelombang = GelombangPpdb::menerimaPendaftar()->latest()->first();
        if (! $gelombang || ! PengaturanSistem::saatIni()->informasiRekeningLengkap()) {
            return back()->with('error', 'Pendaftaran belum tersedia. Hubungi Staf PPDB.');
        }

        if ($request->user()->pembayaranPendaftaranAwal()->where('gelombang_ppdb_id', $gelombang->id)
            ->belumDigunakan()->dapatDilanjutkan()->exists()) {
            return to_route('wali-murid.biaya-pendaftaran.show')
                ->with('error', 'Selesaikan pendaftaran yang sedang berjalan terlebih dahulu.');
        }
        if (KebijakanKategori::penuhUntuk($gelombang->id, (int) $data['kategori_siswa_id'])) {
            throw ValidationException::withMessages([
                'kategori_siswa_id' => 'Jalur ini sudah penuh. Jangan melakukan transfer. Tunggu gelombang berikutnya jika tidak ada jalur yang sesuai.',
            ]);
        }

        $request->user()->pembayaranPendaftaranAwal()->create([
            'gelombang_ppdb_id' => $gelombang->id,
            'kategori_siswa_id' => $data['kategori_siswa_id'],
            'nominal_tagihan' => $gelombang->biaya_pendaftaran,
            'status' => 'menunggu_pembayaran',
            'reservasi_berakhir_pada' => now()->addDay()->min($gelombang->tanggal_selesai->copy()->endOfDay()),
        ]);

        return to_route('wali-murid.biaya-pendaftaran.show')->with('success', 'Jalur dipilih. Silakan lanjutkan pembayaran.');
    }

    public function store(StorePembayaranPendaftaranAwalRequest $request): RedirectResponse
    {
        $pengaturan = PengaturanSistem::saatIni();
        $gelombang = GelombangPpdb::menerimaPendaftar()->latest()->first();

        if (! $gelombang) {
            return to_route('wali-murid.biaya-pendaftaran.show')->with('error', 'Gelombang pendaftaran sudah ditutup. Jangan melakukan transfer.');
        }

        abort_unless(
            $pengaturan->informasiRekeningLengkap(),
            422,
            'Rekening pembayaran belum dilengkapi sekolah. Hubungi Staf PPDB sebelum melakukan transfer.'
        );

        $path = $request->file('bukti_transfer')->store('bukti-biaya-pendaftaran', 'public');

        try {
            DB::transaction(function () use ($request, $gelombang, $path): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $gelombangAktif = GelombangPpdb::query()->lockForUpdate()->findOrFail($gelombang->id);

                abort_unless(
                    $gelombangAktif->sedangMenerimaPendaftar(),
                    422,
                    'Gelombang pendaftaran sudah ditutup. Tunggu gelombang berikutnya untuk melakukan pembayaran.'
                );

                abort_if(
                    $user->tiketPendaftaranTersedia($gelombangAktif->id)->exists(),
                    403,
                    'Anda sudah memiliki pembayaran yang disetujui dan belum digunakan untuk mendaftarkan anak.'
                );

                abort_if(
                    $user->pembayaranPendaftaranAwal()
                        ->where('gelombang_ppdb_id', $gelombangAktif->id)
                        ->where('status', 'menunggu_verifikasi')
                        ->exists(),
                    403,
                    'Masih ada bukti pembayaran yang menunggu diperiksa Staf PPDB.'
                );

                $reservasi = $user->pembayaranPendaftaranAwal()
                    ->where('gelombang_ppdb_id', $gelombangAktif->id)
                    ->belumDigunakan()->dapatDilanjutkan()
                    ->find($request->integer('reservasi_id'));
                if (! $reservasi || ! in_array($reservasi->status, ['menunggu_pembayaran', 'ditolak'], true)) {
                    throw ValidationException::withMessages([
                        'reservasi_id' => 'Pembayaran ini tidak dapat dilanjutkan. Muat ulang halaman. Jika sudah transfer, hubungi Staf PPDB.',
                    ]);
                }

                $data = [
                    'nominal_transfer' => $request->integer('nominal_transfer'),
                    'tanggal_transfer' => $request->date('tanggal_transfer'),
                    'bukti_transfer' => $path,
                    'bukti_dikirim_pada' => now(),
                    'status' => 'menunggu_verifikasi',
                ];
                if ($reservasi->status === 'ditolak') {
                    // Simpan bukti dan keputusan lama di riwayat; pindahkan satu
                    // reservasinya ke bukti pengganti, bukan memesan kursi kedua.
                    $user->pembayaranPendaftaranAwal()->create([
                        ...$data,
                        'gelombang_ppdb_id' => $reservasi->gelombang_ppdb_id,
                        'kategori_siswa_id' => $reservasi->kategori_siswa_id,
                        'nominal_tagihan' => $reservasi->nominal_tagihan,
                        'reservasi_berakhir_pada' => $reservasi->reservasi_berakhir_pada,
                    ]);
                    $reservasi->update(['reservasi_berakhir_pada' => null]);
                } else {
                    $reservasi->update($data);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        return to_route('wali-murid.dashboard')
            ->with('success', 'Bukti pembayaran dikirim dan sedang menunggu verifikasi Staf PPDB.');
    }
}
