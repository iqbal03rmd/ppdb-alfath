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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gelombang PPDB - jendela pendaftaran beserta seluruh angka yang berlaku
 * selama jendela itu terbuka.
 *
 * Lima hal yang diatur di sini, dan kelimanya memang berubah tiap gelombang:
 *
 *   tanggal           buka, tutup, dan jatuh tempo minimal bayar
 *   kuota per jalur   daya tampung, biasanya menyusut di gelombang berikutnya
 *   minimal bayar     setoran awal per jalur, wajib diisi
 *   nominal komponen  harga tiap pos biaya, per jalur
 *   berkas wajib      berkas yang diminta, per jalur
 *
 * Kelimanya diatur di SATU layar yang sama untuk Tambah dan Ubah Gelombang,
 * karena kelimanya
 * memang satu keputusan: "ketentuan yang berlaku untuk angkatan ini". Waktu
 * nominalnya masih diatur di menu Jalur Pendaftaran, angka yang bersifat
 * gelombang x jalur harus dicari di layar yang tidak menyebut gelombang sama
 * sekali. Tidak ada nominal dasar: sejak gelombang dibuat, setiap angka sudah
 * jelas menjadi milik jalur yang mana.
 *
 * KETENTUAN PUNYA DUA KUNCI - lihat GelombangPpdb::alasanTidakBisaDiubah().
 * Selagi terbuka: terkunci sementara, tinggal ditutup. Sesudah jendelanya
 * lewat: BEKU PERMANEN, karena pendaftar di dalamnya membaca tenggat, kuota,
 * dan syarat berkas gelombang ini secara hidup. Gelombang lahir tertutup justru
 * supaya seluruh angkanya bisa disiapkan sebelum jendelanya berjalan.
 *
 * Layar Ubah tetap bisa DIBUKA walau terkunci - dia merangkap satu-satunya
 * tempat pengaturan gelombang lama masih bisa dibaca. Yang menolak update(),
 * bukan edit().
 *
 * TIDAK ADA aksi hapus: gelombang cascade ke pendaftaran_ppdb, yang cascade lagi
 * ke pembayaran_ppdb. Yang tersedia menutup pendaftarannya.
 */
