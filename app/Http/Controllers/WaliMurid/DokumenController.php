<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StoreDokumenRequest;
use App\Models\BerkasPersyaratan;
use App\Models\KebijakanKategori;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    public function index(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);

        $pendaftaran->load(['dokumen', 'gelombang.dokumenWajib']);

        $dokumenList = collect($pendaftaran->dokumenWajib())->map(function (string $jenis) use ($pendaftaran) {
            $existing = $pendaftaran->dokumen->firstWhere('jenis_dokumen', $jenis);

            return [
                'jenis' => $jenis,
                'label' => BerkasPersyaratan::peta()[$jenis] ?? $jenis,
                'terunggah' => (bool) $existing,
                'nama_file' => $existing ? basename($existing->berkas) : null,
                'url' => $existing ? Storage::url($existing->berkas) : null,
            ];
        })->values();

        return Inertia::render('wali-murid/unggah-berkas', [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
                'status' => $pendaftaran->status,
            ],
            'dokumenList' => $dokumenList,
            // Frontend pakai ini buat mutusin tampilan dropzone aktif vs read-only.
            'bisaEdit' => $pendaftaran->bisaDiedit(),
        ]);
    }

    public function store(StoreDokumenRequest $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);
        $this->authorizeEditable($pendaftaran);

        $path = $request->file('berkas')->store('dokumen-ppdb', 'public');

        $existing = $pendaftaran->dokumen()->where('jenis_dokumen', $request->jenis_dokumen)->first();

        if ($existing) {
            // Ganti berkas lama: hapus file fisik lama, update record yang sama
            Storage::disk('public')->delete($existing->berkas);
            $existing->update(['berkas' => $path]);
        } else {
            $pendaftaran->dokumen()->create([
                'jenis_dokumen' => $request->jenis_dokumen,
                'berkas' => $path,
            ]);
        }

        return back();
    }

    public function submit(PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);
        $this->authorizeEditable($pendaftaran);

        abort_unless($pendaftaran->status === 'draft', 403, 'Gunakan tombol "Kirim Perbaikan" untuk mengirim ulang setelah perbaikan.');

        $pendaftaran->load(['dokumen', 'gelombang.dokumenWajib']);

        abort_if(! $pendaftaran->berkasLengkap(), 422, 'Masih ada dokumen wajib yang belum diunggah.');

        DB::transaction(function () use ($pendaftaran) {
            KebijakanKategori::where('gelombang_ppdb_id', $pendaftaran->gelombang_ppdb_id)
                ->where('kategori_siswa_id', $pendaftaran->kategori_siswa_id)
                ->lockForUpdate()
                ->first();

            abort_if(
                KebijakanKategori::penuhUntuk($pendaftaran->gelombang_ppdb_id, $pendaftaran->kategori_siswa_id),
                422,
                'Kuota untuk kategori pendaftaran ini sudah penuh. Silakan ubah kategori di formulir atau tunggu gelombang berikutnya.'
            );

            $pendaftaran->update(['status' => 'diajukan']);
        });

        return to_route('wali-murid.pendaftaran.index', ['expand' => $pendaftaran->id]);
    }

    private function authorizeEditable(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->bisaDiedit(),
            403,
            'Berkas pendaftaran ini sudah tidak bisa diubah karena statusnya sudah lanjut ke tahap berikutnya.'
        );
    }

    private function authorizeAccess(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless($pendaftaran->user_id === request()->user()->id, 403);
    }
}