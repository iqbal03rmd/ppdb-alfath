<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BerkasPersyaratan;
use App\Models\DokumenWajibKategori;
use App\Models\GelombangPpdb;
use App\Models\KategoriSiswa;
use App\Models\KebijakanKategori;
use App\Models\KomponenBiaya;
use App\Models\TahunAjaran;
use App\Models\TarifKategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gelombang PPDB - jendela pendaftaran beserta seluruh angka yang berlaku
 * selama jendela itu terbuka.
 *
 * Tiga hal yang diatur di sini, dan ketiganya memang berubah tiap gelombang:
 *
 *   tanggal          buka, tutup, dan jatuh tempo minimal bayar
 *   kuota per jalur  daya tampung, biasanya menyusut di gelombang berikutnya
 *   minimal bayar    setoran awal, bisa dinaikkan di gelombang berikutnya
 *
 * NOMINAL KOMPONEN tidak diatur di sini kecuali sekali saat gelombang dibuat -
 * "nominal dasar" yang mengisi seluruh jalur sekaligus sebagai titik awal.
 * Sesudah itu angkanya diubah per jalur di menu Jalur Pendaftaran, supaya cuma
 * ada satu tempat yang memegangnya.
 *
 * TIDAK ADA aksi hapus: gelombang cascade ke pendaftaran_ppdb, yang cascade lagi
 * ke pembayaran_ppdb. Yang tersedia menutup pendaftarannya.
 */
