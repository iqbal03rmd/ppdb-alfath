<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\KomponenBiaya;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Komponen biaya - daftar pos biaya PPDB, berlaku untuk semua gelombang.
 *
 * Di sini cuma NAMA posnya. Nominalnya tidak ada di halaman ini sama sekali:
 * angka berbeda tiap gelombang dan tiap jalur, jadi tempatnya di Jalur
 * Pendaftaran (dengan pemilih gelombang). Kalau suatu saat ada yang menambahkan
 * kolom nominal di sini, dia akan jadi sumber kedua untuk angka yang sama.
 */
class KomponenBiayaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('super-admin/komponen-biaya', [
            'komponen' => KomponenBiaya::terurut()->get()->map(fn (KomponenBiaya $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'keterangan' => $k->keterangan,
                'urutan' => $k->urutan,
                'status_aktif' => (bool) $k->status_aktif,
                // Berapa baris tarif yang menggantung padanya - itu yang bikin
                // komponen ini tidak bisa dihapus lagi.
                'dipakai' => $k->tarif()->count(),
            ])->all(),

            // Formulirnya modal di atas daftar ini, jadi tidak ada permintaan
            // kedua ke server yang bisa mengangkut angka ini. Ditaruh di
            // belakang antrean supaya komponen baru tidak menyelak ke tengah
            // rincian tagihan tanpa diminta.
            'urutanBerikutnya' => (int) KomponenBiaya::max('urutan') + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        KomponenBiaya::create([...$data, 'status_aktif' => $request->boolean('status_aktif', true)]);

        return to_route('super-admin.komponen-biaya.index')
            ->with('success', "Komponen {$data['nama']} ditambahkan. Nominalnya diatur di Jalur Pendaftaran, per gelombang.");
    }

    public function update(Request $request, KomponenBiaya $komponenBiaya): RedirectResponse
    {
        $komponenBiaya->update($this->validasi($request, $komponenBiaya));

        return to_route('super-admin.komponen-biaya.index')
            ->with('success', "Komponen {$komponenBiaya->nama} diperbarui.");
    }

    /**
     * Menghapus komponen biaya.
     *
     * Aman dihapus SELAMA belum punya tarif: komponen tanpa tarif belum pernah
     * ikut ke tagihan siapa pun. Begitu ada tarifnya, menghapus komponen ikut
     * menghapus baris tarifnya (cascade) - dan itu mengubah total tagihan
     * pendaftar yang tagihannya belum terbit, diam-diam.
     *
     * Tagihan yang SUDAH terbit tidak terpengaruh apa pun: tagihan_item menyimpan
     * nama komponen sebagai teks, bukan foreign key. Itu memang gunanya snapshot.
     */
    public function destroy(KomponenBiaya $komponenBiaya): RedirectResponse
    {
        if ($komponenBiaya->tarif()->exists()) {
            return back()->with(
                'error',
                "{$komponenBiaya->nama} sudah punya nominal di salah satu gelombang, jadi tidak bisa dihapus. "
                    .'Kosongkan dulu nominalnya di Jalur Pendaftaran kalau pos ini memang sudah tidak dipakai.'
            );
        }

        $nama = $komponenBiaya->nama;
        $komponenBiaya->delete();

        return to_route('super-admin.komponen-biaya.index')->with('success', "Komponen {$nama} dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?KomponenBiaya $komponenBiaya = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('komponen_biaya', 'nama')->ignore($komponenBiaya?->id)],
            // Ikut disalin ke tagihan_item saat tagihan terbit, jadi ini kalimat
            // yang dibaca wali di rincian tagihannya - bukan catatan internal.
            'keterangan' => ['nullable', 'string', 'max:255'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            // Beda dari status_aktif tahun ajaran yang cuma boleh dinyalakan:
            // yang ini saklar sungguhan, jadi boleh ikut mass-assign apa adanya.
            // Mematikan semuanya sah - artinya sekolah tidak menagih apa pun.
            'status_aktif' => ['boolean'],
        ], [
            'nama.required' => 'Nama komponen wajib diisi, misalnya Pembangunan.',
            'nama.unique' => 'Komponen dengan nama ini sudah ada.',
            'urutan.required' => 'Urutan wajib diisi - itu yang menentukan letaknya di rincian tagihan.',
        ]);
    }
}
