<?php

namespace App\Http\Controllers\StafPpdb;

use App\Http\Controllers\Controller;
use App\Jobs\KirimNotifikasiWhatsApp;
use App\Models\BerkasPersyaratan;
use App\Models\NotifikasiWhatsapp;
use App\Models\PendaftaranPpdb;
use App\Models\WaliMurid;
use App\Rules\NomorWhatsApp;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VerifikasiPendaftaranController extends Controller
{
    /**
     * Antrian pendaftaran yang menunggu diverifikasi staf.
     *
     * Namanya "verifikasi pendaftaran", bukan "verifikasi berkas": yang diperiksa
     * formulir DAN berkas sekaligus, dan hasilnya satu keputusan untuk dua-duanya
     * (aturan "status satu paket" di PRD).
     *
     * Isinya HANYA status 'diajukan'. Itu satu-satunya status yang berarti
     * "wali sudah selesai, giliran staf" - draft belum dikirim, perlu_perbaikan
     * bolanya balik ke wali, dan sisanya sudah lewat tahap ini.
     */
    public function index(): Response
    {
        // gelombang.dokumenWajib ikut di-load: tiap baris memanggil
        // dokumenWajib(), yang membaca dokumen tambahan milik jalurnya. Tanpa
        // ini, antrian sepanjang N menembak N query tambahan.
        $antrian = PendaftaranPpdb::with(['gelombang.dokumenWajib', 'kategoriSiswa', 'dokumen'])
            ->where('status', 'diajukan')
            // Yang paling lama menunggu didahulukan - antrian, bukan tumpukan.
            ->oldest('updated_at')
            ->get()
            // Status diajukan seharusnya hanya lahir setelah validasi berkas di
            // sisi wali. Tetap saring di sini sebagai pagar untuk data impor,
            // seed lama, atau perubahan manual yang melanggar aturan tersebut.
            ->filter(fn (PendaftaranPpdb $p) => $p->berkasLengkap())
            ->values()
            ->map(fn (PendaftaranPpdb $p) => [
                'id' => $p->id,
                'nomor_pendaftaran' => $p->nomor_pendaftaran,
                'nama_pendaftar' => $p->nama_pendaftar,
                'kategori' => $p->kategoriSiswa->nama,
                'gelombang' => $p->gelombang->nama,
                'berkas_terunggah' => $p->dokumen->count(),
                'berkas_wajib' => count($p->dokumenWajib()),
                'menunggu_sejak' => $p->updated_at->locale('id')->translatedFormat('d F Y'),
            ]);

        return Inertia::render('staf-ppdb/verifikasi-pendaftaran', [
            'antrian' => $antrian,
        ]);
    }

    /**
     * Halaman periksa satu pendaftaran: biodata, data wali, dan berkas yang
     * diunggah - semua yang dibutuhkan staf buat memutuskan, dalam satu layar.
     *
     * Belum ada tombol setujui/minta perbaikan; itu langkah berikutnya.
     *
     * Sengaja TIDAK memakai mapDetail() milik PendaftaranController sisi wali:
     * yang dibutuhkan staf beda (nggak perlu progres pembayaran, perlu identitas
     * akun pendaftarnya), dan menyatukan keduanya sekarang cuma bikin satu method
     * yang melayani dua kepentingan berbeda.
     */
    public function show(PendaftaranPpdb $pendaftaran): Response
    {
        $pendaftaran->load(['gelombang.dokumenWajib', 'kategoriSiswa', 'waliMurid', 'dokumen', 'user']);

        $this->pastikanSiapDiperiksa($pendaftaran);

        return Inertia::render('staf-ppdb/verifikasi-pendaftaran-show', [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'nomor_pendaftaran' => $pendaftaran->nomor_pendaftaran,
                'status' => $pendaftaran->status,
                'kategori' => $pendaftaran->kategoriSiswa->nama,
                'gelombang' => $pendaftaran->gelombang->nama,
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
                'akun_pendaftar' => $pendaftaran->user->name.' ('.$pendaftaran->user->email.')',
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
                    'nama_file' => $dokumen ? basename($dokumen->berkas) : null,
                ];
            }),
        ]);
    }

    /**
     * Formulir + berkas dinyatakan benar. Status naik ke 'diverifikasi', dan di
     * titik itulah pembayaran terbuka buat wali.
     *
     * Guard status ada di sini, bukan cuma di tampilan: tombolnya memang
     * disembunyikan kalau statusnya bukan 'diajukan', tapi menyembunyikan tombol
     * bukan pengamanan - orang masih bisa menembak rutenya langsung. Guard ini
     * juga menangkap kasus dua staf memeriksa berkas yang sama bersamaan; yang
     * datang belakangan ditolak, bukan menimpa keputusan yang pertama.
     */
    public function setujui(Request $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $pendaftaran): void {
                // Guard dan penulisan berada di bawah kunci yang sama. Dua staf
                // yang menekan Setujui bersamaan tidak boleh sama-sama lolos dan
                // menerbitkan efek samping dua kali.
                $terkunci = PendaftaranPpdb::query()
                    ->with(['user', 'gelombang.dokumenWajib', 'dokumen', 'tagihanItem'])
                    ->lockForUpdate()
                    ->findOrFail($pendaftaran->id);

                $this->pastikanSiapDiperiksa($terkunci);

                $terkunci->update([
                    'status' => 'diverifikasi',
                    'diverifikasi_oleh' => $request->user()->id,
                    // Titik nol buat mengukur berapa lama wali menggantung sebelum
                    // transfer pertama - lihat komentarnya di migration. Cuma di sini
                    // yang mengisinya; mintaPerbaikan() dan tutup() sengaja tidak.
                    'diverifikasi_pada' => now(),
                    // Catatan perbaikan lama dibersihkan. Kalau ditinggalkan, wali masih
                    // membaca keluhan yang justru sudah dia betulkan.
                    'catatan_verifikasi' => null,
                ]);

                // Tagihan dan notifikasi adalah satu kejadian bisnis. Jangan
                // memberi tahu wali sebelum snapshot tagihannya sungguh ada.
                $terkunci->terbitkanTagihan();
                $terkunci->load(['tagihanItem', 'user', 'gelombang']);

                if (! $terkunci->tagihanSudahTerbit()) {
                    throw new DomainException(
                        'Pendaftaran belum dapat disetujui karena tarif jalur ini belum dikonfigurasi. Minta Super Admin melengkapi nominal Gelombang PPDB terlebih dahulu.'
                    );
                }

                $this->antrekanNotifikasiTagihanDibuka($terkunci);
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('staf-ppdb.verifikasi-pendaftaran.index')
            ->with('success', "Pendaftaran {$pendaftaran->nomor_pendaftaran} diverifikasi, tagihan diterbitkan, dan notifikasi WhatsApp diproses sesuai pengaturan wali.");
    }

    /**
     * Catat satu notifikasi tagihan. Detail ini sengaja berada dekat dengan
     * satu-satunya pemicunya agar alur TA mudah ditelusuri; kalau jenis pesan
     * bertambah barulah layak dipindah menjadi service orchestration.
     */
    private function antrekanNotifikasiTagihanDibuka(PendaftaranPpdb $pendaftaran): void
    {
        $nomorTujuan = NomorWhatsApp::normalisasi($pendaftaran->user->telepon);
        [$status, $keterangan] = $this->statusAwalNotifikasi($pendaftaran, $nomorTujuan);

        $notifikasi = NotifikasiWhatsapp::firstOrCreate(
            ['idempotency_key' => 'tagihan_dibuka:'.$pendaftaran->id],
            [
                'user_id' => $pendaftaran->user_id,
                'pendaftaran_ppdb_id' => $pendaftaran->id,
                'jenis' => 'tagihan_dibuka',
                'nomor_tujuan' => $nomorTujuan,
                'pesan' => $this->pesanTagihanDibuka($pendaftaran),
                'status' => $status,
                'keterangan_status' => $keterangan,
            ]
        );

        if ($notifikasi->wasRecentlyCreated && $status === 'menunggu') {
            KirimNotifikasiWhatsApp::dispatch($notifikasi->id)->afterCommit();
        }
    }

    /** @return array{string, string|null} */
    private function statusAwalNotifikasi(PendaftaranPpdb $pendaftaran, ?string $nomorTujuan): array
    {
        if (! config('services.whatsapp.enabled')) {
            return ['dilewati', 'Pengiriman WhatsApp sedang dinonaktifkan.'];
        }

        if (! $pendaftaran->user->notifikasi_whatsapp_aktif) {
            return ['dilewati', 'Wali tidak mengaktifkan notifikasi WhatsApp.'];
        }

        if ($nomorTujuan === null) {
            return ['dilewati', 'Nomor WhatsApp wali kosong atau tidak valid.'];
        }

        return ['menunggu', null];
    }

    private function pesanTagihanDibuka(PendaftaranPpdb $pendaftaran): string
    {
        $formatRupiah = static fn (int $nominal): string => 'Rp'.number_format($nominal, 0, ',', '.');
        $batasPembayaran = $pendaftaran->batasMinimalBayar()?->locale('id')->translatedFormat('d F Y') ?? 'Hubungi Staf PPDB';
        $url = route('wali-murid.pembayaran.show', $pendaftaran, absolute: true);

        return implode("\n", [
            "Assalamu'alaikum, Bapak/Ibu {$pendaftaran->user->name}.",
            '',
            "Berkas pendaftaran {$pendaftaran->nama_pendaftar} ({$pendaftaran->nomor_pendaftaran}) telah disetujui. Tagihan PPDB sekarang sudah tersedia.",
            '',
            'Total tagihan: '.$formatRupiah($pendaftaran->totalTagihan()),
            'Minimum pembayaran: '.$formatRupiah((int) $pendaftaran->minimalBayar()),
            'Batas pembayaran minimum: '.$batasPembayaran,
            '',
            'Lihat rincian tagihan dan unggah bukti pembayaran:',
            $url,
            '',
            'Pesan otomatis PPDB SDIT Al-Fath. Notifikasi dapat dinonaktifkan melalui menu Pengaturan akun.',
        ]);
    }

    /**
     * Ada yang perlu dibetulkan. Status turun ke 'perlu_perbaikan' dan bolanya
     * balik ke wali - dia bisa mengedit formulir DAN berkas, lalu mengirim ulang
     * lewat tombol "Kirim Perbaikan" miliknya.
     *
     * Catatan WAJIB diisi: itu satu-satunya keterangan yang sampai ke wali soal
     * apa yang salah. Tanpa itu wali cuma tahu ada yang keliru, tanpa tahu apa.
     */
    public function mintaPerbaikan(Request $request, PendaftaranPpdb $pendaftaran): RedirectResponse
    {
        $pendaftaran->load(['gelombang.dokumenWajib', 'dokumen']);

        $this->pastikanSiapDiperiksa($pendaftaran);

        // Validasi ditulis langsung di sini, tidak lewat Form Request terpisah:
        // cuma satu kolom dan tidak ada aturan hak akses tambahan, jadi berkas
        // sendiri malah bikin aturannya jauh dari tempat pemakaiannya.
        $data = $request->validate(
            ['catatan_verifikasi' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'catatan_verifikasi.required' => 'Tulis dulu bagian mana yang perlu diperbaiki.',
                'catatan_verifikasi.min' => 'Catatannya terlalu pendek. Sebutkan yang jelas supaya wali tahu apa yang harus dibetulkan.',
            ]
        );

        $pendaftaran->update([
            'status' => 'perlu_perbaikan',
            'catatan_verifikasi' => $data['catatan_verifikasi'],
            // Ikut dicatat walau hasilnya bukan "diverifikasi": meminta perbaikan
            // sama saja tindakan memeriksa, dan wali berhak tahu siapa yang
            // memintanya. Kolomnya menyimpan pemeriksa TERAKHIR, bukan riwayat.
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        return to_route('staf-ppdb.verifikasi-pendaftaran.index')
            ->with('success', "Permintaan perbaikan untuk {$pendaftaran->nomor_pendaftaran} sudah dikirim ke wali.");
    }

    /**
     * Satu definisi untuk seluruh pintu verifikasi: sudah diserahkan wali dan
     * seluruh dokumen yang diwajibkan jalurnya tersedia. UI bukan pengaman;
     * aturan ini tetap diperiksa saat halaman atau endpoint ditembak langsung.
     */
    private function pastikanSiapDiperiksa(PendaftaranPpdb $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->status === 'diajukan' && $pendaftaran->berkasLengkap(),
            403,
            'Pendaftaran belum diajukan dengan berkas wajib yang lengkap.'
        );
    }
}
