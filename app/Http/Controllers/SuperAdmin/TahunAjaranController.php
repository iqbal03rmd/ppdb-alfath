<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data Master - tahun ajaran, dan gelombang di dalamnya.
 *
 * Sama seperti Kelola Pengguna: TIDAK ADA AKSI HAPUS. tahun_ajaran cascade ke
 * gelombang_ppdb, yang cascade lagi ke pendaftaran_ppdb, pembayaran_ppdb, dan
 * tagihan_item. Menghapus satu tahun ajaran berarti menghapus satu angkatan
 * penuh beserta seluruh catatan uangnya. Yang tersedia menutup gelombang dan
 * memindahkan status aktif - keduanya bisa dibatalkan, penghapusan tidak.
 *
 * Satu hal yang WAJIB dipahami sebelum mengubah berkas ini: mengubah tarif atau
 * minimal bayar di sini TIDAK mengubah tagihan yang sudah terbit. Tagihan
 * di-snapshot ke tagihan_item dan minimal bayarnya dibekukan ke
 * pendaftaran_ppdb.minimal_bayar saat wali pertama membuka halaman Pembayaran
 * (PendaftaranPpdb::terbitkanTagihan()). Itu memang disengaja - kalau dihitung
 * ulang, menaikkan tarif hari ini akan mengubah kewajiban orang yang sudah lunas
 * kemarin. Layarnya menyebutkan ini supaya admin tidak menyangka sebaliknya.
 */
class TahunAjaranController extends Controller
{
    /**
     * Beranda Data Master: daftar tahun ajaran beserta isinya.
     */
    public function index(): Response
    {
        $tahunAjaran = TahunAjaran::query()
            ->withCount('gelombang')
            ->orderByDesc('tahun_mulai')
            ->orderByDesc('nama')
            ->get()
            ->map(fn (TahunAjaran $t) => [
                'id' => $t->id,
                'nama' => $t->nama,
                'tahun_mulai' => $t->tahun_mulai,
                'status_aktif' => (bool) $t->status_aktif,
                'batas_pelunasan' => $this->tanggal($t->batas_pelunasan),
                // Tanggal yang sama dalam dua bentuk, dan dua-duanya dipakai:
                // yang di atas untuk dibaca di tabel ("1 Juli 2027"), yang ini
                // untuk mengisi <input type="date"> di modal, yang cuma mau
                // menerima Y-m-d. Modalnya mengubah baris tabel yang sudah ada
                // di layar, jadi tidak ada permintaan kedua ke server yang bisa
                // mengangkut bentuk mentahnya.
                'batas_pelunasan_iso' => $t->batas_pelunasan?->format('Y-m-d'),
                'jumlah_gelombang' => $t->gelombang_count,
                'jumlah_pendaftaran' => $t->jumlahPendaftaran(),
            ])
            ->all();

        return Inertia::render('super-admin/tahun-ajaran', [
            'tahunAjaran' => $tahunAjaran,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        // Selalu lahir non-aktif, lalu diaktifkan lewat terapkanStatus() kalau
        // memang diminta. Kalau status_aktif ikut mass-assign di sini, ada
        // sekejap dua tahun ajaran aktif sekaligus - dan laporan yang kebetulan
        // dibaca saat itu mengambil yang mana saja.
        $tahunAjaran = TahunAjaran::create([...$data, 'status_aktif' => false]);

        $this->terapkanStatus($request, $tahunAjaran);

        $status = $tahunAjaran->status_aktif ? 'aktif' : 'non-aktif';

        return to_route('super-admin.tahun-ajaran.index')
            ->with('success', "Tahun ajaran {$tahunAjaran->nama} dibuat sebagai {$status}. Lanjutkan ke menu Gelombang PPDB.");
    }

    public function update(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $aktifSebelumnya = $tahunAjaran->status_aktif;

        $tahunAjaran->update($this->validasi($request, $tahunAjaran));

        $this->terapkanStatus($request, $tahunAjaran);

        return to_route('super-admin.tahun-ajaran.index')->with('success', ! $aktifSebelumnya && $tahunAjaran->status_aktif
            ? "Tahun ajaran {$tahunAjaran->nama} diperbarui dan sekarang aktif."
            : "Tahun ajaran {$tahunAjaran->nama} diperbarui.");
    }

    /**
     * Terapkan pilihan "aktifkan tahun ajaran ini" dari formulir.
     *
     * Cuma bisa MENGAKTIFKAN, tidak pernah menonaktifkan, dan itu bukan
     * kelalaian. Menonaktifkan tanpa ada penggantinya berarti nol tahun ajaran
     * aktif - Beranda Kepala Sekolah kosong dan penyaring Semua Pendaftaran
     * kehilangan posisi awalnya, karena seluruh sistem membacanya lewat
     * `where('status_aktif', true)->first()`. Layarnya sudah mengunci pilihan
     * itu, tapi penjaganya di sini: permintaan yang dikarang sendiri pun tidak
     * bisa mengosongkannya.
     *
     * Memindahkannya tetap bisa, dan cuma satu cara: mengaktifkan tahun ajaran
     * lain. aktifkan() yang mematikan sisanya, dalam satu transaksi.
     */
    private function terapkanStatus(Request $request, TahunAjaran $tahunAjaran): void
    {
        if ($request->boolean('status_aktif') && ! $tahunAjaran->status_aktif) {
            $tahunAjaran->aktifkan();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?TahunAjaran $tahunAjaran = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:50', Rule::unique('tahun_ajaran', 'nama')->ignore($tahunAjaran?->id)],
            'tahun_mulai' => ['required', 'integer', 'min:2000', 'max:2100'],
            // Tenggat PELUNASAN cicilan - WAJIB. Bukan karena lewatnya
            // berakibat sesuatu di sistem (tidak; yang bisa menolak cuma batas
            // minimal bayar milik gelombang), tapi karena tanggal ini yang
            // dicetak ke halaman Pembayaran dan Beranda wali sebagai pengingat
            // kapan cicilan harus lunas. Waktu masih boleh kosong, kalimat
            // pengingatnya terbit tanpa menyebut tanggal apa pun.
            'batas_pelunasan' => ['required', 'date'],
            'status_aktif' => ['boolean'],
        ], [
            'nama.required' => 'Nama tahun ajaran wajib diisi, misalnya 2026/2027.',
            'nama.unique' => 'Tahun ajaran dengan nama ini sudah ada.',
            'tahun_mulai.required' => 'Tahun mulai wajib diisi.',
            'batas_pelunasan.required' => 'Batas pelunasan wajib diisi - tanggal ini yang dibaca wali.',
        ]);

        // Dikeluarkan dari nilai yang di-mass-assign dengan sengaja: status
        // aktif cuma boleh berpindah lewat TahunAjaran::aktifkan(), yang
        // mematikan yang lain dalam transaksi yang sama. Dibiarkan lewat
        // update(), satu centang bisa bikin dua tahun ajaran aktif sekaligus.
        // Yang membacanya terapkanStatus(), langsung dari request.
        unset($data['status_aktif']);

        return $data;
    }

    private function tanggal(?\Illuminate\Support\Carbon $tanggal): ?string
    {
        return $tanggal?->locale('id')->translatedFormat('d F Y');
    }
}
