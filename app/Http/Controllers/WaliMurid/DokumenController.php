<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StoreDokumenRequest;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    /**
     * Label yang ditampilkan ke wali per jenis dokumen.
     * Kalau nanti nambah jenis dokumen baru di migration, tambahkan juga di sini.
     */
    private const JENIS_LABEL = [
        'kartu_keluarga' => 'Kartu Keluarga (KK)',
        'akta' => 'Akta Kelahiran',
        'ktp_orangtua' => 'KTP Orang Tua / Wali',
        'pas_foto' => 'Pas Foto Calon Peserta Didik',
        'surat_kematian_ayah' => 'Surat Kematian Ayah',
        'surat_keterangan_tidak_mampu' => 'Surat Keterangan Tidak Mampu',
    ];

    public function index(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);

        $pendaftaran->load(['dokumen', 'kategoriSiswa']);

        $requiredJenis = $this->requiredJenisFor($pendaftaran);

        $dokumenList = collect($requiredJenis)->map(function (string $jenis) use ($pendaftaran) {
            $existing = $pendaftaran->dokumen->firstWhere('jenis_dokumen', $jenis);

            return [
                'jenis' => $jenis,
                'label' => self::JENIS_LABEL[$jenis],
                'terunggah' => (bool) $existing,
                'nama_file' => $existing ? basename($existing->berkas) : null,
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
        ]);
    }

    public function store(StoreDokumenRequest $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

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

    /**
     * Wali klik "Kirim Berkas untuk Diverifikasi" - ubah status draft -> diajukan.
     */
    public function submit(PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

        $pendaftaran->load(['dokumen', 'kategoriSiswa']);

        $requiredJenis = $this->requiredJenisFor($pendaftaran);
        $uploadedJenis = $pendaftaran->dokumen->pluck('jenis_dokumen')->all();
        $missing = array_diff($requiredJenis, $uploadedJenis);

        abort_if(count($missing) > 0, 422, 'Masih ada dokumen wajib yang belum diunggah.');

        $pendaftaran->update(['status' => 'diajukan']);

        return to_route('wali-murid.status-pendaftaran', $pendaftaran);
    }

    /**
     * Dokumen wajib dasar buat semua kategori, ditambah dokumen khusus
     * tergantung kategori_siswa yang diklaim (mis. Anak Yatim butuh surat
     * kematian ayah). Sesuaikan di sini kalau ada kategori baru nanti.
     */
    private function requiredJenisFor(PendaftaranPpdb $pendaftaran): array
    {
        $required = ['kartu_keluarga', 'akta', 'ktp_orangtua', 'pas_foto'];

        if ($pendaftaran->kategoriSiswa->nama === 'Anak Yatim') {
            $required[] = 'surat_kematian_ayah';
        }

        return $required;
    }

    private function authorizeAccess(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless($pendaftaran->user_id === request()->user()->id, 403);
    }
}