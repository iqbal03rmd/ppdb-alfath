<?php

namespace App\Http\Controllers\StafPpdb;

use App\Http\Controllers\Controller;
use App\Models\GelombangPpdb;
use App\Models\KebijakanKategori;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\TahunAjaran;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Layar kerja staf, bukan laporan.
     *
     * Isinya cuma hal yang bisa ditindaklanjuti hari itu juga: dua antrian yang
     * menunggu dikerjakan, siapa yang perlu diputuskan, dan sisa daya tampung.
     * Angka-angka rekapitulasi (total uang masuk, grafik, sebaran status)
     * sengaja TIDAK ada di sini - tidak ada yang bisa dikerjakan staf dari
     * angka itu, dan tempatnya nanti di modul Kepala Sekolah.
     */
    public function __invoke(): Response
    {
        return Inertia::render('staf-ppdb/dashboard', [
            'antrian' => $this->antrian(),
            'statistik' => $this->statistik(),
            'kuota' => $this->kuota(),
        ]);
    }

    /**
     * Dua antrian yang menunggu dikerjakan staf. Cukup jumlahnya - rinciannya
     * ada di halaman antriannya masing-masing, dan mengulangnya di sini cuma
     * bikin layar ini ikut jadi tempat memeriksa.
     */
    private function antrian(): array
    {
        return [
            'pendaftaran' => [
                'jumlah' => PendaftaranPpdb::where('status', 'diajukan')->count(),
            ],
            'transfer' => [
                'jumlah' => PembayaranPpdb::where('status', 'menunggu_verifikasi')->count(),
            ],
        ];
    }

    /**
     * Sebaran status untuk dua diagram di Beranda.
     *
     * Dua sudut pandang yang sengaja dipisah: satu menjawab "sampai mana proses
     * pendaftarannya", satu lagi "sampai mana uangnya". Satu pendaftaran bisa
     * sudah 'diterima' tapi pembayarannya baru 'dicicil' - digabung jadi satu
     * diagram, dua kabar itu saling menutupi.
     *
     * Yang belum boleh melihat tagihan tidak ikut dihitung di diagram pembayaran:
     * dia memang belum punya urusan uang, bukan berarti belum bayar.
     */
    private function statistik(): array
    {
        $semua = PendaftaranPpdb::with(['pembayaran', 'tagihanItem'])->get();

        return [
            'pendaftaran' => $semua->countBy('status')->all(),
            'pembayaran' => $semua
                ->filter(fn (PendaftaranPpdb $p) => $p->bolehLihatTagihan())
                ->countBy(fn (PendaftaranPpdb $p) => $p->statusPelunasan())
                ->all(),
        ];
    }

    /**
     * Sisa daya tampung gelombang yang sedang dibuka.
     *
     * Angkanya diambil lewat KebijakanKategori::terpakai()/sisa(), bukan dihitung
     * ulang di sini - aturan status mana yang memegang kursi tinggal di model
     * (STATUS_MEMAKAI_KUOTA), dan menyalinnya ke controller berarti dua tempat
     * yang bisa berbeda pendapat. Beberapa query tambahan di layar yang dibuka
     * sekali-sekali jauh lebih murah daripada risiko itu.
     */
    private function kuota(): array
    {
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        $gelombang = $tahunAktif
            ? GelombangPpdb::where('tahun_ajaran_id', $tahunAktif->id)
                ->where('status_buka', true)
                ->latest()
                ->first()
            : null;

        if ($gelombang === null) {
            return ['gelombang' => null, 'tanggal_selesai' => null, 'kategori' => []];
        }

        $kategori = KebijakanKategori::with('kategoriSiswa')
            ->where('gelombang_ppdb_id', $gelombang->id)
            ->get()
            ->sortBy(fn (KebijakanKategori $k) => $k->kategoriSiswa->nama)
            ->values()
            ->map(fn (KebijakanKategori $k) => [
                'nama' => $k->kategoriSiswa->nama,
                'kuota' => $k->kuota,
                'terpakai' => $k->terpakai(),
                'sisa' => $k->sisa(),
                'penuh' => $k->penuh(),
            ]);

        return [
            'gelombang' => $gelombang->nama.' · '.$tahunAktif->nama,
            'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            'kategori' => $kategori->all(),
        ];
    }
}
