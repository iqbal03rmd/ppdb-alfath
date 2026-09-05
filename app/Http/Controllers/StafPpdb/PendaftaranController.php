<?php

namespace App\Http\Controllers\StafPpdb;

use App\Http\Controllers\Controller;
use App\Models\DokumenPpdb;
use App\Models\GelombangPpdb;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\TagihanItem;
use App\Models\TahunAjaran;
use App\Models\WaliMurid;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PendaftaranController extends Controller
{
    /**
     * Seluruh pendaftaran, segala status.
     *
     * Bedanya dengan dua antrian verifikasi: antrian itu MENGOSONG - isinya cuma
     * yang menunggu dikerjakan, dan hilang begitu diputuskan. Halaman ini
     * MENUMPUK, dan dipakai waktu staf perlu mencari data seseorang, mis. saat
     * wali menelepon menanyakan anaknya.
     *
     * Karena itu tidak ada satu pun tombol yang mengubah status di sini. Semua
     * keputusan tetap diambil di halaman verifikasinya masing-masing, supaya
     * tidak ada dua tempat yang bisa mengubah hal yang sama.
     */
    public function index(): Response
    {
        $pendaftaran = PendaftaranPpdb::with([
            // tahunAjaran ikut di-load karena dipakai buat filter di layar. Tanpa
            // ini tiap baris menembak query sendiri (N+1) - dan halaman inilah
            // yang paling panjang daftarnya.
            'kategoriSiswa', 'gelombang.tahunAjaran', 'pembayaran', 'tagihanItem',
        ])
            // Terbaru di atas - kebalikan dari antrian verifikasi yang justru
            // mendahulukan yang paling lama menunggu.
            ->latest()
            ->get()
            ->map(fn (PendaftaranPpdb $p) => [
                'id' => $p->id,
                'nomor_pendaftaran' => $p->nomor_pendaftaran,
                'nama_pendaftar' => $p->nama_pendaftar,
                'kategori' => $p->kategoriSiswa->nama,
                // Dua kolom ini jadi dua penyaring yang berdiri sendiri di layar.
                // Gelombang disaring lewat NAMANYA, jadi menyaring "Gelombang 1"
                // tanpa memilih tahun ajaran memang berarti Gelombang 1 dari
                // semua angkatan - itu yang dimaksud, bukan kecelakaan.
                'gelombang' => $p->gelombang->nama,
                'tahun_ajaran' => $p->gelombang->tahunAjaran->nama,
                'status' => $p->status,
                // Pendaftaran yang belum boleh melihat tagihan (draft, diajukan)
                // memang belum punya urusan pembayaran - jangan ditampilkan
                // "belum bayar", seolah-olah dia menunggak.
                'status_pelunasan' => $p->bolehLihatTagihan() ? $p->statusPelunasan() : null,
                'sisa_tagihan' => $p->bolehLihatTagihan() ? $p->sisaTagihan() : null,
                'tanggal_daftar' => $p->created_at->locale('id')->translatedFormat('d M Y'),
            ]);

        return Inertia::render('staf-ppdb/pendaftaran', [
            'pendaftaran' => $pendaftaran,
            'filterAwal' => $this->filterAwal(),
        ]);
    }

    /**
     * Posisi awal penyaring saat halaman dibuka: tahun ajaran yang aktif dan
     * gelombang yang sedang dibuka. Itu yang paling sering ditanyakan orang,
     * jadi staf tidak perlu menyetel apa-apa dulu untuk pekerjaan sehari-hari.
     *
     * Ini SEKADAR posisi awal, bukan pembatasan - tahun ajaran lain tetap bisa
     * dipilih, dan tombol "Tampilkan semua" mengembalikan seluruh arsip.
     *
     * Ditentukan di sini, bukan di TSX, supaya layar tidak menebak-nebak mana
     * yang aktif dari deretan baris yang kebetulan tampil.
     *
     * Sengaja tidak memakai ulang PendaftaranController::gelombangDibuka()
     * milik wali: method itu menjawab "boleh mendaftarkan anak baru atau
     * tidak", dan menyeretnya ke sini bikin aturan pendaftaran ikut berubah
     * tiap kali tampilan arsip mau disetel lain.
     *
     * Kalau tidak ada yang aktif, hasilnya string kosong - artinya "semua",
     * dan staf melihat seluruh arsip apa adanya.
     */
    private function filterAwal(): array
    {
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        // Dicari khusus di dalam tahun ajaran aktif. Gelombang yang masih
        // status_buka milik tahun lama bisa bernama gelombang yang tidak ada
        // di tahun aktif - dua penyaring itu lalu saling meniadakan, dan
        // halaman terbuka dengan tabel kosong tanpa sebab yang kelihatan.
        $gelombangAktif = $tahunAktif
            ? GelombangPpdb::where('tahun_ajaran_id', $tahunAktif->id)
                ->where('status_buka', true)
                ->latest()
                ->first()
            : null;

        return [
            'tahunAjaran' => $tahunAktif?->nama ?? '',
            'gelombang' => $gelombangAktif?->nama ?? '',
        ];
    }

    /**
     * Rekam lengkap satu pendaftaran - biodata, wali, berkas, DAN posisi
     * pembayarannya, dalam satu layar.
     *
     * Halaman ini sengaja berdiri sendiri, tidak menumpang halaman periksa milik
     * Verifikasi Pendaftaran, karena tujuannya beda: yang di sana untuk
     * MEMUTUSKAN (berkas ditampilkan besar-besar, ada tombol aksi), yang di sini
     * untuk MENJAWAB - staf sedang ditelepon wali dan butuh semuanya sekaligus,
     * terutama angka pembayaran yang justru tidak ada di halaman periksa.
     *
     * Sama seperti index-nya, halaman ini read-only. Yang ada cuma tautan ke
     * halaman tempat keputusan diambil, bukan tombol keputusannya sendiri.
     */
    public function show(PendaftaranPpdb $pendaftaran): Response
    {
        $pendaftaran->load([
            'kategoriSiswa', 'gelombang.tahunAjaran', 'waliMurid', 'dokumen',
            'pembayaran', 'tagihanItem', 'user', 'diverifikasiOleh',
        ]);

        // Sengaja TIDAK memanggil terbitkanTagihan(), padahal halaman pembayaran
        // wali memanggilnya. Penerbitan tagihan itu peristiwa milik wali: tarif
        // dibekukan pada saat DIA pertama kali melihat tagihannya. Kalau staf
        // yang sekadar membuka arsip ikut menerbitkan, titik beku tarif berpindah
        // ke tangan orang yang bukan sedang ditagih - dan wali bisa terkunci pada
        // tarif versi lain dari yang pernah dia lihat.
        $bolehLihatTagihan = $pendaftaran->bolehLihatTagihan();
        $tagihanTerbit = $bolehLihatTagihan && $pendaftaran->tagihanSudahTerbit();

        return Inertia::render('staf-ppdb/pendaftaran-show', [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'status' => $pendaftaran->status,
                'kategori' => $pendaftaran->kategoriSiswa->nama,
                'gelombang' => $pendaftaran->gelombang->nama,
                'tahun_ajaran' => $pendaftaran->gelombang->tahunAjaran->nama,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
                'nik' => $pendaftaran->nik,
                'tempat_lahir' => $pendaftaran->tempat_lahir,
                'tanggal_lahir' => $pendaftaran->tanggal_lahir->locale('id')->translatedFormat('d F Y'),
                'jenis_kelamin' => $pendaftaran->jenis_kelamin,
                'agama' => $pendaftaran->agama,
                'alamat' => $pendaftaran->alamat,
                'nama_saudara' => $pendaftaran->nama_saudara,
                'nama_orang_tua_guru' => $pendaftaran->nama_orang_tua_guru,
                'catatan_verifikasi' => $pendaftaran->catatan_verifikasi,
                'diperiksa_oleh' => $pendaftaran->diverifikasiOleh?->name,
                'akun_pendaftar' => $pendaftaran->user->name.' ('.$pendaftaran->user->email.')',
                'tanggal_daftar' => $pendaftaran->created_at->locale('id')->translatedFormat('d F Y'),
            ],
            'waliMurid' => $pendaftaran->waliMurid->map(fn (WaliMurid $w) => [
                'nama' => $w->nama,
                'nik' => $w->nik,
                'hubungan' => $w->hubungan,
                'telepon' => $w->telepon,
            ]),
            // Seluruh dokumen WAJIB ditampilkan, termasuk yang belum diunggah -
            // staf perlu melihat lubangnya, bukan cuma yang sudah ada.
            'berkas' => collect($pendaftaran->dokumenWajib())->map(function (string $jenis) use ($pendaftaran) {
                $dokumen = $pendaftaran->dokumen->firstWhere('jenis_dokumen', $jenis);

                return [
                    'jenis' => $jenis,
                    'label' => DokumenPpdb::LABEL[$jenis] ?? $jenis,
                    'terunggah' => $dokumen !== null,
                    'url' => $dokumen ? Storage::url($dokumen->berkas) : null,
                ];
            }),
            // Null = memang belum ada angka apa pun untuk ditampilkan. Dua sebab
            // kosongnya dibedakan lewat 'sebabTanpaTagihan' di bawah, karena buat
            // staf yang sedang ditelepon dua sebab itu jawabannya beda jauh.
            'ringkasanPembayaran' => $tagihanTerbit ? [
                'statusPelunasan' => $pendaftaran->statusPelunasan(),
                'totalTagihan' => $pendaftaran->totalTagihan(),
                'totalTerbayar' => $pendaftaran->totalTerbayar(),
                'sisaTagihan' => $pendaftaran->sisaTagihan(),
                'minimalBayar' => $pendaftaran->minimalBayar(),
                'kurangMinimal' => $pendaftaran->kurangMinimal(),
                'sudahPenuhiMinimal' => $pendaftaran->sudahPenuhiMinimal(),
                'menunggak' => $pendaftaran->menunggak(),
                // Cuma SATU yang boleh disebut "jatuh tempo": batas minimal bayar.
                // Tanggal cicilan dikirim terpisah dan dilabeli lain di layar,
                // karena lewatnya tidak berakibat apa-apa di sistem.
                'jatuhTempoMinimal' => $pendaftaran->jatuhTempoMinimal()?->locale('id')->translatedFormat('d F Y'),
                'tanggalPelunasanCicilan' => $pendaftaran->tanggalPelunasanCicilan()?->locale('id')->translatedFormat('d F Y'),
            ] : null,
            'sebabTanpaTagihan' => $tagihanTerbit ? null : ($bolehLihatTagihan
                ? 'Tagihan belum terbit. Wali belum pernah membuka halaman pembayarannya, jadi rincian tagihannya belum dibekukan.'
                : 'Pendaftaran ini belum sampai tahap pembayaran. Tagihan baru terbit setelah pendaftarannya diverifikasi.'),
            'rincianTagihan' => $tagihanTerbit
                ? $pendaftaran->tagihanItem->sortBy('id')->values()->map(fn (TagihanItem $i) => [
                    'nama' => $i->nama_komponen,
                    'nominal' => $i->nominal,
                ])
                : [],
            // Semua transfer, termasuk yang ditolak - ini riwayat, bukan saldo.
            // Tiap baris menaut ke halaman periksanya sendiri; itu satu-satunya
            // tempat transfer boleh disahkan atau dibatalkan.
            'riwayatTransfer' => $pendaftaran->pembayaran->sortByDesc('tanggal_transfer')->values()
                ->map(fn (PembayaranPpdb $p) => [
                    'id' => $p->id,
                    'nominal_transfer' => $p->nominal_transfer,
                    'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                    'status' => $p->status,
                    'catatan_verifikasi' => $p->catatan_verifikasi,
                ]),
        ]);
    }
}
