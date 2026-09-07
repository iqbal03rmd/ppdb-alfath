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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Tutup pendaftaran: status jadi 'ditolak', dan kursi kuotanya terlepas.
     *
     * Dua sebab yang bermuara ke sini, keduanya ditulis staf di catatan:
     *
     *   1. Syarat tidak akan terpenuhi - mis. jalur Anak Yatim yang surat
     *      kematiannya tidak ada. Ditutup dari 'diajukan'/'perlu_perbaikan'.
     *   2. Tidak mencapai minimal bayar sampai tenggat gelombangnya.
     *
     * Sengaja TIDAK ada di halaman verifikasi berkas. Di sana pilihan staf tetap
     * dua - setujui atau minta perbaikan - supaya wali selalu diberi kesempatan
     * membetulkan dulu. Menutup itu langkah sesudah kesempatan itu gagal.
     *
     * Tidak ada otomatisasi yang boleh memanggil ini. Menolak melepas kursi yang
     * bisa langsung diambil keluarga lain, dan tidak ada jalan kembali - jadi
     * pemicunya harus manusia yang menyaksikan datanya. Alasan lengkapnya di PRD.
     */
    public function tutup(Request $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        // Guard di server, bukan cuma menyembunyikan tombol: rutenya bisa
        // ditembak langsung, dan dua staf bisa membuka layar yang sama.
        abort_unless($pendaftaran->bisaDitutup(), 403, 'Pendaftaran ini sedang tidak bisa ditutup.');

        // Alasannya kalimat bebas, sama seperti permintaan perbaikan. Wajib
        // diisi: itu satu-satunya keterangan yang sampai ke wali soal kenapa
        // pendaftarannya ditutup - apalagi kalau dia terlanjur menyetor uang.
        $data = $request->validate(
            ['catatan_verifikasi' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'catatan_verifikasi.required' => 'Tulis dulu alasan penutupannya.',
                'catatan_verifikasi.min' => 'Alasannya terlalu pendek. Wali berhak tahu persis kenapa pendaftarannya ditutup.',
            ]
        );

        $pendaftaran->update([
            'status' => 'ditolak',
            'catatan_verifikasi' => $data['catatan_verifikasi'],
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        return to_route('staf-ppdb.pendaftaran.show', $pendaftaran)
            ->with('success', "Pendaftaran {$pendaftaran->nomor_pendaftaran} ditutup. Kursi kuotanya sudah dilepas.");
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
            'bisaDitutup' => $pendaftaran->bisaDitutup(),
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
