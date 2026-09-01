<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliMurid\StoreFormulirRequest;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KuotaKategori;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PendaftaranController extends Controller
{

    public function index(Request $request): Response
    {
        $pendaftaranList = PendaftaranPpdb::with([
            'kategoriSiswa', 'dokumen', 'waliMurid', 'pembayaranTerakhir', 'gelombang',
            'pembayaran', 'tagihanItem',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (PendaftaranPpdb $p) => $this->mapDetail($p));

        return Inertia::render('wali-murid/pendaftaran-index', [
            'pendaftaranList' => $pendaftaranList,
            'expandId' => $request->query('expand'),
            'gelombangDibuka' => $this->gelombangDibuka() !== null,
        ]);
    }

    public function show(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);

        $pendaftaran->load([
            'kategoriSiswa', 'dokumen', 'waliMurid', 'pembayaranTerakhir', 'gelombang',
            'pembayaran', 'tagihanItem',
        ]);

        return Inertia::render('wali-murid/pendaftaran-show', $this->mapDetail($pendaftaran));
    }

    private function mapDetail(PendaftaranPpdb $pendaftaran): array
    {
        $dokumenWajib = $pendaftaran->dokumenWajib();
        $jumlahTerunggah = $pendaftaran->dokumen->whereIn('jenis_dokumen', $dokumenWajib)->count();

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
                'label' => $d->label(),
                'nama_file' => basename($d->berkas),
                'url' => Storage::url($d->berkas),
            ]),
            'statusPembayaran' => ($status = $pendaftaran->statusPelunasan()) === 'belum_bayar' ? null : $status,
            'bisaEditBerkas' => $pendaftaran->bisaDiedit(),
            'bolehBayar' => $pendaftaran->bolehBayar(),
            'progres' => [
                'wali' => $pendaftaran->waliMurid->count() > 0,
                'berkasTerunggah' => $jumlahTerunggah,
                'berkasWajib' => count($dokumenWajib),
            ],
        ];
    }

    private function gelombangDibuka(): ?GelombangPpdb
    {
        return GelombangPpdb::with('tahunAjaran')
            ->where('status_buka', true)
            ->latest()
            ->first();
    }

    public function create(): Response
    {
        $gelombang = $this->gelombangDibuka();

        return Inertia::render('wali-murid/pendaftaran-create', [
            'kategoriSiswa' => $this->kategoriDenganKuota($gelombang),
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
        $gelombang = $this->gelombangDibuka();

        abort_if(! $gelombang, 422, 'Tidak ada gelombang PPDB yang sedang dibuka saat ini.');

        $pendaftaran = DB::transaction(function () use ($request, $gelombang) {
            KuotaKategori::where('gelombang_ppdb_id', $gelombang->id)
                ->where('kategori_siswa_id', $request->kategori_siswa_id)
                ->lockForUpdate()
                ->first();

            $this->abortJikaKuotaPenuh($gelombang, (int) $request->kategori_siswa_id);

            $pendaftaran = PendaftaranPpdb::create([
                'user_id' => $request->user()->id,
                'gelombang_ppdb_id' => $gelombang->id,
                'kategori_siswa_id' => $request->kategori_siswa_id,
                'nomor_pendaftaran' => $this->generateNomorPendaftaran($gelombang),
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

            return $pendaftaran;
        });

        return to_route('wali-murid.pendaftaran.unggah-berkas', $pendaftaran);
    }

    public function edit(PendaftaranPpdb $pendaftaran): Response
    {
        $this->authorizeAccess($pendaftaran);
        $this->authorizeEditable($pendaftaran);

        $pendaftaran->load('waliMurid');
        $gelombang = $this->gelombangDibuka();

        return Inertia::render('wali-murid/pendaftaran-create', [
            'kategoriSiswa' => $this->kategoriDenganKuota($gelombang, $pendaftaran->kategori_siswa_id),
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

        if ((int) $request->kategori_siswa_id !== $pendaftaran->kategori_siswa_id) {
            $this->abortJikaKuotaPenuh($pendaftaran->gelombang, (int) $request->kategori_siswa_id);
        }

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

    public function submitPerbaikan(PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $this->authorizeAccess($pendaftaran);

        abort_unless($pendaftaran->status === 'perlu_perbaikan', 403, 'Pendaftaran ini bukan status perlu perbaikan.');

        $pendaftaran->load(['dokumen', 'kategoriSiswa', 'waliMurid']);

        abort_if($pendaftaran->waliMurid->isEmpty(), 422, 'Data wali belum diisi.');
        abort_if(! $pendaftaran->berkasLengkap(), 422, 'Masih ada dokumen wajib yang belum diunggah.');

        $pendaftaran->update(['status' => 'diajukan', 'catatan_verifikasi' => null]);

        return to_route('wali-murid.pendaftaran.index', ['expand' => $pendaftaran->id]);
    }

    private function kategoriDenganKuota(?GelombangPpdb $gelombang, ?int $kecualikanKategoriId = null): Collection
    {
        return KategoriSiswa::select('id', 'nama', 'deskripsi')->get()->map(function (KategoriSiswa $k) use ($gelombang, $kecualikanKategoriId) {
            $kuota = $gelombang ? KuotaKategori::untuk($gelombang->id, $k->id) : null;

            return [
                'id' => $k->id,
                'nama' => $k->nama,
                'deskripsi' => $k->deskripsi,
                'kuota' => $kuota?->kuota,
                'sisa_kuota' => $kuota?->sisa(),
                'penuh' => $kuota && $kuota->penuh() && $k->id !== $kecualikanKategoriId,
            ];
        });
    }

    private function abortJikaKuotaPenuh(GelombangPpdb $gelombang, int $kategoriSiswaId): void
    {
        abort_if(
            KuotaKategori::penuhUntuk($gelombang->id, $kategoriSiswaId),
            422,
            'Kuota untuk kategori yang dipilih sudah penuh pada gelombang ini. Silakan pilih kategori lain atau tunggu gelombang berikutnya.'
        );
    }

    private function authorizeEditable(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->bisaDiedit(),
            403,
            'Pendaftaran ini sudah tidak bisa diedit karena statusnya sudah lanjut ke tahap berikutnya.'
        );
    }

    private function generateNomorPendaftaran(GelombangPpdb $gelombang): string
    {
        $nomorUrut = PendaftaranPpdb::where('gelombang_ppdb_id', $gelombang->id)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('PPDB-%s-%05d', $gelombang->tahunAjaran->tahun_mulai, $nomorUrut);
    }

    private function authorizeAccess(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless($pendaftaran->user_id === request()->user()->id, 403);
    }
}