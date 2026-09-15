<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BerkasPersyaratan;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KomponenBiaya;
use App\Models\PengaturanSistem;
use App\Models\TahunAjaran;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Beranda Super Admin memantau kesiapan sistem, bukan pekerjaan operasional
     * staf atau laporan penerimaan milik Kepala Sekolah.
     */
    public function __invoke(): Response
    {
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();
        $gelombangMenerima = GelombangPpdb::with('tahunAjaran')
            ->menerimaPendaftar()
            ->latest('tanggal_mulai')
            ->first();
        $gelombangTerbaru = $tahunAktif?->gelombang()
            ->with('tahunAjaran')
            ->latest('tanggal_mulai')
            ->first();
        $gelombangSorotan = $gelombangMenerima ?? $gelombangTerbaru;
        $pengaturan = PengaturanSistem::saatIni();
        $gelombangSiap = $gelombangSorotan !== null
            && ! in_array($gelombangSorotan->keadaan(), ['perlu_ditutup', 'berakhir', 'tahun_lampau'], true);

        $jumlahMaster = [
            'komponen_biaya' => KomponenBiaya::aktif()->count(),
            'berkas_persyaratan' => BerkasPersyaratan::aktif()->count(),
            'jalur' => KategoriSiswa::aktif()->count(),
        ];

        $kesiapan = [
            $this->bagianKesiapan(
                'tahun_ajaran',
                'Tahun Ajaran',
                $tahunAktif !== null,
                $tahunAktif?->nama ?? 'Belum ada tahun ajaran yang sedang berjalan.'
            ),
            $this->bagianKesiapan(
                'komponen_biaya',
                'Komponen Biaya',
                $jumlahMaster['komponen_biaya'] > 0,
                $jumlahMaster['komponen_biaya'] > 0
                    ? $jumlahMaster['komponen_biaya'].' komponen biaya aktif.'
                    : 'Belum ada komponen biaya aktif.'
            ),
            $this->bagianKesiapan(
                'berkas_persyaratan',
                'Berkas Persyaratan',
                $jumlahMaster['berkas_persyaratan'] > 0,
                $jumlahMaster['berkas_persyaratan'] > 0
                    ? $jumlahMaster['berkas_persyaratan'].' jenis berkas aktif.'
                    : 'Belum ada jenis berkas persyaratan aktif.'
            ),
            $this->bagianKesiapan(
                'jalur',
                'Jalur Pendaftaran',
                $jumlahMaster['jalur'] > 0,
                $jumlahMaster['jalur'] > 0
                    ? $jumlahMaster['jalur'].' jalur pendaftaran aktif.'
                    : 'Belum ada jalur pendaftaran aktif.'
            ),
            $this->bagianKesiapan(
                'gelombang',
                'Gelombang PPDB',
                $gelombangSiap,
                $this->keteranganGelombang($gelombangSorotan)
            ),
            $this->bagianKesiapan(
                'pengaturan_sistem',
                'Informasi Pembayaran',
                $pengaturan->informasiRekeningLengkap(),
                $pengaturan->informasiRekeningLengkap()
                    ? $pengaturan->nama_bank.' atas nama '.$pengaturan->nama_pemilik_rekening.'.'
                    : 'Rekening pembayaran belum dilengkapi.'
            ),
        ];

        $penggunaPeran = User::query()
            ->selectRaw('role, COUNT(*) as jumlah')
            ->groupBy('role')
            ->pluck('jumlah', 'role');

        return Inertia::render('super-admin/dashboard', [
            'ringkasan' => [
                'pengguna_aktif' => User::where('status_aktif', true)->count(),
                'pengguna_nonaktif' => User::where('status_aktif', false)->count(),
                'tahun_ajaran' => $tahunAktif?->nama,
                'gelombang' => $gelombangSorotan === null ? null : [
                    'nama' => $gelombangSorotan->nama,
                    'keadaan' => $gelombangSorotan->keadaan(),
                    'label_keadaan' => $this->labelKeadaanGelombang($gelombangSorotan->keadaan()),
                    'tanggal_mulai' => $this->tanggalPanjang($gelombangSorotan->tanggal_mulai),
                    'tanggal_selesai' => $this->tanggalPanjang($gelombangSorotan->tanggal_selesai),
                ],
                'master_aktif' => $jumlahMaster,
                'bagian_siap' => collect($kesiapan)->where('siap', true)->count(),
                'total_bagian' => count($kesiapan),
            ],
            'kesiapan' => $kesiapan,
            'penggunaPeran' => [
                'wali_murid' => (int) ($penggunaPeran['wali_murid'] ?? 0),
                'staf_ppdb' => (int) ($penggunaPeran['staf_ppdb'] ?? 0),
                'kepala_sekolah' => (int) ($penggunaPeran['kepala_sekolah'] ?? 0),
                'super_admin' => (int) ($penggunaPeran['super_admin'] ?? 0),
            ],
        ]);
    }

    /** @return array{kunci: string, nama: string, siap: bool, keterangan: string} */
    private function bagianKesiapan(string $kunci, string $nama, bool $siap, string $keterangan): array
    {
        return compact('kunci', 'nama', 'siap', 'keterangan');
    }

    private function keteranganGelombang(?GelombangPpdb $gelombang): string
    {
        if ($gelombang === null) {
            return 'Belum ada gelombang pada tahun ajaran berjalan.';
        }

        return $gelombang->nama.' · '.$this->labelKeadaanGelombang($gelombang->keadaan()).'.';
    }

    private function labelKeadaanGelombang(string $keadaan): string
    {
        return match ($keadaan) {
            'menerima' => 'Sedang menerima pendaftar',
            'perlu_ditutup' => 'Perlu ditutup',
            'siap' => 'Siap dibuka',
            'belum_mulai' => 'Terjadwal',
            'berakhir' => 'Sudah berakhir',
            'tahun_lampau' => 'Tahun ajaran lampau',
            default => 'Status tidak dikenali',
        };
    }

    private function tanggalPanjang($tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('d F Y');
    }
}
