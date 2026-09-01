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
    /**
     * Beranda menjawab satu pertanyaan: "apa yang harus saya lakukan sekarang?"
     * Jadi tiap anak ditampilkan bersama SATU tindakan berikutnya, bukan
     * sekadar daftar status. Urutannya pun didahulukan yang butuh tindakan wali.
     */
    public function __invoke(Request $request): Response
    {
        $pendaftaran = PendaftaranPpdb::with([
            'kategoriSiswa', 'dokumen', 'waliMurid', 'gelombang.tahunAjaran', 'pembayaran', 'tagihanItem',
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
            // Dua tanggal yang DIPISAH, karena bobotnya beda jauh:
            //   jatuh_tempo      -> lewat = pendaftaran bisa ditutup, kursi lepas
            //   tanggal_cicilan  -> cuma keterangan, nggak berakibat apa-apa
            // Keduanya diambil dari gelombang & tahun ajaran MILIK pendaftaran
            // ini, bukan dari gelombang yang kebetulan sedang dibuka: kalau
            // sekolah sudah membuka Gelombang 2 sementara tagihan wali masih
            // nyangkut di Gelombang 1, yang berlaku tetap tenggat Gelombang 1 -
            // dan tetap tampil walau gelombangnya sudah ditutup.
            'jatuh_tempo' => $p->jatuhTempoMinimal()?->locale('id')->translatedFormat('d F Y'),
            'jatuh_tempo_lewat' => $p->jatuhTempoMinimal()?->isPast() ?? false,
            'tanggal_cicilan' => $p->tanggalPelunasanCicilan()?->locale('id')->translatedFormat('d F Y'),
            'menunggak' => $p->menunggak(),
            ...$this->tindakanBerikutnya($p),
        ]);

        $gelombang = GelombangPpdb::where('status_buka', true)->latest()->first();

        return Inertia::render('wali-murid/dashboard', [
            'daftarPendaftaran' => $daftar->sortByDesc('perlu_tindakan')->values(),
            // Tiga angka yang paling dicari wali begitu membuka beranda:
            // berapa anak yang didaftarkan, ada yang perlu diurus, dan
            // berapa lagi uang yang harus disiapkan.
            'ringkasan' => [
                'jumlah_anak' => $daftar->count(),
                'perlu_tindakan' => $daftar->where('perlu_tindakan', true)->count(),
                'total_sisa_tagihan' => $daftar->sum(fn ($d) => $d['sisa_tagihan'] ?? 0),
                ...$this->tenggatTerdekat($pendaftaran),
            ],
            // MURNI gerbang "boleh mendaftarkan anak baru atau nggak" - jangan
            // dipakai lagi buat menurunkan tenggat pembayaran, karena gelombang
            // yang sedang dibuka belum tentu gelombang milik pendaftaran wali.
            //
            // Query di atas cuma menyaring status_buka - sama persis dengan
            // gerbang di PendaftaranController. Jangan tambah gerbang berbasis
            // tanggal di sini, nanti tombol "daftar" hilang padahal rutenya
            // masih menerima pendaftaran.
            'gelombangDibuka' => $gelombang ? [
                'nama' => $gelombang->nama,
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
        ]);
    }

    /**
     * Jatuh tempo paling dekat di antara anak-anak yang belum mencapai minimal
     * bayar - HANYA itu, sengaja tidak mencampur tanggal cicilan. Kartu ini
     * memberi peringatan, jadi isinya harus cuma tanggal yang benar-benar
     * berakibat; mencampurnya dengan tanggal cicilan yang tidak berakibat bikin
     * peringatannya kehilangan arti, dan yang lebih buruk, tanggal cicilan yang
     * kebetulan lebih awal bisa menyembunyikan anak yang sebetulnya terancam.
     *
     * Anak yang daftar di gelombang berbeda punya jatuh tempo sendiri-sendiri,
     * jadi tanggalnya WAJIB disertai keterangan punya siapa. Satu tanggal
     * telanjang di layar wali yang punya empat anak nggak bisa ditindaklanjuti -
     * dia nggak tahu anak mana yang harus dibayari. Kalau tanggal itu dipakai
     * lebih dari satu anak, yang disebut jumlahnya, bukan salah satu namanya.
     */
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

    /**
     * Posisi pendaftaran pada alur 4 langkah (Registrasi - Formulir - Unggah
     * Berkas - Pembayaran), buat titik penanda di kartu. Angka = berapa langkah
     * yang sudah TUNTAS, jadi wali langsung lihat sejauh mana tanpa membaca.
     */
    private function tahapKe(PendaftaranPpdb $pendaftaran): int
    {
        // Registrasi akun + formulir sudah pasti tuntas kalau barisnya ada.
        // Pembayaran dihitung tuntas begitu statusnya 'diterima' - status itu
        // sendiri sudah menandakan minimal bayar tercapai, jadi nggak perlu
        // dihitung ulang di sini. Sisa cicilan sesudahnya nggak menarik mundur
        // langkah yang sudah tuntas.
        return match ($pendaftaran->status) {
            'draft', 'perlu_perbaikan' => 2,
            'diterima' => 4,
            default => 3, // diajukan, diverifikasi, ditolak
        };
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

        // Belum sampai minimal bayar: INI satu-satunya kondisi pembayaran yang
        // bisa berujung penolakan, jadi ini yang disorot sebagai perlu tindakan.
        if (! $pendaftaran->sudahPenuhiMinimal()) {
            $kurang = $this->rupiah($pendaftaran->kurangMinimal());

            return [
                'tindakan' => "Bayar {$kurang} lagi supaya pendaftaran diterima",
                'tombol' => 'Bayar Sekarang',
                'rute' => 'pembayaran',
                'perlu_tindakan' => true,
            ];
        }

        // Sudah diterima, tinggal cicilan. Sengaja TIDAK ditandai perlu tindakan
        // selama belum jatuh tempo - kalau tiap cicilan yang belum lewat tenggat
        // ikut dihitung, angka "perlu tindakan" nggak pernah nol dan lonceng itu
        // berhenti berarti apa-apa buat wali.
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
