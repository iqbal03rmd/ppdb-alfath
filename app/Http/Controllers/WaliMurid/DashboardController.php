<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Models\GelombangPpdb;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    
    public function __invoke(Request $request): Response
    {
        $pendaftaran = PendaftaranPpdb::with([
            'kategoriSiswa', 'dokumen', 'waliMurid', 'gelombang.tahunAjaran', 'gelombang.dokumenWajib', 'pembayaran', 'tagihanItem',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $daftar = $pendaftaran->map(fn (PendaftaranPpdb $p) => [
            'id' => $p->id,
            'nomor_pendaftaran' => $p->nomor_pendaftaran,
            'nama_pendaftar' => $p->nama_pendaftar,
            'kategori' => $p->kategoriSiswa->nama,
            'status' => $p->status,
            'catatan_verifikasi' => $p->catatan_verifikasi,
            'sisa_tagihan' => $p->bolehLihatTagihan() ? $p->sisaTagihan() : null,
            'tahap' => $this->tahapKe($p),
            'tahap_total' => 4,
            'jatuh_tempo' => $p->jatuhTempoMinimal()?->locale('id')->translatedFormat('d F Y'),
            'jatuh_tempo_lewat' => $p->jatuhTempoMinimal()?->isPast() ?? false,
            'tanggal_cicilan' => $p->tanggalPelunasanCicilan()?->locale('id')->translatedFormat('d F Y'),
            'menunggak' => $p->menunggak(),
            ...$this->tindakanBerikutnya($p),
        ]);

        $gelombang = GelombangPpdb::where('status_buka', true)->latest()->first();

        return Inertia::render('wali-murid/dashboard', [
            'daftarPendaftaran' => $daftar->sortByDesc('perlu_tindakan')->values(),
            'ringkasan' => [
                'jumlah_anak' => $daftar->count(),
                'perlu_tindakan' => $daftar->where('perlu_tindakan', true)->count(),
                'total_sisa_tagihan' => $daftar->sum(fn ($d) => $d['sisa_tagihan'] ?? 0),
                ...$this->tenggatTerdekat($pendaftaran),
            ],
            'gelombangDibuka' => $gelombang ? [
                'nama' => $gelombang->nama,
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
        ]);
    }

    private function tenggatTerdekat($pendaftaran): array
    {
        $berjatuhTempo = $pendaftaran
            ->map(fn (PendaftaranPpdb $p) => [
                'nama' => $p->nama_pendaftar,
                'tenggat' => $p->jatuhTempoMinimal(),
            ])
            ->filter(fn (array $x) => $x['tenggat'] instanceof Carbon)
            ->sortBy(fn (array $x) => $x['tenggat']->getTimestamp())
            ->values();

        $paling = $berjatuhTempo->first();

        if ($paling === null) {
            return [
                'tenggat_terdekat' => null,
                'tenggat_terdekat_lewat' => false,
                'tenggat_terdekat_untuk' => null,
            ];
        }

        $seharian = $berjatuhTempo->filter(
            fn (array $x) => $x['tenggat']->isSameDay($paling['tenggat'])
        );

        return [
            'tenggat_terdekat' => $paling['tenggat']->locale('id')->translatedFormat('d F Y'),
            'tenggat_terdekat_lewat' => $paling['tenggat']->isPast(),
            'tenggat_terdekat_untuk' => $seharian->count() === 1
                ? $paling['nama']
                : $seharian->count().' anak',
        ];
    }

    private function tahapKe(PendaftaranPpdb $pendaftaran): int
    {
        return match ($pendaftaran->status) {
            'draft', 'perlu_perbaikan' => 2,
            'diterima' => 4,
            default => 3, // diajukan, diverifikasi, ditolak
        };
    }

    private function tindakanBerikutnya(PendaftaranPpdb $pendaftaran): array
    {
        $berkasKurang = count($pendaftaran->dokumenKurang());

        if ($pendaftaran->status === 'draft') {
            return $berkasKurang > 0
                ? [
                    'tindakan' => "Lengkapi {$berkasKurang} berkas lagi, lalu kirim untuk diverifikasi",
                    'tombol' => 'Lanjutkan Pendaftaran',
                    'rute' => 'unggah-berkas',
                    'perlu_tindakan' => true,
                ]
                : [
                    'tindakan' => 'Berkas sudah lengkap — tinggal dikirim untuk diverifikasi',
                    'tombol' => 'Kirim Berkas',
                    'rute' => 'unggah-berkas',
                    'perlu_tindakan' => true,
                ];
        }

        if ($pendaftaran->status === 'perlu_perbaikan') {
            return [
                'tindakan' => 'Staf meminta perbaikan data. Betulkan lalu kirim ulang',
                'tombol' => 'Perbaiki Sekarang',
                'rute' => 'pendaftaran',
                'perlu_tindakan' => true,
            ];
        }

        if ($pendaftaran->status === 'diajukan') {
            return [
                'tindakan' => 'Berkas sedang diperiksa Staf PPDB. Tidak ada yang perlu kamu lakukan',
                'tombol' => null,
                'rute' => null,
                'perlu_tindakan' => false,
            ];
        }

        if ($pendaftaran->status === 'ditolak') {
            return [
                'tindakan' => 'Pendaftaran ini ditutup sekolah',
                'tombol' => 'Lihat Rincian',
                'rute' => 'pendaftaran',
                'perlu_tindakan' => false,
            ];
        }

        // diverifikasi / diterima - tinggal urusan pembayaran
        if ($pendaftaran->tagihanSudahTerbit() && $pendaftaran->sisaTagihan() <= 0) {
            return [
                'tindakan' => $pendaftaran->status === 'diterima'
                    ? 'Selamat, pendaftaran diterima dan seluruh biaya sudah lunas'
                    : 'Seluruh biaya sudah lunas, menunggu keputusan akhir dari sekolah',
                'tombol' => null,
                'rute' => null,
                'perlu_tindakan' => false,
            ];
        }

        if ($pendaftaran->adaPembayaranPending()) {
            return [
                'tindakan' => 'Bukti transfer sedang diperiksa Staf PPDB',
                'tombol' => 'Lihat Status',
                'rute' => 'pembayaran',
                'perlu_tindakan' => false,
            ];
        }

        if ($pendaftaran->statusPelunasan() === 'ditolak') {
            return [
                'tindakan' => 'Bukti transfer ditolak — silakan unggah ulang',
                'tombol' => 'Unggah Ulang',
                'rute' => 'pembayaran',
                'perlu_tindakan' => true,
            ];
        }

        if (! $pendaftaran->sudahPenuhiMinimal()) {
            $kurang = $this->rupiah($pendaftaran->kurangMinimal());

            return [
                'tindakan' => "Bayar {$kurang} lagi supaya pendaftaran diterima",
                'tombol' => 'Bayar Sekarang',
                'rute' => 'pembayaran',
                'perlu_tindakan' => true,
            ];
        }

        $sisa = $this->rupiah($pendaftaran->sisaTagihan());

        return [
            'tindakan' => $pendaftaran->menunggak()
                ? "Diterima, tapi sisa cicilan {$sisa} sudah melewati batas pelunasan"
                : "Diterima. Sisa cicilan {$sisa} boleh dilunasi bertahap",
            'tombol' => 'Bayar Cicilan',
            'rute' => 'pembayaran',
            'perlu_tindakan' => $pendaftaran->menunggak(),
        ];
    }

    private function rupiah(int $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