class GelombangController extends Controller
{
    public function index(): Response
    {
        $gelombang = GelombangPpdb::with('tahunAjaran')
            ->withCount('pendaftaran')
            ->get()
            ->sortByDesc(fn (GelombangPpdb $g) => $g->tahunAjaran->nama.$g->tanggal_mulai?->format('Y-m-d'))
            ->values()
            ->map(fn (GelombangPpdb $g) => [
                'id' => $g->id,
                'nama' => $g->nama,
                'tahun_ajaran' => $g->tahunAjaran->nama,
                'tanggal_mulai' => $this->tanggal($g->tanggal_mulai),
                'tanggal_selesai' => $this->tanggal($g->tanggal_selesai),
                'batas_waktu_pembayaran' => $this->tanggal($g->batas_waktu_pembayaran),
                'minimal_pembayaran' => $g->minimal_pembayaran,
                'status_buka' => (bool) $g->status_buka,
                'jumlah_pendaftaran' => $g->pendaftaran_count,
                // Jumlah sel tarif yang sudah diisi. 0 berarti tagihan
                // pendaftar gelombang ini TIDAK AKAN terbit sama sekali - itu
                // keadaan yang harus kelihatan dari daftar, bukan ditemukan
                // setelah ada wali yang mengeluh tagihannya kosong.
                'tarif_terisi' => $g->tarif()->count(),
            ])
            ->all();

        return Inertia::render('super-admin/gelombang', [
            'gelombang' => $gelombang,
            'tahunAjaran' => TahunAjaran::orderByDesc('nama')->pluck('nama')->all(),
            'filterAwal' => TahunAjaran::where('status_aktif', true)->value('nama') ?? '',
            'adaTahunAjaran' => TahunAjaran::exists(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('super-admin/gelombang-form', [
            ...$this->pilihan(),
            // Nominal dasar cuma ditawarkan saat MEMBUAT. Sesudah gelombangnya
            // ada, angkanya diubah per jalur - lihat komentar kelas.
            'komponen' => KomponenBiaya::aktif()->terurut()->get()->map(fn (KomponenBiaya $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $nominalDasar = $request->validate([
            'nominal_dasar' => ['present', 'array'],
            'nominal_dasar.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'nominal_dasar.*.integer' => 'Nominal dasar harus berupa angka.',
            'nominal_dasar.*.min' => 'Nominal dasar tidak boleh negatif.',
        ])['nominal_dasar'];

        $gelombang = DB::transaction(function () use ($data, $nominalDasar) {
            $gelombang = GelombangPpdb::create([
                ...$data,
                // Gelombang baru selalu lahir TERTUTUP. Membukanya tindakan
                // tersendiri, karena membuka berarti menutup gelombang lain
                // yang sedang berjalan - itu tidak boleh jadi efek samping
                // tombol "Simpan".
                'status_buka' => false,
            ]);

            $this->sebarNominalDasar($gelombang, $nominalDasar);
            $this->warisiBerkasWajib($gelombang);

            return $gelombang;
        });

        return to_route('super-admin.gelombang.index')
            ->with('success', "{$gelombang->nama} dibuat dan masih tertutup. Sesuaikan nominal per jalur di Jalur Pendaftaran, lalu buka pendaftarannya.");
    }

    public function edit(GelombangPpdb $gelombang): Response
    {
        $gelombang->load('tahunAjaran');

        $kebijakan = KebijakanKategori::where('gelombang_ppdb_id', $gelombang->id)->get()->keyBy('kategori_siswa_id');

        $berkasTerpilih = DokumenWajibKategori::where('gelombang_ppdb_id', $gelombang->id)
            ->get()
            ->groupBy('kategori_siswa_id')
            ->map(fn ($baris) => $baris->pluck('jenis_dokumen'));

        return Inertia::render('super-admin/gelombang-form', [
            ...$this->pilihan(),
            'gelombang' => [
                'id' => $gelombang->id,
                'tahun_ajaran_id' => $gelombang->tahun_ajaran_id,
                'nama' => $gelombang->nama,
                'tanggal_mulai' => $gelombang->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai' => $gelombang->tanggal_selesai?->format('Y-m-d'),
                'batas_waktu_pembayaran' => $gelombang->batas_waktu_pembayaran?->format('Y-m-d'),
                'minimal_pembayaran' => $gelombang->minimal_pembayaran,
                'status_buka' => (bool) $gelombang->status_buka,
                'jumlah_pendaftaran' => $gelombang->pendaftaran()->count(),
                // Yang tagihannya sudah terbit kebal terhadap perubahan di
                // halaman ini. Angka inilah yang dipakai sebagai peringatan,
                // bukan jumlah pendaftar - supaya peringatannya tidak berlebihan.
                'tagihan_sudah_terbit' => $gelombang->pendaftaran()->whereNotNull('minimal_bayar')->count(),
            ],
            'kebijakan' => KategoriSiswa::terurut()->get()->map(fn (KategoriSiswa $j) => [
                'kategori_siswa_id' => $j->id,
                'nama' => $j->nama,
                'kuota' => $kebijakan->get($j->id)?->kuota !== null ? (string) $kebijakan->get($j->id)->kuota : '',
                'minimal_bayar' => $kebijakan->get($j->id)?->minimal_bayar !== null ? (string) $kebijakan->get($j->id)->minimal_bayar : '',
                'terpakai' => KebijakanKategori::terpakaiUntuk($gelombang->id, $j->id),
                'dokumen' => $berkasTerpilih->get($j->id, collect())->all(),
            ])->all(),

            // Cuma jenis yang masih aktif yang ditawarkan. Yang sudah
            // dipensiunkan tidak hilang dari gelombang yang terlanjur
            // memintanya - dia tersaring di GelombangPpdb::dokumenWajibUntuk().
            'pilihanDokumen' => BerkasPersyaratan::peta(),
        ]);
    }

    public function update(Request $request, GelombangPpdb $gelombang): RedirectResponse
    {
        $data = $this->validasi($request);

        $kebijakan = $request->validate([
            'kebijakan' => ['present', 'array'],
            'kebijakan.*.kuota' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'kebijakan.*.minimal_bayar' => ['nullable', 'integer', 'min:0'],
        ], [
            'kebijakan.*.kuota.integer' => 'Kuota harus berupa angka, atau dikosongkan kalau tidak dibatasi.',
            'kebijakan.*.kuota.min' => 'Kuota tidak boleh negatif.',
            'kebijakan.*.minimal_bayar.integer' => 'Minimal bayar harus berupa angka, atau dikosongkan untuk ikut nilai bawaan.',
        ])['kebijakan'];

        $berkas = $request->validate([
            'dokumen' => ['present', 'array'],
            'dokumen.*' => ['present', 'array'],
            'dokumen.*.*' => ['string', Rule::in(array_keys(BerkasPersyaratan::peta()))],
        ])['dokumen'];

        DB::transaction(function () use ($gelombang, $data, $kebijakan, $berkas) {
            $gelombang->update($data);
            $this->simpanBerkasWajib($gelombang, $berkas);
            $this->simpanKebijakan($gelombang, $kebijakan);
        });

        return to_route('super-admin.gelombang.index')->with('success', "{$gelombang->nama} diperbarui.");
    }

    /**
     * Buka atau tutup pendaftaran di gelombang ini.
     *
     * Nilainya dikirim eksplisit, bukan dibalik dari keadaan sekarang: saklar
     * buta bikin dua klik beruntun - atau dua tab yang terbuka bersamaan -
     * berakhir di keadaan yang bukan diinginkan siapa pun.
     */
    public function status(Request $request, GelombangPpdb $gelombang): RedirectResponse
    {
        $data = $request->validate(['status_buka' => ['required', 'boolean']]);

        if ($data['status_buka']) {
            $gelombang->buka();

            return back()->with(
                'success',
                "{$gelombang->nama} dibuka. Gelombang lain yang tadinya terbuka ikut ditutup - pendaftar baru masuk ke sini."
            );
        }

        $gelombang->tutup();

        return back()->with(
            'success',
            "{$gelombang->nama} ditutup. Pendaftaran yang sudah masuk tetap berjalan dengan tenggatnya sendiri."
        );
    }

    /**
     * Isi nominal seluruh jalur sekaligus dengan satu angka per komponen.
     *
     * Cuma dipakai saat gelombang DIBUAT. Gunanya menghindari admin mengetik
     * empat angka yang sama untuk empat jalur; yang berbeda tinggal disesuaikan
     * belakangan per jalur. Komponen yang dikosongkan dilewati - artinya jalur
     * belum punya nominal untuk pos itu, bukan gratis.
     *
     * @param  array<int, int|null>  $nominalDasar
     */
    /**
     * Salin syarat berkas dari gelombang sebelumnya ke gelombang yang baru dibuat.
     *
     * Bawaannya diwarisi, BUKAN dikosongkan: gelombang tanpa satu pun syarat
     * berkas berarti pendaftar tidak diminta melampirkan apa-apa, dan itu
     * kelihatan seperti sistem yang rusak - bukan seperti keputusan sekolah.
     * Sekolah jarang mengganti syaratnya tiap gelombang, jadi menyalin yang
     * kemarin adalah tebakan yang paling sering benar; sisanya tinggal
     * disesuaikan di layar Ubah.
     *
     * Kalau ini gelombang pertama yang pernah ada, seluruh berkas yang aktif
     * dipasang ke semua jalur - lebih baik meminta berlebih lalu dikurangi
     * daripada tidak meminta apa-apa tanpa ada yang sadar.
     */
    private function warisiBerkasWajib(GelombangPpdb $baru): void
    {
        $sebelumnya = GelombangPpdb::whereKeyNot($baru->getKey())->latest('id')->first();

        $sumber = $sebelumnya
            ? DokumenWajibKategori::where('gelombang_ppdb_id', $sebelumnya->id)
                ->get()
                ->map(fn (DokumenWajibKategori $d) => [$d->kategori_siswa_id, $d->jenis_dokumen])
            : KategoriSiswa::pluck('id')->crossJoin(array_keys(BerkasPersyaratan::peta()));

        foreach ($sumber as [$kategoriId, $jenis]) {
            DokumenWajibKategori::updateOrCreate([
                'gelombang_ppdb_id' => $baru->id,
                'kategori_siswa_id' => $kategoriId,
                'jenis_dokumen' => $jenis,
            ]);
        }
    }

    /**
     * Simpan syarat berkas tiap jalur untuk gelombang ini.
     *
     * Hapus-lalu-tulis-ulang: barisnya cuma pasangan tanpa id yang dirujuk siapa
     * pun dan tanpa riwayat, jadi membandingkan selisih di sini cuma menambah
     * kode yang bisa salah.
     *
     * @param  array<int|string, array<int, string>>  $berkas
     */
    private function simpanBerkasWajib(GelombangPpdb $gelombang, array $berkas): void
    {
        $idJalurSah = KategoriSiswa::pluck('id')->all();

        DokumenWajibKategori::where('gelombang_ppdb_id', $gelombang->id)->delete();

        foreach ($berkas as $kategoriId => $jenisDipilih) {
            if (! in_array((int) $kategoriId, $idJalurSah, true)) {
                continue;
            }

            foreach (array_unique($jenisDipilih) as $jenis) {
                DokumenWajibKategori::create([
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kategori_siswa_id' => (int) $kategoriId,
                    'jenis_dokumen' => $jenis,
                ]);
            }
        }

        $gelombang->unsetRelation('dokumenWajib');
    }

    private function sebarNominalDasar(GelombangPpdb $gelombang, array $nominalDasar): void
    {
        $idKomponenSah = KomponenBiaya::pluck('id')->all();
        $idJalur = KategoriSiswa::pluck('id')->all();

        foreach ($nominalDasar as $komponenId => $nominal) {
            if ($nominal === null || ! in_array((int) $komponenId, $idKomponenSah, true)) {
                continue;
            }

            foreach ($idJalur as $jalurId) {
                TarifKategori::updateOrCreate(
                    [
                        'gelombang_ppdb_id' => $gelombang->id,
                        'komponen_biaya_id' => (int) $komponenId,
                        'kategori_siswa_id' => $jalurId,
                    ],
                    ['nominal' => (int) $nominal]
                );
            }
        }
    }

    /**
     * @param  array<int, array{kuota: int|null, minimal_bayar: int|null}>  $kebijakan
     */
    private function simpanKebijakan(GelombangPpdb $gelombang, array $kebijakan): void
    {
        $idJalurSah = KategoriSiswa::pluck('id')->all();

        foreach ($kebijakan as $jalurId => $nilai) {
            if (! in_array((int) $jalurId, $idJalurSah, true)) {
                continue;
            }

            $kunci = ['gelombang_ppdb_id' => $gelombang->id, 'kategori_siswa_id' => (int) $jalurId];
            $kuota = $nilai['kuota'] ?? null;
            $minimal = $nilai['minimal_bayar'] ?? null;

            // Dua-duanya kosong = tidak ada kebijakan khusus apa pun untuk jalur
            // ini; barisnya dibuang supaya tabelnya tidak menumpuk baris yang
            // seluruh isinya null - artinya sama persis dengan baris yang tidak
            // pernah ada.
            if ($kuota === null && $minimal === null) {
                KebijakanKategori::where($kunci)->delete();

                continue;
            }

            KebijakanKategori::updateOrCreate($kunci, [
                'kuota' => $kuota === null ? null : (int) $kuota,
                'minimal_bayar' => $minimal === null ? null : (int) $minimal,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pilihan(): array
    {
        return [
            'pilihanTahunAjaran' => TahunAjaran::orderByDesc('nama')
                ->get()
                ->map(fn (TahunAjaran $t) => ['id' => $t->id, 'nama' => $t->nama, 'aktif' => (bool) $t->status_aktif])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'tahun_ajaran_id' => ['required', Rule::exists('tahun_ajaran', 'id')],
            'nama' => ['required', 'string', 'max:50'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            // Jatuh tempo minimal bayar. Tidak boleh mendahului penutupan
            // pendaftaran: kalau lebih awal, ada pendaftar yang tenggat bayarnya
            // sudah lewat pada hari dia mendaftar.
            'batas_waktu_pembayaran' => ['nullable', 'date', 'after_or_equal:tanggal_selesai'],
            'minimal_pembayaran' => ['nullable', 'integer', 'min:0'],
        ], [
            'tahun_ajaran_id.required' => 'Pilih dulu tahun ajarannya.',
            'nama.required' => 'Nama gelombang wajib diisi, misalnya Gelombang 1.',
            'tanggal_mulai.required' => 'Tanggal mulai pendaftaran wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai pendaftaran wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
            'batas_waktu_pembayaran.after_or_equal' => 'Jatuh tempo pembayaran tidak boleh mendahului penutupan pendaftaran - '
                .'kalau lebih awal, ada pendaftar yang tenggatnya sudah lewat pada hari dia mendaftar.',
        ]);
    }

    private function tanggal(?\Illuminate\Support\Carbon $tanggal): ?string
    {
        return $tanggal?->locale('id')->translatedFormat('d F Y');
    }
}
