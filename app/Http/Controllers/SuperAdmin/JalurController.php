<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\KategoriSiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jalur pendaftaran - dulu bernama "Kategori Siswa".
 *
 * TIDAK ADA nama jalur yang dicocokkan kode di mana pun. Sebelum 8 September
 * 2026 ada tiga pencocokan teks, dan salah satunya memang sudah rusak diam-diam
 * (mencari 'Anak Guru/Tenaga Kependidikan' padahal jalurnya bernama 'Anak
 * Guru'). Ketiganya sekarang data - berkas wajib di dokumen_wajib_kategori,
 * pertanyaan khusus di kolom pertanyaan_khusus, nominal di tarif_kategori - jadi
 * nama bebas diubah admin tanpa merusak apa pun.
 *
 * BERKAS WAJIB TIDAK DIATUR DI SINI (10 September 2026). Syaratnya milik
 * gelombang x jalur dan diatur di layar Ubah Gelombang. Waktu masih melekat
 * pada jalur saja, mengubahnya berlaku SURUT: menambah satu syarat bikin anak
 * yang sudah diterima tercatat kurang berkas, mencabut satu syarat bikin berkas
 * yang telanjur diunggah hilang dari layar staf.
 *
 * Tambah dan ubah jalur TIDAK punya halaman sendiri (10 September 2026) -
 * formulirnya modal di atas daftarnya, isinya sifat jalur saja: nama, urutan,
 * keterangan, pertanyaan khusus, status.
 *
 * NOMINAL JUGA TIDAK DIATUR DI SINI (11 September 2026). Alasannya sama persis
 * dengan berkas wajib: tarif_kategori berkunci gelombang x jalur x komponen,
 * jadi tempatnya di layar yang menyebut gelombang. Yang tinggal di sini cuma
 * sifat jalurnya sendiri - yang tidak berubah dari gelombang ke gelombang.
 */
class JalurController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('super-admin/jalur', [
            'jalur' => KategoriSiswa::terurut()
                ->get()
                ->map(fn (KategoriSiswa $j) => [
                    'id' => $j->id,
                    'nama' => $j->nama,
                    'urutan' => $j->urutan,
                    'deskripsi' => $j->deskripsi,
                    'pertanyaan_khusus' => $j->pertanyaan_khusus,
                    'status_aktif' => (bool) $j->status_aktif,
                    'jumlah_pendaftaran' => $j->jumlahPendaftaran(),
                    // Jalur yang sudah dipakai tidak bisa dihapus - foreign key
                    // pendaftaran_ppdb.kategori_siswa_id sengaja tidak cascade.
                    // Dihitung di sini supaya tombolnya tidak ditawarkan
                    // sia-sia; server tetap menolaknya juga.
                    'bisa_dihapus' => $j->jumlahPendaftaran() === 0,
                ])
                ->all(),

            // Formulirnya modal di atas daftar ini, jadi tidak ada permintaan
            // kedua ke server yang bisa mengangkut angka ini.
            'urutanBerikutnya' => (int) KategoriSiswa::max('urutan') + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        KategoriSiswa::create([...$data, 'status_aktif' => $request->boolean('status_aktif', true)]);

        return to_route('super-admin.jalur.index')
            ->with('success', "Jalur {$data['nama']} ditambahkan. Atur berkas wajib dan nominalnya per gelombang.");
    }

    public function update(Request $request, KategoriSiswa $jalur): RedirectResponse
    {
        $data = $this->validasi($request, $jalur);

        $jalur->update($data);

        return to_route('super-admin.jalur.index')->with('success', "Jalur {$jalur->nama} diperbarui.");
    }

    /**
     * Menghapus jalur.
     *
     * Ditolak kalau sudah ada pendaftar yang memakainya - dan penolakannya
     * berlapis dua. Foreign key pendaftaran_ppdb.kategori_siswa_id sengaja TIDAK
     * cascade, jadi database sendiri menolak; pemeriksaan di bawah cuma supaya
     * pesannya bisa dibaca orang, bukan error SQL mentah.
     *
     * Yang ikut terhapus kalau jalurnya memang belum dipakai: dokumen wajibnya,
     * kebijakan kuota/minimal bayarnya, dan barisan tarifnya (semuanya cascade).
     * Ketiganya cuma pengaturan - tidak ada riwayat maupun uang di dalamnya.
     */
    public function destroy(KategoriSiswa $jalur): RedirectResponse
    {
        if ($jalur->pendaftaran()->exists()) {
            return back()->with(
                'error',
                "Jalur {$jalur->nama} sudah dipakai {$jalur->jumlahPendaftaran()} pendaftaran, jadi tidak bisa dihapus. "
                    .'Riwayat mereka menggantung pada jalur ini.'
            );
        }

        $nama = $jalur->nama;
        $jalur->delete();

        return to_route('super-admin.jalur.index')->with('success', "Jalur {$nama} dihapus beserta pengaturannya.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?KategoriSiswa $jalur = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:50', Rule::unique('kategori_siswa', 'nama')->ignore($jalur?->id)],
            // Kalimat yang dibaca wali di bawah pilihan jalur waktu mengisi
            // formulir - satu-satunya keterangan yang menolong dia memilih jalur
            // yang benar, jadi diwajibkan.
            'deskripsi' => ['required', 'string', 'max:500'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            // Kalimat bebas, bukan pilihan tetap. Kosong = jalur ini tidak
            // menanyakan apa-apa di luar formulir biasa.
            'pertanyaan_khusus' => ['nullable', 'string', 'max:255'],
            // Saklar sungguhan dua arah - mematikannya justru gunanya, karena
            // jalur yang sudah dipakai tidak bisa dihapus.
            'status_aktif' => ['boolean'],
        ], [
            'nama.required' => 'Nama jalur wajib diisi.',
            'nama.unique' => 'Jalur dengan nama ini sudah ada.',
            'deskripsi.required' => 'Tulis keterangannya - kalimat ini yang dibaca wali saat memilih jalur.',
            'urutan.required' => 'Urutan wajib diisi - itu yang menentukan letaknya di daftar pilihan jalur.',
        ]);
    }
}
