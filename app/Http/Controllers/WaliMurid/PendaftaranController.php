<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StoreFormulirRequest;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PendaftaranController extends Controller
{
    private const JENIS_LABEL = [
        'kartu_keluarga' => 'Kartu Keluarga (KK)',
        'akta' => 'Akta Kelahiran',
        'ktp_orangtua' => 'KTP Orang Tua / Wali',
        'pas_foto' => 'Pas Foto Calon Peserta Didik',
        'surat_kematian_ayah' => 'Surat Kematian Ayah',
        'surat_keterangan_tidak_mampu' => 'Surat Keterangan Tidak Mampu',
    ];

    /**
     * DataTable: semua pendaftaran (anak) milik wali yang login.
     * Detail lengkap tiap baris juga dikirim sekalian (dipakai accordion
     * expand di tabel), bukan cuma ringkasan - makanya pakai mapDetail()
     * yang sama kayak show().
     */
    public function index(Request $request): Response
    {
        $pendaftaranList = PendaftaranPpdb::with(['kategoriSiswa', 'dokumen', 'waliMurid', 'pembayaranTerakhir', 'gelombang'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (PendaftaranPpdb $p) => $this->mapDetail($p));

        return Inertia::render('wali-murid/pendaftaran-index', [
            'pendaftaranList' => $pendaftaranList,
            'expandId' => $request->query('expand'),
        ]);
    }

    /**
     * Detail satu pendaftaran lewat URL langsung (dipakai kalau ada yang
     * perlu link ke pendaftaran spesifik, mis. dari sisi Staf nanti).
     */
    public function show(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);

        $pendaftaran->load(['kategoriSiswa', 'dokumen', 'waliMurid', 'pembayaranTerakhir', 'gelombang']);

        return Inertia::render('wali-murid/pendaftaran-show', $this->mapDetail($pendaftaran));
    }

    /**
     * Satu-satunya tempat yang nyusun representasi lengkap sebuah pendaftaran
     * (biodata, wali, dokumen, progres, status pembayaran). Dipakai index()
     * (buat tiap baris di accordion) dan show() (buat halaman detail langsung) -
     * biar dua-duanya nggak pernah beda data.
     */
    private function mapDetail(PendaftaranPpdb $pendaftaran): array
    {
        $requiredJenis = ['kartu_keluarga', 'akta', 'ktp_orangtua', 'pas_foto'];
        if ($pendaftaran->kategoriSiswa->nama === 'Anak Yatim') {
            $requiredJenis[] = 'surat_kematian_ayah';
        }
        $jumlahTerunggah = $pendaftaran->dokumen->whereIn('jenis_dokumen', $requiredJenis)->count();

        return [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
                'nik' => $pendaftaran->nik,
                'tempat_lahir' => $pendaftaran->tempat_lahir,
                'tanggal_lahir' => $pendaftaran->tanggal_lahir->locale('id')->translatedFormat('d F Y'),
                'jenis_kelamin' => $pendaftaran->jenis_kelamin,
                'agama' => $pendaftaran->agama,
                'alamat' => $pendaftaran->alamat,
                'kategori' => $pendaftaran->kategoriSiswa->nama,
                'status' => $pendaftaran->status,
                'catatan_verifikasi' => $pendaftaran->catatan_verifikasi,
                'tanggal_daftar' => $pendaftaran->created_at->locale('id')->translatedFormat('d F Y'),
                'gelombang' => $pendaftaran->gelombang->nama,
            ],
            'waliMurid' => $pendaftaran->waliMurid->map(fn ($w) => [
                'nama' => $w->nama,
                'nik' => $w->nik,
                'hubungan' => $w->hubungan,
                'telepon' => $w->telepon,
            ]),
            'dokumenList' => $pendaftaran->dokumen->map(fn ($d) => [
                'label' => self::JENIS_LABEL[$d->jenis_dokumen] ?? $d->jenis_dokumen,
                'nama_file' => basename($d->berkas),
                'url' => Storage::url($d->berkas),
            ]),
            // Status pelunasan GABUNGAN (bisa dari beberapa baris pembayaran_ppdb
            // kalau dicicil) - bukan status transfer terakhir doang. null kalau
            // belum ada transfer sama sekali, biar cocok sama badge di frontend.
            'statusPembayaran' => ($status = $pendaftaran->statusPelunasan()) === 'belum_bayar' ? null : $status,
            'bisaEditBerkas' => in_array($pendaftaran->status, ['draft', 'perlu_perbaikan']),
            'progres' => [
                'wali' => $pendaftaran->waliMurid->count() > 0,
                'berkasTerunggah' => $jumlahTerunggah,
                'berkasWajib' => count($requiredJenis),
            ],
        ];
    }

    public function create(): Response
    {
        $gelombang = GelombangPpdb::with('tahunAjaran')
            ->where('status_buka', true)
            ->latest()
            ->first();

        return Inertia::render('wali-murid/pendaftaran-create', [
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

    public function edit(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);
        $this->authorizeEditable($pendaftaran);

        $pendaftaran->load('waliMurid');

        $gelombang = GelombangPpdb::with('tahunAjaran')
            ->where('status_buka', true)
            ->latest()
            ->first();

        return Inertia::render('wali-murid/pendaftaran-create', [
            'kategoriSiswa' => KategoriSiswa::select('id', 'nama', 'deskripsi')->get(),
            'gelombang' => $gelombang ? [
                'id' => $gelombang->id,
                'nama' => $gelombang->nama,
                'tanggal_mulai' => $gelombang->tanggal_mulai->locale('id')->translatedFormat('d F Y'),
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'kategori_siswa_id' => (string) $pendaftaran->kategori_siswa_id,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
                'nik' => $pendaftaran->nik ?? '',
                'tanggal_lahir' => $pendaftaran->tanggal_lahir->format('Y-m-d'),
                'tempat_lahir' => $pendaftaran->tempat_lahir,
                'jenis_kelamin' => $pendaftaran->jenis_kelamin,
                'agama' => $pendaftaran->agama ?? '',
                'alamat' => $pendaftaran->alamat,
                'nama_saudara' => $pendaftaran->nama_saudara ?? '',
                'nama_orang_tua_guru' => $pendaftaran->nama_orang_tua_guru ?? '',
                'wali_murid' => $pendaftaran->waliMurid->map(fn ($w) => [
                    'nama' => $w->nama,
                    'nik' => $w->nik,
                    'hubungan' => $w->hubungan,
                    'telepon' => $w->telepon,
                ]),
            ],
        ]);
    }

    public function update(StoreFormulirRequest $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);
        $this->authorizeEditable($pendaftaran);

        // Status TIDAK di-flip di sini walau lagi perlu_perbaikan - soalnya
        // berkas mungkin belum ikut dibetulin. Status cuma berubah lewat
        // submitPerbaikan(), setelah dua-duanya (formulir+berkas) confirmed lengkap.
        $pendaftaran->update([
            'kategori_siswa_id' => $request->kategori_siswa_id,
            'nama_pendaftar' => $request->nama_pendaftar,
            'nik' => $request->nik,
            'tanggal_lahir' => $request->tanggal_lahir,
            'tempat_lahir' => $request->tempat_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'nama_saudara' => $request->nama_saudara,
            'nama_orang_tua_guru' => $request->nama_orang_tua_guru,
        ]);

        $pendaftaran->waliMurid()->delete();
        foreach ($request->wali_murid as $waliMuridData) {
            $pendaftaran->waliMurid()->create($waliMuridData);
        }

        return to_route('wali-murid.pendaftaran.index', ['expand' => $pendaftaran->id]);
    }

    /**
     * Tombol "Kirim Perbaikan" - satu-satunya jalan status perlu_perbaikan
     * balik jadi diajukan. Cuma bisa jalan kalau formulir DAN berkas
     * dua-duanya udah lengkap, jadi nggak ada bagian yang kekunci
     * sebelum sempat dibetulin.
     */
    public function submitPerbaikan(PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

        abort_unless($pendaftaran->status === 'perlu_perbaikan', 403, 'Pendaftaran ini bukan status perlu perbaikan.');

        $pendaftaran->load(['dokumen', 'kategoriSiswa', 'waliMurid']);

        abort_if($pendaftaran->waliMurid->isEmpty(), 422, 'Data wali belum diisi.');

        $requiredJenis = ['kartu_keluarga', 'akta', 'ktp_orangtua', 'pas_foto'];
        if ($pendaftaran->kategoriSiswa->nama === 'Anak Yatim') {
            $requiredJenis[] = 'surat_kematian_ayah';
        }
        $uploadedJenis = $pendaftaran->dokumen->pluck('jenis_dokumen')->all();
        $missing = array_diff($requiredJenis, $uploadedJenis);
        abort_if(count($missing) > 0, 422, 'Masih ada dokumen wajib yang belum diunggah.');

        $pendaftaran->update(['status' => 'diajukan', 'catatan_verifikasi' => null]);

        return to_route('wali-murid.pendaftaran.index', ['expand' => $pendaftaran->id]);
    }

    private function authorizeEditable(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless(
            in_array($pendaftaran->status, ['draft', 'perlu_perbaikan']),
            403,
            'Pendaftaran ini sudah tidak bisa diedit karena statusnya sudah lanjut ke tahap berikutnya.'
        );
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