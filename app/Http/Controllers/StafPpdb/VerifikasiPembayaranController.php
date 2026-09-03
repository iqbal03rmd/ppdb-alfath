<?php

namespace App\Http\Controllers\StafPpdb;

use App\Http\Controllers\Controller;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VerifikasiPembayaranController extends Controller
{
    /**
     * Antrian bukti transfer yang menunggu diperiksa staf.
     *
     * Bedanya dengan antrian Verifikasi Pendaftaran: di sana satu barisnya satu
     * PENDAFTARAN, di sini satu barisnya satu TRANSFER. Satu pendaftaran bisa
     * muncul lebih dari sekali kalau walinya mencicil - dan itu memang benar,
     * karena tiap transfer diperiksa sendiri-sendiri.
     */
    public function index(): Response
    {
        $antrian = PembayaranPpdb::with(['pendaftaran.kategoriSiswa'])
            ->where('status', 'menunggu_verifikasi')
            // Yang paling lama menunggu didahulukan.
            ->oldest('created_at')
            ->get()
            ->map(fn (PembayaranPpdb $p) => [
                'id' => $p->id,
                'pendaftaran_id' => $p->pendaftaran_ppdb_id,
                'nomor_pendaftaran' => $p->pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $p->pendaftaran->nama_pendaftar,
                'kategori' => $p->pendaftaran->kategoriSiswa->nama,
                'nominal_transfer' => $p->nominal_transfer,
                'tanggal_transfer' => $p->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                'menunggu_sejak' => $p->created_at->locale('id')->translatedFormat('d F Y'),
            ]);

        return Inertia::render('staf-ppdb/verifikasi-pembayaran', [
            'antrian' => $antrian,
        ]);
    }

    /**
     * Halaman periksa satu bukti transfer.
     *
     * Selain buktinya sendiri, staf diberi konteks angkanya: sudah berapa yang
     * terverifikasi sebelum ini, dan JADI BERAPA kalau transfer ini disahkan.
     * Tanpa itu staf cuma melihat selembar bukti tanpa tahu artinya apa buat
     * pendaftaran yang bersangkutan.
     *
     * Yang paling penting ditonjolkan: kalau pengesahan ini membuat pembayaran
     * menyentuh minimal bayar, status pendaftaran naik sendiri jadi 'diterima'.
     * Staf harus tahu itu SEBELUM menekan tombol, bukan sesudahnya.
     *
     * Sengaja tidak dibatasi hanya status 'menunggu_verifikasi' - transfer yang
     * sudah diputuskan tetap boleh dibuka untuk dilihat lagi. Yang dijaga nanti
     * tombol aksinya, bukan hak melihatnya.
     */
    public function show(PembayaranPpdb $pembayaran): Response
    {
        $pembayaran->load([
            'pendaftaran.kategoriSiswa',
            'pendaftaran.pembayaran',
            'pendaftaran.tagihanItem',
            'pendaftaran.gelombang.tahunAjaran',
            'diverifikasiOleh',
        ]);

        $pendaftaran = $pembayaran->pendaftaran;

        // totalTerbayar() hanya menghitung yang berstatus 'terverifikasi'.
        $sudahTerverifikasi = $pendaftaran->totalTerbayar();
        $minimalBayar = (int) $pendaftaran->minimalBayar();

        // Proyeksi "kalau disahkan" cuma masuk akal selama transfernya memang
        // masih menunggu. Untuk transfer yang sudah terverifikasi, nominalnya
        // SUDAH ada di dalam $sudahTerverifikasi - menambahkannya lagi bikin
        // angka terhitung dua kali (pernah kejadian: tagihan 4jt, proyeksinya 8jt).
        $masihMenunggu = $pembayaran->status === 'menunggu_verifikasi';
        $setelahDisahkan = $masihMenunggu
            ? $sudahTerverifikasi + $pembayaran->nominal_transfer
            : $sudahTerverifikasi;

        // Arah sebaliknya: kalau pengesahan ini DICABUT, uangnya tinggal berapa,
        // dan apakah itu masih menutupi minimal bayar. Dipakai buat memberi tahu
        // staf akibat pembatalan sebelum tombolnya ditekan - pasangan dari
        // pemberitahuan yang sudah ada di sisi pengesahan.
        $sudahDisahkan = $pembayaran->status === 'terverifikasi';
        $setelahDibatalkan = $sudahDisahkan
            ? $sudahTerverifikasi - $pembayaran->nominal_transfer
            : $sudahTerverifikasi;

        // Transfer lain milik pendaftaran yang sama yang masih menunggu diperiksa.
        // Ini yang bikin pembatalan terasa mendadak buat wali: dia sudah terlanjur
        // mencicil lagi karena melihat dirinya diterima.
        $menungguLain = $pendaftaran->pembayaran
            ->where('id', '!=', $pembayaran->id)
            ->where('status', 'menunggu_verifikasi');

        return Inertia::render('staf-ppdb/verifikasi-pembayaran-show', [
            'pembayaran' => [
                'id' => $pembayaran->id,
                'status' => $pembayaran->status,
                'nominal_transfer' => $pembayaran->nominal_transfer,
                'tanggal_transfer' => $pembayaran->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                'diunggah_pada' => $pembayaran->created_at->locale('id')->translatedFormat('d F Y'),
                'catatan_verifikasi' => $pembayaran->catatan_verifikasi,
                'diperiksa_oleh' => $pembayaran->diverifikasiOleh?->name,
                'bukti_url' => Storage::url($pembayaran->bukti_transfer),
                // Bukti boleh PDF atau gambar. Frontend butuh tahu yang mana biar
                // gambarnya bisa langsung ditampilkan, PDF cukup ditautkan.
                'bukti_gambar' => in_array(
                    strtolower(pathinfo($pembayaran->bukti_transfer, PATHINFO_EXTENSION)),
                    ['jpg', 'jpeg', 'png']
                ),
            ],
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'nama_pendaftar' => $pendaftaran->nama_pendaftar,
                'kategori' => $pendaftaran->kategoriSiswa->nama,
                'status' => $pendaftaran->status,
            ],
            'ringkasan' => [
                'total_tagihan' => $pendaftaran->totalTagihan(),
                'minimal_bayar' => $minimalBayar,
                'sudah_terverifikasi' => $sudahTerverifikasi,
                'setelah_disahkan' => $setelahDisahkan,
                'sisa_setelah_disahkan' => max(0, $pendaftaran->totalTagihan() - $setelahDisahkan),
                // Inilah kalimat kunci buat staf: mengesahkan transfer ini
                // membuat pendaftarannya otomatis diterima.
                'capai_minimal_setelah_disahkan' => $minimalBayar > 0 && $setelahDisahkan >= $minimalBayar,
                'sudah_capai_minimal_sebelumnya' => $minimalBayar > 0 && $sudahTerverifikasi >= $minimalBayar,
                // Dipakai frontend buat memutuskan menampilkan proyeksi atau tidak.
                'masih_menunggu' => $masihMenunggu,
                'sudah_disahkan' => $sudahDisahkan,
                // Pembatalan benar-benar menurunkan status, bukan cuma "bisa".
                'akan_menurunkan_status' => $sudahDisahkan
                    && $pendaftaran->status === 'diterima'
                    && $minimalBayar > 0
                    && $setelahDibatalkan < $minimalBayar,
                'transfer_menunggu_lain' => $menungguLain->count(),
                'nominal_menunggu_lain' => (int) $menungguLain->sum('nominal_transfer'),
            ],
            // Transfer lain untuk pendaftaran yang sama - penting buat wali yang
            // mencicil, supaya staf tidak menilai satu transfer secara terpisah.
            'transferLain' => $pendaftaran->pembayaran
                ->where('id', '!=', $pembayaran->id)
                ->sortByDesc('tanggal_transfer')
                ->values()
                ->map(fn (PembayaranPpdb $lain) => [
                    'nominal_transfer' => $lain->nominal_transfer,
                    'tanggal_transfer' => $lain->tanggal_transfer->locale('id')->translatedFormat('d F Y'),
                    'status' => $lain->status,
                ]),
        ]);
    }

    /**
     * Bukti transfer dinyatakan sah.
     *
     * Sesudah transfernya dicatat, status pendaftaran DIHITUNG ULANG - dan di
     * situlah pendaftaran bisa naik sendiri jadi 'diterima' kalau pembayarannya
     * menyentuh minimal bayar. Tidak ada tombol "Tetapkan Diterima" terpisah;
     * itu keputusan yang sudah disepakati (lihat PRD bagian 12 poin A).
     */
    public function sahkan(Request $request, PembayaranPpdb $pembayaran): RedirectResponse
    {
        abort_unless($pembayaran->status === 'menunggu_verifikasi', 403, 'Transfer ini sudah pernah diputuskan.');

        $pembayaran->update([
            'status' => 'terverifikasi',
            'diverifikasi_oleh' => $request->user()->id,
            // Catatan penolakan lama dibersihkan supaya wali tidak membaca
            // keluhan atas bukti yang sekarang justru diterima.
            'catatan_verifikasi' => null,
        ]);

        $pesan = 'Transfer '.$this->rupiah($pembayaran->nominal_transfer).' disahkan.';

        return to_route('staf-ppdb.verifikasi-pembayaran.index')
            ->with('success', $pesan.$this->kabarPerubahanStatus($pembayaran->pendaftaran));
    }

    /**
     * Bukti transfer ditolak - buram, nominal tidak cocok, atau bukan bukti yang
     * dimaksud. Status PENDAFTARAN tidak ikut ditolak; wali cuma perlu mengunggah
     * ulang buktinya (aturan lama yang gampang keliru, lihat PRD bagian 2).
     *
     * Boleh dipakai juga pada transfer yang TERLANJUR disahkan. Itu jalur
     * pembatalan verifikasi, dan justru jalur itu yang bikin otomatisasi status
     * jadi utuh: kalau pengesahan yang salah tidak bisa dicabut, status
     * 'diterima' yang lahir dari salah periksa akan menetap selamanya.
     */
    public function tolak(Request $request, PembayaranPpdb $pembayaran): RedirectResponse
    {
        abort_unless(
            in_array($pembayaran->status, ['menunggu_verifikasi', 'terverifikasi']),
            403,
            'Transfer ini sudah ditolak sebelumnya.'
        );

        $data = $request->validate(
            ['catatan_verifikasi' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'catatan_verifikasi.required' => 'Tulis dulu alasannya, supaya wali tahu apa yang harus dibetulkan.',
                'catatan_verifikasi.min' => 'Alasannya terlalu pendek. Sebutkan yang jelas, mis. "nominal tidak sesuai".',
            ]
        );

        $pembayaran->update([
            'status' => 'ditolak',
            'catatan_verifikasi' => $data['catatan_verifikasi'],
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        $pesan = 'Transfer '.$this->rupiah($pembayaran->nominal_transfer).' ditolak.';

        return to_route('staf-ppdb.verifikasi-pembayaran.index')
            ->with('success', $pesan.$this->kabarPerubahanStatus($pembayaran->pendaftaran));
    }

    /**
     * Hitung ulang status penerimaan, lalu laporkan ke staf kalau statusnya
     * memang berubah.
     *
     * Pemanggilan segarkanStatusPenerimaan() ada di SATU tempat ini supaya tidak
     * mungkin lupa dipanggil di salah satu arah - persis kewajiban yang dicatat
     * di PRD: titik verifikasi DAN pembatalan verifikasi, dua-duanya.
     *
     * Kabar perubahannya disampaikan karena statusnya berubah tanpa staf menekan
     * tombol apa pun untuk itu; kalau tidak diberitahu, staf tidak punya cara
     * tahu bahwa keputusannya barusan juga menerima seorang murid.
     */
    private function kabarPerubahanStatus(PendaftaranPpdb $pendaftaran): string
    {
        $sebelum = $pendaftaran->status;

        $pendaftaran->segarkanStatusPenerimaan();

        $sesudah = $pendaftaran->refresh()->status;

        if ($sesudah === $sebelum) {
            return '';
        }

        return " Status pendaftaran {$pendaftaran->nomor_pendaftaran} ikut berubah dari '{$sebelum}' jadi '{$sesudah}'.";
    }

    private function rupiah(int $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