class GelombangController extends Controller
{
    public function index(): Response
    {
        $gelombang = GelombangPpdb::with('tahunAjaran')
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
                'status_buka' => (bool) $g->status_buka,
                // Jumlah sel tarif yang sudah diisi. 0 berarti tagihan
                // pendaftar gelombang ini TIDAK AKAN terbit sama sekali - itu
                // keadaan yang harus kelihatan dari daftar, bukan ditemukan
                // setelah ada wali yang mengeluh tagihannya kosong.
                'tarif_terisi' => $g->tarif()->count(),
                // Alasannya dihitung di sini dan dikirim sebagai prop, bukan
                // disusun ulang di TSX: syarat yang ditegakkan server dan
                // kalimat yang dibaca admin tidak boleh berbeda pendapat.
                'alasan_tidak_bisa_dibuka' => $g->alasanTidakBisaDibuka(),
                'alasan_tidak_bisa_diubah' => $g->alasanTidakBisaDiubah(),
                // Satu kata, dihitung server - badge di daftar tinggal
                // memetakannya, tidak menyimpulkan sendiri dari tiga boolean.
                'keadaan' => $g->keadaan(),
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
        $komponen = KomponenBiaya::aktif()->terurut()->get();
        $dokumenAwal = $this->dokumenAwalGelombangBaru();

        return Inertia::render('super-admin/gelombang-form', [
            ...$this->pilihan(),
            'kebijakan' => KategoriSiswa::terurut()->get()->map(fn (KategoriSiswa $j) => [
                'kategori_siswa_id' => $j->id,
                'nama' => $j->nama,
                'kuota' => '',
                'minimal_bayar' => '',
                'terpakai' => 0,
                // Berkas gelombang sebelumnya hanya menjadi isian awal. Admin
                // tetap melihat dan boleh mengubahnya sebelum gelombang dibuat.
                'dokumen' => $dokumenAwal[$j->id] ?? [],
                'tarif' => $this->tarifJalur(null, $komponen),
            ])->all(),
            'komponen' => $komponen->map(fn (KomponenBiaya $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
            ])->all(),
            'pilihanDokumen' => BerkasPersyaratan::peta(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $ketentuan = $this->validasiKetentuan($request);

        $gelombang = DB::transaction(function () use ($data, $ketentuan) {
            $gelombang = GelombangPpdb::create([
                ...$data,
                // Gelombang baru selalu lahir TERTUTUP. Membukanya tindakan
                // tersendiri, karena membuka berarti menutup gelombang lain
                // yang sedang berjalan - itu tidak boleh jadi efek samping
                // tombol "Simpan".
                'status_buka' => false,
            ]);

            $this->simpanBerkasWajib($gelombang, $ketentuan['berkas']);
            $this->simpanKebijakan($gelombang, $ketentuan['kebijakan']);
            $this->simpanTarif($gelombang, $ketentuan['tarif']);

            return $gelombang;
        });

        return to_route('super-admin.gelombang.index')
            ->with('success', "{$gelombang->nama} beserta ketentuan tiap jalurnya dibuat dan masih tertutup. Buka saat siap menerima pendaftar.");
    }

    public function edit(GelombangPpdb $gelombang): Response
    {
        $gelombang->load('tahunAjaran');

        $kebijakan = KebijakanKategori::where('gelombang_ppdb_id', $gelombang->id)->get()->keyBy('kategori_siswa_id');

        // Pos yang dimatikan tidak ditawarkan lagi - mengisi harga untuk sesuatu
        // yang tidak akan ditagihkan cuma bikin bingung. Nominalnya yang sudah
        // tersimpan tetap ada di database, tinggal nyalakan lagi posnya.
        $komponen = KomponenBiaya::aktif()->terurut()->get();

        $tarif = TarifKategori::where('gelombang_ppdb_id', $gelombang->id)
            ->get()
            ->groupBy('kategori_siswa_id')
            ->map(fn ($baris) => $baris->keyBy('komponen_biaya_id'));

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
                'status_buka' => (bool) $gelombang->status_buka,
                'bisa_pindah_tahun_ajaran' => $gelombang->bisaPindahTahunAjaran(),
            ],

            // Layar ini merangkap dua: formulir Ubah, dan - kalau terkunci -
            // satu-satunya tempat pengaturan gelombang lama masih bisa DIBACA.
            // Tanpa itu, kuota, nominal, dan syarat berkas angkatan yang sudah
            // lewat lenyap dari pandangan selamanya.
            'terkunci' => $gelombang->alasanTidakBisaDiubah(),
            'kebijakan' => KategoriSiswa::terurut()->get()->map(fn (KategoriSiswa $j) => [
                'kategori_siswa_id' => $j->id,
                'nama' => $j->nama,
                'kuota' => $kebijakan->get($j->id)?->kuota !== null ? (string) $kebijakan->get($j->id)->kuota : '',
                'minimal_bayar' => $kebijakan->get($j->id)?->minimal_bayar !== null ? (string) $kebijakan->get($j->id)->minimal_bayar : '',
                'terpakai' => KebijakanKategori::terpakaiUntuk($gelombang->id, $j->id),
                'dokumen' => $berkasTerpilih->get($j->id, collect())->all(),
                // String, bukan integer: kotak isian terkendali di React, dan ''
                // yang berarti "belum diatur" harus bisa dibedakan dari '0' yang
                // artinya jalur ini dibebaskan dari pos tersebut.
                'tarif' => $this->tarifJalur($tarif->get($j->id), $komponen),
            ])->all(),

            'komponen' => $komponen->map(fn (KomponenBiaya $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
            ])->all(),

            // Cuma jenis yang masih aktif yang ditawarkan. Yang sudah
            // dipensiunkan tidak hilang dari gelombang yang terlanjur
            // memintanya - dia tersaring di GelombangPpdb::dokumenWajibUntuk().
            'pilihanDokumen' => BerkasPersyaratan::peta(),
        ]);
    }

