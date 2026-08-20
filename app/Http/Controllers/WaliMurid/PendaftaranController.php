<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StoreFormulirRequest;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PendaftaranController extends Controller
{
    /**
     * DataTable: semua pendaftaran (anak) milik wali yang login.
     */
    public function index(Request $request): Response
    {
        $pendaftaran = PendaftaranPpdb::with('kategoriSiswa')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (PendaftaranPpdb $p) => [
                'id' => $p->id,
                'nomor_pendaftaran' => $p->nomor_pendaftaran,
                'nama_pendaftar' => $p->nama_pendaftar,
                'kategori' => $p->kategoriSiswa->nama,
                'status' => $p->status,
                'tanggal_daftar' => $p->created_at->locale('id')->translatedFormat('d F Y'),
            ]);

        return Inertia::render('wali-murid/pendaftaran/index', [
            'pendaftaranList' => $pendaftaran,
        ]);
    }

    /**
     * Detail satu pendaftaran - status timeline, ringkasan berkas.
     * Ini yang gantiin rencana lama "Status Pendaftaran" terpisah.
     */
    public function show(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);

        $pendaftaran->load(['kategoriSiswa', 'dokumen', 'pembayaranTerakhir']);

        return Inertia::render('wali-murid/pendaftaran/show', [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
                'kategori' => $pendaftaran->kategoriSiswa->nama,
                'status' => $pendaftaran->status,
                'catatan_verifikasi' => $pendaftaran->catatan_verifikasi,
                'jumlah_dokumen' => $pendaftaran->dokumen->count(),
                'status_pembayaran' => $pendaftaran->pembayaranTerakhir?->status,
            ],
        ]);
    }

    public function create(): Response
    {
        $gelombang = GelombangPpdb::with('tahunAjaran')
            ->where('status_buka', true)
            ->latest()
            ->first();

        return Inertia::render('wali-murid/pendaftaran/create', [
            'kategoriSiswa' => KategoriSiswa::select('id', 'nama', 'deskripsi')->get(),
            'gelombang' => $gelombang ? [
                'id' => $gelombang->id,
                'nama' => $gelombang->nama,
                'tanggal_mulai' => $gelombang->tanggal_mulai->locale('id')->translatedFormat('d F Y'),
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
        ]);
    }

    public function store(StoreFormulirRequest $request): RedirectResponse
    {
        $gelombang = GelombangPpdb::with('tahunAjaran')
            ->where('status_buka', true)
            ->latest()
            ->first();

        abort_if(! $gelombang, 422, 'Tidak ada gelombang PPDB yang sedang dibuka saat ini.');

        $nomorPendaftaran = $this->generateNomorPendaftaran($gelombang);

        $pendaftaran = PendaftaranPpdb::create([
            'user_id' => $request->user()->id,
            'gelombang_ppdb_id' => $gelombang->id,
            'kategori_siswa_id' => $request->kategori_siswa_id,
            'nomor_pendaftaran' => $nomorPendaftaran,
            'nama_pendaftar' => $request->nama_pendaftar,
            'nik' => $request->nik,
            'tanggal_lahir' => $request->tanggal_lahir,
            'tempat_lahir' => $request->tempat_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'nama_saudara' => $request->nama_saudara,
            'nama_orang_tua_guru' => $request->nama_orang_tua_guru,
            'status' => 'draft',
        ]);

        foreach ($request->wali_murid as $waliMuridData) {
            $pendaftaran->waliMurid()->create($waliMuridData);
        }

        return to_route('wali-murid.pendaftaran.unggah-berkas', $pendaftaran);
    }

    private function generateNomorPendaftaran(GelombangPpdb $gelombang): string
    {
        $nomorUrut = PendaftaranPpdb::where('gelombang_ppdb_id', $gelombang->id)->count() + 1;

        return sprintf('PPDB-%s-%05d', $gelombang->tahunAjaran->tahun_mulai, $nomorUrut);
    }

    private function authorizeAccess(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless($pendaftaran->user_id === request()->user()->id, 403);
    }
}