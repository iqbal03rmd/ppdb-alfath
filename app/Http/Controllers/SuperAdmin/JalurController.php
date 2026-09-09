<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KomponenBiaya;
use App\Models\TarifKategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 * simpanTarif() DIPARKIR. Nominal per komponen bersifat gelombang x jalur, jadi
 * tempatnya nanti di layar Ubah Gelombang - bareng kuota, minimal bayar, dan
 * berkas wajib yang sudah lebih dulu pindah ke sana. Sampai perombakan itu
 * dikerjakan, route-nya tidak dituju layar mana pun. Yang dipertahankan aturan
 * "kosong BUKAN nol" di dalamnya: kosong artinya pos itu tidak muncul di tagihan
 * jalur ini sama sekali, 0 artinya muncul dengan nilai Rp0 - jalur ini
 * dibebaskan darinya. Menulis ulang aturan itu dari nol gampang salah.
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
     * Nominal tiap komponen untuk jalur ini, pada satu gelombang.
     *
     * Kosong berarti BELUM DIATUR, dan barisnya dibuang - bukan disimpan sebagai
     * 0. Bedanya menentukan: 0 artinya jalur ini dibebaskan dari pos tersebut
     * (dan tetap ikut ke tagihan sebagai baris Rp0), sedangkan belum diatur
     * artinya pos itu tidak muncul di tagihannya sama sekali.
     */
    public function simpanTarif(Request $request, KategoriSiswa $jalur): RedirectResponse
    {
        $data = $request->validate([
            'gelombang_ppdb_id' => ['required', Rule::exists('gelombang_ppdb', 'id')],
            'tarif' => ['present', 'array'],
            'tarif.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'tarif.*.integer' => 'Nominal harus berupa angka, atau dikosongkan kalau belum diatur.',
            'tarif.*.min' => 'Nominal tidak boleh negatif.',
        ]);

        $idKomponenSah = KomponenBiaya::pluck('id')->all();

        DB::transaction(function () use ($data, $jalur, $idKomponenSah) {
            foreach ($data['tarif'] as $komponenId => $nominal) {
                if (! in_array((int) $komponenId, $idKomponenSah, true)) {
                    continue;
                }

                $kunci = [
                    'gelombang_ppdb_id' => $data['gelombang_ppdb_id'],
                    'komponen_biaya_id' => (int) $komponenId,
                    'kategori_siswa_id' => $jalur->id,
                ];

                if ($nominal === null) {
                    TarifKategori::where($kunci)->delete();

                    continue;
                }

                TarifKategori::updateOrCreate($kunci, ['nominal' => (int) $nominal]);
            }
        });

        return back()->with('success', "Nominal jalur {$jalur->nama} disimpan. Tagihan yang sudah terbit tidak ikut berubah.");
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
     * @return array<int, array<string, mixed>>
     */
    private function tarif(GelombangPpdb $gelombang, KategoriSiswa $jalur): array
    {
        $tersimpan = TarifKategori::where('gelombang_ppdb_id', $gelombang->id)
            ->where('kategori_siswa_id', $jalur->id)
            ->get()
            ->keyBy('komponen_biaya_id');

        // Pos yang dimatikan tidak ditawarkan lagi di sini - mengisi harga buat
        // sesuatu yang tidak akan ditagihkan cuma bikin bingung. Nominalnya yang
        // sudah tersimpan tetap ada di database, tinggal nyalakan lagi posnya.
        return KomponenBiaya::aktif()->terurut()->get()->map(fn (KomponenBiaya $k) => [
            'komponen_biaya_id' => $k->id,
            'nama' => $k->nama,
            'keterangan' => $k->keterangan,
            // String, bukan integer: kotak isian terkendali di React, dan ''
            // yang berarti "belum diatur" harus bisa dibedakan dari '0' yang
            // artinya dibebaskan.
            'nominal' => $tersimpan->has($k->id) ? (string) $tersimpan->get($k->id)->nominal : '',
        ])->all();
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
