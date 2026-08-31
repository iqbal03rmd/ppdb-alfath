<?php

namespace App\Http\Controllers\WaliMurid;

use App\Http\Controllers\Controller;
use App\Models\GelombangPpdb;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Beranda menjawab satu pertanyaan: "apa yang harus saya lakukan sekarang?"
     * Jadi tiap anak ditampilkan bersama SATU tindakan berikutnya, bukan
     * sekadar daftar status. Urutannya pun didahulukan yang butuh tindakan wali.
     */
    public function __invoke(Request $request): Response
    {
        $daftar = PendaftaranPpdb::with([
            'kategoriSiswa', 'dokumen', 'waliMurid', 'gelombang', 'pembayaran', 'tagihanItem',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (PendaftaranPpdb $p) => [
                'id' => $p->id,
                'nomor_pendaftaran' => $p->nomor_pendaftaran,
                'nama_pendaftar' => $p->nama_pendaftar,
                'kategori' => $p->kategoriSiswa->nama,
                'status' => $p->status,
                'catatan_verifikasi' => $p->catatan_verifikasi,
                'sisa_tagihan' => $p->bolehLihatTagihan() ? $p->sisaTagihan() : null,
                'status_pelunasan' => $p->statusPelunasan(),
                ...$this->tindakanBerikutnya($p),
            ]);

        $gelombang = GelombangPpdb::where('status_buka', true)->latest()->first();

        return Inertia::render('wali-murid/dashboard', [
            'daftarPendaftaran' => $daftar->sortByDesc('perlu_tindakan')->values(),
            'gelombangDibuka' => $gelombang ? [
                'nama' => $gelombang->nama,
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
        ]);
    }

    /**
     * Satu kalimat tindakan + tautannya, diturunkan dari status pendaftaran dan
     * status pelunasan. 'perlu_tindakan' menandai yang bolanya ada di wali -
     * dipakai buat mengurutkan dan menyorot.
     */
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
        return match ($pendaftaran->statusPelunasan()) {
            'lunas' => [
                'tindakan' => $pendaftaran->status === 'diterima'
                    ? 'Selamat, pendaftaran diterima dan tagihan sudah lunas'
                    : 'Tagihan lunas, menunggu keputusan akhir dari sekolah',
                'tombol' => null,
                'rute' => null,
                'perlu_tindakan' => false,
            ],
            'menunggu_verifikasi' => [
                'tindakan' => 'Bukti transfer sedang diperiksa Staf PPDB',
                'tombol' => 'Lihat Status',
                'rute' => 'pembayaran',
                'perlu_tindakan' => false,
            ],
            'ditolak' => [
                'tindakan' => 'Bukti transfer ditolak — silakan unggah ulang',
                'tombol' => 'Unggah Ulang',
                'rute' => 'pembayaran',
                'perlu_tindakan' => true,
            ],
            'dicicil' => [
                'tindakan' => 'Masih ada sisa tagihan yang perlu dilunasi',
                'tombol' => 'Bayar Sisa',
                'rute' => 'pembayaran',
                'perlu_tindakan' => true,
            ],
            default => [
                'tindakan' => 'Berkas sudah diverifikasi. Silakan lakukan pembayaran',
                'tombol' => 'Bayar Sekarang',
                'rute' => 'pembayaran',
                'perlu_tindakan' => true,
            ],
        };
    }
}
