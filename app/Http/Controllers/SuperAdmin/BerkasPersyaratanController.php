<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BerkasPersyaratan;
use App\Models\DokumenPpdb;
use App\Models\DokumenWajibKategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Berkas Persyaratan - daftar master jenis berkas yang bisa diminta pendaftar.
 *
 * Yang diatur di sini cuma DAFTARNYA. Jalur mana yang mewajibkan berkas apa
 * tetap diatur di Jalur Pendaftaran; di sini menambah pilihannya.
 *
 * Kodenya dibuat sekali dari namanya lalu BEKU. Nama boleh diganti kapan saja -
 * yang dibaca wali memang itu - tapi kodenya tidak ikut, karena kode itu yang
 * tersimpan di tiap baris dokumen_ppdb. Ikut berubah berarti seluruh berkas yang
 * sudah diunggah kehilangan jenisnya sekaligus.
 */
class BerkasPersyaratanController extends Controller
{
    public function index(): Response
    {
        // Dua agregat sekali jalan, bukan dua query per baris. Yang dibutuhkan
        // layar cuma "sudah dipakai atau belum", jadi cukup daftar kodenya.
        $dimintaJalur = DokumenWajibKategori::distinct()->pluck('jenis_dokumen');
        $pernahDiunggah = DokumenPpdb::distinct()->pluck('jenis_dokumen');

        return Inertia::render('super-admin/berkas-persyaratan', [
            'berkas' => BerkasPersyaratan::terurut()->get()->map(fn (BerkasPersyaratan $b) => [
                'id' => $b->id,
                'kode' => $b->kode,
                'nama' => $b->nama,
                'keterangan' => $b->keterangan,
                'urutan' => $b->urutan,
                'status_aktif' => (bool) $b->status_aktif,
                // Satu penanda, bukan angka: yang perlu diketahui Admin cuma
                // sudah tersangkut ke sesuatu atau belum. Dihitung di sini dan
                // bukan disusun ulang di TSX, karena ini aturan yang sama
                // persis dengan yang ditegakkan destroy().
                'sudah_dipakai' => $dimintaJalur->contains($b->kode) || $pernahDiunggah->contains($b->kode),
            ])->all(),

            'urutanBerikutnya' => (int) BerkasPersyaratan::max('urutan') + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        BerkasPersyaratan::create([
            ...$data,
            'kode' => $this->kodeUnik($data['nama']),
            'status_aktif' => $request->boolean('status_aktif', true),
        ]);

        return to_route('super-admin.berkas-persyaratan.index')
            ->with('success', "Berkas {$data['nama']} ditambahkan. Pilih jalur yang mewajibkannya di Jalur Pendaftaran.");
    }

    public function update(Request $request, BerkasPersyaratan $berkasPersyaratan): RedirectResponse
    {
        // Kodenya sengaja TIDAK ikut diperbarui - lihat komentar kelas.
        $berkasPersyaratan->update($this->validasi($request, $berkasPersyaratan));

        return to_route('super-admin.berkas-persyaratan.index')
            ->with('success', "Berkas {$berkasPersyaratan->nama} diperbarui.");
    }

    /**
     * Menghapus jenis berkas.
     *
     * Cuma boleh selama BELUM DIPAKAI SAMA SEKALI - belum diminta jalur mana
     * pun, dan belum pernah diunggah siapa pun. Praktisnya cuma baris salah
     * ketik yang lolos ke sini.
     *
     * Dua-duanya menahan, dan sebabnya beda. Yang sudah diunggah: menghapus
     * jenisnya bikin berkas itu kehilangan namanya di layar staf maupun wali,
     * tanpa cara mengembalikannya. Yang diminta sebuah jalur: menghapusnya ikut
     * mencabut persyaratan jalur itu diam-diam - perubahan aturan pendaftaran
     * yang terjadi sebagai efek samping tombol Hapus, bukan sebagai keputusan.
     *
     * Untuk berkas yang sudah tidak diminta lagi, yang dipakai menonaktifkan.
     */
    public function destroy(BerkasPersyaratan $berkasPersyaratan): RedirectResponse
    {
        $terunggah = DokumenPpdb::where('jenis_dokumen', $berkasPersyaratan->kode)->count();

        if ($terunggah > 0) {
            return back()->with(
                'error',
                "{$berkasPersyaratan->nama} sudah diunggah {$terunggah} pendaftar, jadi tidak bisa dihapus. "
                    .'Nonaktifkan saja kalau berkas ini sudah tidak diminta lagi - berkas yang telanjur masuk tetap bisa dibuka.'
            );
        }

        $jalur = DokumenWajibKategori::with('kategoriSiswa')
            ->where('jenis_dokumen', $berkasPersyaratan->kode)
            ->get()
            ->pluck('kategoriSiswa.nama')
            ->filter();

        if ($jalur->isNotEmpty()) {
            return back()->with(
                'error',
                "{$berkasPersyaratan->nama} masih diminta jalur {$jalur->join(', ', ' dan ')}, jadi tidak bisa dihapus. "
                    .'Lepaskan dulu dari jalur itu di Jalur Pendaftaran, atau nonaktifkan saja berkasnya.'
            );
        }

        $nama = $berkasPersyaratan->nama;
        $berkasPersyaratan->delete();

        return to_route('super-admin.berkas-persyaratan.index')->with('success', "Berkas {$nama} dihapus.");
    }

    /**
     * Kode dari nama, dijamin belum terpakai.
     *
     * Dibuat sekali saat menambah, lalu beku selamanya.
     */
    private function kodeUnik(string $nama): string
    {
        $dasar = Str::slug($nama, '_') ?: 'berkas';
        $kode = $dasar;
        $n = 2;

        while (BerkasPersyaratan::where('kode', $kode)->exists()) {
            $kode = "{$dasar}_{$n}";
            $n++;
        }

        return $kode;
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?BerkasPersyaratan $berkas = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('berkas_persyaratan', 'nama')->ignore($berkas?->id)],
            // Ikut tampil di bawah nama berkas pada halaman Unggah Berkas, jadi
            // ini kalimat yang dibaca wali - tempat menerangkan berkas seperti
            // apa yang diterima.
            'keterangan' => ['nullable', 'string', 'max:255'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'status_aktif' => ['boolean'],
        ], [
            'nama.required' => 'Nama berkas wajib diisi, misalnya Kartu Keluarga (KK).',
            'nama.unique' => 'Berkas dengan nama ini sudah ada.',
            'urutan.required' => 'Urutan wajib diisi - itu yang menentukan letaknya di checklist Unggah Berkas.',
        ]);

        // status_aktif SENGAJA ikut terbawa (beda dari tahun ajaran): ini saklar
        // sungguhan dua arah, dan mematikannya justru gunanya.
        return $data;
    }
}