    public function update(Request $request, GelombangPpdb $gelombang): RedirectResponse
    {
        $gelombang->loadMissing('tahunAjaran');

        if ($alasan = $gelombang->alasanTidakBisaDiubah()) {
            return to_route('super-admin.gelombang.index')->with('error', $alasan);
        }

        $data = $this->validasi($request);

        if ((int) $data['tahun_ajaran_id'] !== $gelombang->tahun_ajaran_id && ! $gelombang->bisaPindahTahunAjaran()) {
            return back()->with(
                'error',
                'Tahun ajaran tidak bisa diganti karena gelombang ini sudah memiliki pendaftar. Jadwal dan tagihan mereka harus tetap berada di angkatan asalnya.'
            );
        }

        $ketentuan = $this->validasiKetentuan($request);

        DB::transaction(function () use ($gelombang, $data, $ketentuan) {
            $gelombang->update($data);
            $this->simpanBerkasWajib($gelombang, $ketentuan['berkas']);
            $this->simpanKebijakan($gelombang, $ketentuan['kebijakan']);
            $this->simpanTarif($gelombang, $ketentuan['tarif']);
        });

        return to_route('super-admin.gelombang.index')->with('success', "{$gelombang->nama} diperbarui.");
    }

    /**
     * Buka atau tutup pendaftaran di gelombang ini.
     *
     * Nilainya dikirim eksplisit, bukan dibalik dari keadaan sekarang: saklar
     * buta bikin dua klik beruntun - atau dua tab yang terbuka bersamaan -
     * berakhir di keadaan yang bukan diinginkan siapa pun.
     *
     * MEMBUKA bersyarat (lihat GelombangPpdb::alasanTidakBisaDibuka()), MENUTUP
     * tidak pernah. Menutup harus selalu bisa dilakukan: itu jalan keluar dari
     * hampir semua keadaan salah di layar ini, termasuk satu-satunya cara
     * membuka kunci layar Ubah.
     */
    public function status(Request $request, GelombangPpdb $gelombang): RedirectResponse
    {
        $data = $request->validate(['status_buka' => ['required', 'boolean']]);

        if ($data['status_buka']) {
            // Ditegakkan di server, bukan cuma dengan mematikan tombolnya:
            // tombol mati menyembunyikan jalannya, permintaannya tetap bisa
            // dikirim langsung.
            $gelombang->loadMissing('tahunAjaran');

            if ($alasan = $gelombang->alasanTidakBisaDibuka()) {
                return back()->with('error', $alasan);
            }

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
     * Isian awal syarat berkas untuk formulir Tambah Gelombang.
     *
     * Bawaannya diwarisi, BUKAN dikosongkan: formulir tanpa satu pun syarat
     * berkas berarti pendaftar tidak diminta melampirkan apa-apa, dan itu
     * kelihatan seperti sistem yang rusak - bukan seperti keputusan sekolah.
     * Sekolah jarang mengganti syaratnya tiap gelombang, jadi menyalin yang
     * kemarin adalah tebakan yang paling sering benar; sisanya tinggal
     * disesuaikan sebelum tombol Buat Gelombang ditekan.
     *
     * Kalau ini gelombang pertama yang pernah ada, seluruh berkas yang aktif
     * dipasang ke semua jalur - lebih baik meminta berlebih lalu dikurangi
     * daripada tidak meminta apa-apa tanpa ada yang sadar.
     *
     * @return array<int, array<int, string>>
     */
    private function dokumenAwalGelombangBaru(): array
    {
        $sebelumnya = GelombangPpdb::latest('id')->first();

        if ($sebelumnya) {
            $kodeAktifTerurut = array_keys(BerkasPersyaratan::peta());

            return DokumenWajibKategori::where('gelombang_ppdb_id', $sebelumnya->id)
                ->whereIn('jenis_dokumen', $kodeAktifTerurut)
                ->get()
                ->groupBy('kategori_siswa_id')
                ->map(function ($baris) use ($kodeAktifTerurut) {
                    $diminta = $baris->pluck('jenis_dokumen')->all();

                    return array_values(array_filter(
                        $kodeAktifTerurut,
                        fn (string $jenis) => in_array($jenis, $diminta, true)
                    ));
                })
                ->all();
        }

        $semuaBerkasAktif = array_keys(BerkasPersyaratan::peta());

        return KategoriSiswa::pluck('id')
            ->mapWithKeys(fn (int $kategoriId) => [$kategoriId => $semuaBerkasAktif])
            ->all();
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

            // Barisnya TIDAK pernah dibuang lagi. Sejak minimal bayar wajib, tiap
            // jalur selalu punya angka - dan baris yang hilang artinya jalur itu
            // jatuh ke "harus lunas" (lihat PendaftaranPpdb::hitungMinimalBayar),
            // bukan keadaan yang pantas lahir dari menyimpan formulir.
            KebijakanKategori::updateOrCreate($kunci, [
                'kuota' => $kuota === null ? null : (int) $kuota,
                'minimal_bayar' => (int) $nilai['minimal_bayar'],
            ]);
        }
    }

    /**
     * Nominal tiap komponen untuk satu jalur, siap dikirim ke layar.
     *
     * @param  Collection<int, TarifKategori>|null  $tersimpan
     * @param  Collection<int, KomponenBiaya>  $komponen
     * @return array<string, string>
     */
    private function tarifJalur(?Collection $tersimpan, Collection $komponen): array
    {
        $hasil = [];

        foreach ($komponen as $k) {
            $nominal = $tersimpan?->get($k->id)?->nominal;

            // String, bukan integer: kotak isian terkendali di React, dan ''
            // yang berarti "belum diatur" harus bisa dibedakan dari '0'.
            $hasil[(string) $k->id] = $nominal === null ? '' : (string) $nominal;
        }

        return $hasil;
    }

    /**
     * Simpan nominal tiap komponen, per jalur, untuk gelombang ini.
     *
     * KOSONG BUKAN NOL, dan bedanya menentukan: kosong artinya pos itu tidak
     * muncul sama sekali di tagihan jalur ini, sedangkan 0 artinya muncul
     * sebagai baris Rp0 - jalur ini dibebaskan darinya. Karena itu yang
     * dikosongkan barisnya DIHAPUS, bukan disimpan bernilai nol.
     *
     * Komponen yang tidak dikirim layar - yang sudah dinonaktifkan - tidak
     * disentuh sama sekali: nominalnya menunggu di tempat kalau posnya
     * dinyalakan lagi.
     *
     * @param  array<int|string, array<int|string, int|null>>  $tarif
     */
    private function simpanTarif(GelombangPpdb $gelombang, array $tarif): void
    {
        $idJalurSah = KategoriSiswa::pluck('id')->all();
        $idKomponenSah = KomponenBiaya::pluck('id')->all();

        foreach ($tarif as $jalurId => $perKomponen) {
            if (! in_array((int) $jalurId, $idJalurSah, true)) {
                continue;
            }

            foreach ($perKomponen as $komponenId => $nominal) {
                if (! in_array((int) $komponenId, $idKomponenSah, true)) {
                    continue;
                }

                $kunci = [
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kategori_siswa_id' => (int) $jalurId,
                    'komponen_biaya_id' => (int) $komponenId,
                ];

                if ($nominal === null) {
                    TarifKategori::where($kunci)->delete();

                    continue;
                }

                TarifKategori::updateOrCreate($kunci, ['nominal' => (int) $nominal]);
            }
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

    /**
     * Validasi seluruh ketentuan yang diisi per jalur.
     *
     * Bentuk muatannya sengaja sama untuk store() dan update(): membuat
     * gelombang bukan lagi langkah setengah jadi yang harus dilanjutkan lewat
     * layar lain.
     *
     * @return array{
     *     kebijakan: array<int|string, array{kuota: int|null, minimal_bayar: int}>,
     *     berkas: array<int|string, array<int, string>>,
     *     tarif: array<int|string, array<int|string, int|null>>
     * }
     */
    private function validasiKetentuan(Request $request): array
    {
        $aturanKebijakan = [
            'kebijakan' => ['present', 'array'],
            'kebijakan.*.kuota' => ['nullable', 'integer', 'min:0', 'max:10000'],
            // WAJIB, dan boleh 0. Tidak ada angka bawaan yang menambal jalur
            // yang kosong; pembebasan harus diketik sebagai 0 dengan sadar.
            'kebijakan.*.minimal_bayar' => ['required', 'integer', 'min:0'],
        ];
        $aturanBerkas = [
            'dokumen' => ['present', 'array'],
            'dokumen.*' => ['present', 'array'],
            'dokumen.*.*' => ['string', Rule::in(array_keys(BerkasPersyaratan::peta()))],
        ];
        $aturanTarif = [
            'tarif' => ['present', 'array'],
            'tarif.*' => ['present', 'array'],
            'tarif.*.*' => ['nullable', 'integer', 'min:0'],
        ];

        // Wildcard hanya memeriksa baris yang DIKIRIM. Tanpa aturan dinamis
        // ini, satu jalur utuh bisa dihilangkan dari request dan lolos tanpa
        // minimal bayar, daftar berkas, maupun sel nominalnya.
        $idJalur = KategoriSiswa::pluck('id')->all();
        $idKomponenAktif = KomponenBiaya::aktif()->pluck('id')->all();

        foreach ($idJalur as $jalurId) {
            $aturanKebijakan["kebijakan.{$jalurId}"] = ['required', 'array'];
            $aturanKebijakan["kebijakan.{$jalurId}.minimal_bayar"] = ['required', 'integer', 'min:0'];
            $aturanBerkas["dokumen.{$jalurId}"] = ['present', 'array'];
            $aturanTarif["tarif.{$jalurId}"] = ['present', 'array'];

            foreach ($idKomponenAktif as $komponenId) {
                $aturanTarif["tarif.{$jalurId}.{$komponenId}"] = ['present', 'nullable', 'integer', 'min:0'];
            }
        }

        $kebijakan = $request->validate($aturanKebijakan, [
            'kebijakan.*.kuota.integer' => 'Kuota harus berupa angka, atau dikosongkan kalau tidak dibatasi.',
            'kebijakan.*.kuota.min' => 'Kuota tidak boleh negatif.',
            'kebijakan.*.required' => 'Ketentuan setiap jalur wajib dikirim.',
            'kebijakan.*.minimal_bayar.required' => 'Minimal bayar wajib diisi. Isi 0 kalau jalur ini memang diterima tanpa menyetor.',
            'kebijakan.*.minimal_bayar.integer' => 'Minimal bayar harus berupa angka.',
            'kebijakan.*.minimal_bayar.min' => 'Minimal bayar tidak boleh negatif.',
        ])['kebijakan'];

        $berkas = $request->validate($aturanBerkas)['dokumen'];

        $tarif = $request->validate($aturanTarif, [
            'tarif.*.*.integer' => 'Nominal harus berupa angka, atau dikosongkan kalau pos itu tidak ditagihkan ke jalur ini.',
            'tarif.*.*.min' => 'Nominal tidak boleh negatif.',
        ])['tarif'];

        return [
            'kebijakan' => $kebijakan,
            'berkas' => $berkas,
            'tarif' => $tarif,
        ];
    }

    private function tanggal(?Carbon $tanggal): ?string
    {
        return $tanggal?->locale('id')->translatedFormat('d F Y');
    }
}
