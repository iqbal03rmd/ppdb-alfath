<?php

namespace App\Http\Controllers\StafPpdb;

use App\Http\Controllers\Controller;
use App\Models\BerkasPersyaratan;
use App\Models\GelombangPpdb;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\TagihanItem;
use App\Models\TahunAjaran;
use App\Models\WaliMurid;
use App\Services\NotifikasiWhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
     * Karena itu daftar ini tidak menyediakan keputusan verifikasi langsung.
     * Aksi operasional yang memang membutuhkan konteks satu anak—seperti
     * pencatatan tunai—baru tersedia sesudah staf membuka detailnya.
     */
    public function index(Request $request): Response
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
            'filterAwal' => $this->filterAwal($request),
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
    private function filterAwal(Request $request): array
    {
        $status = in_array($request->query('status'), [
            'draft', 'diajukan', 'perlu_perbaikan', 'pembayaran', 'diterima', 'ditolak',
        ], true) ? $request->query('status') : '';

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        // Dicari khusus di dalam tahun ajaran aktif. Gelombang yang masih
        // status_buka milik tahun lama bisa bernama gelombang yang tidak ada
        // di tahun aktif - dua penyaring itu lalu saling meniadakan, dan
        // halaman terbuka dengan tabel kosong tanpa sebab yang kelihatan.
        //
        // SENGAJA memakai status_buka mentah, bukan scope menerimaPendaftar().
        // Ini cuma nilai awal sebuah penyaring, bukan gerbang: begitu jendela
        // Gelombang 1 lewat, arsip yang paling berguna dibuka staf tetap
        // Gelombang 1 - di situ datanya. Memakai scope bikin penyaringnya
        // kosong dan halaman membuka seluruh arsip lintas gelombang.
        $gelombangAktif = $tahunAktif
            ? GelombangPpdb::where('tahun_ajaran_id', $tahunAktif->id)
                ->where('status_buka', true)
                ->latest()
                ->first()
            : null;

        return [
            // Tautan dari dashboard menghitung seluruh data lintas tahun. Saat
            // membawa status, jangan diam-diam mempersempitnya lagi ke tahun
            // dan gelombang aktif sehingga angka kartu berbeda dari isi tabel.
            'tahunAjaran' => $status === '' ? ($tahunAktif?->nama ?? '') : '',
            'gelombang' => $status === '' ? ($gelombangAktif?->nama ?? '') : '',
            // Status hanya diisi saat halaman dibuka dari ringkasan dashboard.
            // Nilai lain diabaikan agar query string tidak bisa membuat tabel
            // tampak kosong dengan status yang sebenarnya tidak pernah ada.
            'status' => $status,
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
     * Keputusan formulir dan bukti transfer tetap dilakukan di antrian masing-
     * masing. Satu pengecualian adalah pencatatan tunai: staf perlu memastikan
     * anak dan sisa tagihannya dari layar lengkap ini sebelum menyimpan uang.
     */
    public function show(PendaftaranPpdb $pendaftaran): Response
    {
        $pendaftaran->load([
            'kategoriSiswa', 'gelombang.tahunAjaran', 'waliMurid', 'dokumen',
            'pembayaran.diverifikasiOleh', 'tagihanItem', 'user', 'diverifikasiOleh',
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
                // Pertanyaannya ikut dikirim, bukan dibaca ulang dari jalurnya:
                // ini yang ditanyakan waktu wali mengisi, bukan yang berlaku
                // sekarang.
                'pertanyaan_khusus' => $pendaftaran->pertanyaan_khusus,
                'jawaban_khusus' => $pendaftaran->jawaban_khusus,
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
                    'label' => BerkasPersyaratan::peta()[$jenis] ?? $jenis,
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
            'bisaCatatTunai' => $tagihanTerbit
                && $pendaftaran->bolehBayar()
                && $pendaftaran->sisaTagihan() > 0
                && ! $pendaftaran->adaPembayaranPending(),
            'adaPembayaranMenunggu' => $tagihanTerbit && $pendaftaran->adaPembayaranPending(),
            'tanggalHariIni' => today()->toDateString(),
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
            // Semua pembayaran, termasuk transfer yang ditolak - ini riwayat,
            // bukan saldo. Pembayaran tunai langsung sah dan tidak mempunyai
            // bukti transfer untuk dibuka.
            'riwayatPembayaran' => $pendaftaran->pembayaran->sortByDesc('tanggal_transfer')->values()
                ->map(fn (PembayaranPpdb $p) => [
                    'id' => $p->id,
                    'nominal_transfer' => $p->nominal_transfer,
                    'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                    'metode_pembayaran' => $p->metode_pembayaran,
                    'status' => $p->status,
                    'catatan_verifikasi' => $p->catatan_verifikasi,
                    'dicatat_oleh' => $p->metode_pembayaran === 'tunai' ? $p->diverifikasiOleh?->name : null,
                ]),
        ]);
    }

    /**
     * Catat pembayaran yang diserahkan langsung ke sekolah.
     *
     * Tunai tidak masuk antrian verifikasi karena staf yang menerima uangnya
     * sekaligus menjadi pemeriksa. Barisnya langsung terverifikasi, tetapi
     * tetap disimpan di tabel pembayaran yang sama agar saldo, status anak,
     * laporan, dan notifikasi memakai satu sumber data.
     */
    public function catatPembayaranTunai(
        Request $request,
        PendaftaranPpdb $pendaftaran,
        NotifikasiWhatsAppService $notifikasi
    ): RedirectResponse {
        $data = $request->validate(
            [
                'nominal_pembayaran' => ['required', 'integer', 'min:1'],
                'tanggal_pembayaran' => ['required', 'date', 'before_or_equal:today'],
            ],
            [
                'nominal_pembayaran.required' => 'Masukkan nominal pembayaran tunai.',
                'nominal_pembayaran.integer' => 'Nominal pembayaran harus berupa angka bulat.',
                'nominal_pembayaran.min' => 'Nominal pembayaran minimal Rp1.',
                'tanggal_pembayaran.required' => 'Pilih tanggal pembayaran.',
                'tanggal_pembayaran.before_or_equal' => 'Tanggal pembayaran tidak boleh melewati hari ini.',
            ]
        );

        $statusSebelum = $pendaftaran->status;

        /** @var PembayaranPpdb $pembayaran */
        $pembayaran = DB::transaction(function () use ($data, $request, $pendaftaran) {
            $terkunci = PendaftaranPpdb::query()
                ->whereKey($pendaftaran->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $terkunci->load(['pembayaran', 'tagihanItem']);

            if (! $terkunci->bolehBayar()) {
                throw ValidationException::withMessages([
                    'nominal_pembayaran' => 'Pendaftaran ini sedang tidak menerima pembayaran baru.',
                ]);
            }

            if (! $terkunci->tagihanSudahTerbit()) {
                throw ValidationException::withMessages([
                    'nominal_pembayaran' => 'Tagihan pendaftaran ini belum tersedia.',
                ]);
            }

            if ($terkunci->adaPembayaranPending()) {
                throw ValidationException::withMessages([
                    'nominal_pembayaran' => 'Masih ada transfer yang menunggu pemeriksaan. Selesaikan transfer itu sebelum mencatat pembayaran tunai.',
                ]);
            }

            $sisaTagihan = $terkunci->sisaTagihan();

            if ($sisaTagihan <= 0) {
                throw ValidationException::withMessages([
                    'nominal_pembayaran' => 'Tagihan pendaftaran ini sudah lunas.',
                ]);
            }

            if ((int) $data['nominal_pembayaran'] > $sisaTagihan) {
                throw ValidationException::withMessages([
                    'nominal_pembayaran' => 'Nominal tunai melebihi sisa tagihan sebesar '.$this->rupiah($sisaTagihan).'.',
                ]);
            }

            $pembayaranBaru = $terkunci->pembayaran()->create([
                'diverifikasi_oleh' => $request->user()->id,
                'nominal_transfer' => (int) $data['nominal_pembayaran'],
                'tanggal_transfer' => $data['tanggal_pembayaran'],
                'metode_pembayaran' => 'tunai',
                'bukti_transfer' => null,
                'status' => 'terverifikasi',
            ]);

            $terkunci->unsetRelation('pembayaran');
            $terkunci->segarkanStatusPenerimaan();

            return $pembayaranBaru;
        });

        $statusSesudah = $pendaftaran->refresh()->status;
        $notifikasi->pembayaranDiterima($pembayaran->refresh());

        $pesan = 'Pembayaran tunai '.$this->rupiah($pembayaran->nominal_transfer).' berhasil dicatat.';

        if ($statusSesudah !== $statusSebelum) {
            $pesan .= " Status pendaftaran ikut berubah menjadi '{$statusSesudah}'.";
        }

        return to_route('staf-ppdb.pendaftaran.show', $pendaftaran)
            ->with('success', $pesan.' Notifikasi WhatsApp diproses sesuai pengaturan wali.');
    }

    private function rupiah(int $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
