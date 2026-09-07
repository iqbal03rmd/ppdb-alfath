<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\GelombangPpdb;
use App\Models\KuotaKategori;
use App\Models\PendaftaranPpdb;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modul Kepala Sekolah - MONITORING SAJA.
 *
 * Tidak ada satu pun aksi di seluruh modul ini: tidak ada tombol yang mengubah
 * status, menyetujui, atau menolak. Keputusan user 1 September 2026 (PRD 8.3):
 * Kepala Sekolah tidak ikut memutuskan diterima/ditolak. `diterima` tetap
 * dihitung otomatis dari minimal bayar, `ditolak` tetap ketetapan staf.
 *
 * Kalau nanti ada yang tergoda menambahkan tombol "Setujui" di sini, baca dulu
 * PRD bagian B - usul itu sudah pernah diajukan dan sengaja ditutup.
 *
 * Dua halaman, satu controller, karena keduanya menghitung dari kumpulan data
 * yang sama persis. Memisahnya jadi dua controller berarti menyalin agregasi
 * yang sama dua kali, dan dua salinan itu yang nanti berbeda pendapat.
 */
class LaporanController extends Controller
{
    /**
     * Beranda: gambaran satu layar. Angka besar, dua diagram, sisa daya tampung.
     */
    public function dashboard(): Response
    {
        $masuk = $this->pendaftaranMasuk();
        $gelombang = $this->gelombangBerjalan();

        return Inertia::render('kepala-sekolah/dashboard', [
            'ringkasan' => [
                'total' => $masuk->count(),
                'diterima' => $masuk->where('status', 'diterima')->count(),
                'diproses' => $masuk->whereIn('status', ['diajukan', 'perlu_perbaikan', 'diverifikasi'])->count(),
                'ditolak' => $masuk->where('status', 'ditolak')->count(),
            ],
            'statistik' => [
                'pendaftaran' => $masuk->countBy('status')->all(),
                'pembayaran' => $masuk
                    ->filter(fn (PendaftaranPpdb $p) => $p->bolehLihatTagihan())
                    ->countBy(fn (PendaftaranPpdb $p) => $p->statusPelunasan())
                    ->all(),
            ],
            'keuangan' => $this->keuangan($masuk),
            'gelombangBerjalan' => $gelombang ? [
                'nama' => $gelombang->nama.' · '.$gelombang->tahunAjaran->nama,
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
            'kuota' => $gelombang ? $this->kuota($gelombang) : [],
        ]);
    }

    /**
     * Rekapitulasi: angka yang sama, dipecah per gelombang dan per kategori.
     *
     * Seluruh gelombang dikirim sekaligus, termasuk tahun ajaran yang sudah
     * lewat - ini halaman laporan, dan membandingkan angkatan tahun ini dengan
     * tahun lalu justru gunanya. Penyaringnya di layar, bukan di query.
     */
    public function rekapitulasi(): Response
    {
        $masuk = $this->pendaftaranMasuk();

        $perGelombang = GelombangPpdb::with('tahunAjaran')
            ->get()
            ->sortByDesc(fn (GelombangPpdb $g) => $g->tahunAjaran->nama.$g->nama)
            ->values()
            ->map(function (GelombangPpdb $g) use ($masuk) {
                $isi = $masuk->where('gelombang_ppdb_id', $g->id);

                return [
                    'id' => $g->id,
                    'nama' => $g->nama,
                    'tahun_ajaran' => $g->tahunAjaran->nama,
                    'status_buka' => (bool) $g->status_buka,
                    'total' => $isi->count(),
                    'diproses' => $isi->whereIn('status', ['diajukan', 'perlu_perbaikan', 'diverifikasi'])->count(),
                    'diterima' => $isi->where('status', 'diterima')->count(),
                    'ditolak' => $isi->where('status', 'ditolak')->count(),
                    ...$this->keuangan($isi),
                ];
            });

        // Per kategori dipecah PER GELOMBANG juga, bukan ditotal lintas tahun:
        // kuota itu milik satu gelombang, jadi "sisa kuota" yang dijumlahkan
        // lintas gelombang tidak berarti apa-apa.
        //
        // Sengaja TIDAK mengirim terpakai() ke layar. Angkanya selalu sama dengan
        // total - ditolak, jadi menampilkannya sebagai kolom sendiri berarti tiga
        // angka yang saling menerangkan hal yang sama; pembacanya malah bertanya
        // kenapa "Pendaftar" dan "Terpakai" beda tipis (keputusan user 7 September
        // 2026). Yang tampil cukup 'ditolak' - itu yang menerangkan kenapa Sisa
        // tidak sama dengan Kuota - Pendaftar. 'sisa' sendiri tetap lewat model,
        // bukan dihitung ulang di sini.
        $perKategori = KuotaKategori::with(['kategoriSiswa', 'gelombang.tahunAjaran'])
            ->get()
            ->map(function (KuotaKategori $k) use ($masuk) {
                $isi = $masuk->where('gelombang_ppdb_id', $k->gelombang_ppdb_id)
                    ->where('kategori_siswa_id', $k->kategori_siswa_id);

                return [
                    'gelombang_id' => $k->gelombang_ppdb_id,
                    'gelombang' => $k->gelombang->nama,
                    'tahun_ajaran' => $k->gelombang->tahunAjaran->nama,
                    'kategori' => $k->kategoriSiswa->nama,
                    'total' => $isi->count(),
                    'diterima' => $isi->where('status', 'diterima')->count(),
                    'ditolak' => $isi->where('status', 'ditolak')->count(),
                    'kuota' => $k->kuota,
                    'sisa' => $k->sisa(),
                    'penuh' => $k->penuh(),
                ];
            })
            ->sortBy([['tahun_ajaran', 'desc'], ['gelombang', 'asc'], ['kategori', 'asc']])
            ->values();

        return Inertia::render('kepala-sekolah/rekapitulasi', [
            'perGelombang' => $perGelombang->all(),
            'perKategori' => $perKategori->all(),
            'tahunAjaran' => TahunAjaran::orderByDesc('nama')->pluck('nama')->all(),
            'filterAwal' => TahunAjaran::where('status_aktif', true)->value('nama') ?? '',
        ]);
    }

    /**
     * Semua pendaftaran KECUALI draft.
     *
     * Draft belum pernah dikirim wali: sekolah belum punya hubungan apa pun
     * dengannya, dan kursi kuota pun belum dipegang. Menghitungnya sebagai
     * "pendaftar" bikin angka di laporan lebih besar daripada kenyataannya.
     */
    private function pendaftaranMasuk(): Collection
    {
        return PendaftaranPpdb::with(['kategoriSiswa', 'gelombang.tahunAjaran', 'pembayaran', 'tagihanItem'])
            ->where('status', '!=', 'draft')
            ->get();
    }

    private function gelombangBerjalan(): ?GelombangPpdb
    {
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        if ($tahunAktif === null) {
            return null;
        }

        return GelombangPpdb::with('tahunAjaran')
            ->where('tahun_ajaran_id', $tahunAktif->id)
            ->where('status_buka', true)
            ->latest()
            ->first();
    }

    /**
     * Angka uang untuk sekumpulan pendaftaran.
     *
     * 'sudah_masuk' HANYA menghitung transfer yang sudah disahkan staf -
     * memakai totalTerbayar() milik model, bukan menjumlahkan sendiri. Bukti
     * yang masih menunggu diperiksa dilaporkan terpisah, karena sebagiannya
     * bisa saja ditolak; menggabungkannya berarti melaporkan uang yang belum
     * tentu ada.
     */
    private function keuangan(Collection $pendaftaran): array
    {
        $tagihan = $pendaftaran->sum(fn (PendaftaranPpdb $p) => $p->totalTagihan());
        $masuk = $pendaftaran->sum(fn (PendaftaranPpdb $p) => $p->totalTerbayar());

        $menunggu = $pendaftaran->sum(
            fn (PendaftaranPpdb $p) => $p->pembayaran
                ->where('status', 'menunggu_verifikasi')
                ->sum('nominal_transfer')
        );

        return [
            'total_tagihan' => (int) $tagihan,
            'sudah_masuk' => (int) $masuk,
            'menunggu_diperiksa' => (int) $menunggu,
            'sisa_tagihan' => (int) max(0, $tagihan - $masuk),
        ];
    }

    /**
     * Sisa daya tampung per kategori. Angkanya lewat KuotaKategori, bukan
     * dihitung ulang - aturan status mana yang memegang kursi tinggal di model.
     */
    private function kuota(GelombangPpdb $gelombang): array
    {
        return KuotaKategori::with('kategoriSiswa')
            ->where('gelombang_ppdb_id', $gelombang->id)
            ->get()
            ->sortBy(fn (KuotaKategori $k) => $k->kategoriSiswa->nama)
            ->values()
            ->map(fn (KuotaKategori $k) => [
                'nama' => $k->kategoriSiswa->nama,
                'kuota' => $k->kuota,
                'terpakai' => $k->terpakai(),
                'sisa' => $k->sisa(),
                'penuh' => $k->penuh(),
            ])
            ->all();
    }
}
