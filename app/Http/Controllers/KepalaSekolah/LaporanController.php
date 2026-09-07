<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\GelombangPpdb;
use App\Models\KuotaKategori;
use App\Models\PendaftaranPpdb;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modul Kepala Sekolah - MONITORING SAJA.
 *
 * Tidak ada satu pun aksi di seluruh modul ini: tidak ada tombol yang mengubah
 * status, menyetujui, atau menolak. Keputusan user 1 September 2026 (PRD 8.3):
 * Kepala Sekolah tidak ikut memutuskan diterima/ditolak. `diterima` tetap
 * dihitung otomatis dari minimal bayar, `ditolak` tetap ketetapan staf.
 *
 * Kalau nanti ada yang tergoda menambahkan tombol "Setujui" di sini, baca dulu
 * PRD bagian B - usul itu sudah pernah diajukan dan sengaja ditutup.
 *
 * Dua halaman, satu controller, karena keduanya menghitung dari kumpulan data
 * yang sama persis. Memisahnya jadi dua controller berarti menyalin agregasi
 * yang sama dua kali, dan dua salinan itu yang nanti berbeda pendapat.
 */
class LaporanController extends Controller
{
    /**
     * Beranda: gambaran satu layar. Angka besar, dua diagram, sisa daya tampung.
     */
    public function dashboard(): Response
    {
        $masuk = $this->pendaftaranMasuk();
        $gelombang = $this->gelombangBerjalan();

        return Inertia::render('kepala-sekolah/dashboard', [
            'ringkasan' => [
                'total' => $masuk->count(),
                'diterima' => $masuk->where('status', 'diterima')->count(),
                'diproses' => $masuk->whereIn('status', ['diajukan', 'perlu_perbaikan', 'diverifikasi'])->count(),
                'ditolak' => $masuk->where('status', 'ditolak')->count(),
            ],
            'statistik' => [
                'pendaftaran' => $masuk->countBy('status')->all(),
                'pembayaran' => $masuk
                    ->filter(fn (PendaftaranPpdb $p) => $p->bolehLihatTagihan())
                    ->countBy(fn (PendaftaranPpdb $p) => $p->statusPelunasan())
                    ->all(),
            ],
            'keuangan' => $this->keuangan($masuk),
            'gelombangBerjalan' => $gelombang ? [
                'nama' => $gelombang->nama.' · '.$gelombang->tahunAjaran->nama,
                'tanggal_selesai' => $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'),
            ] : null,
            'kuota' => $gelombang ? $this->kuota($gelombang) : [],
        ]);
    }

    /**
     * Rekapitulasi: angka yang sama, dipecah per gelombang dan per kategori.
     *
     * Seluruh gelombang dikirim sekaligus, termasuk tahun ajaran yang sudah
     * lewat - ini halaman laporan, dan membandingkan angkatan tahun ini dengan
     * tahun lalu justru gunanya. Penyaringnya di layar, bukan di query.
     */
    public function rekapitulasi(): Response
    {
        $masuk = $this->pendaftaranMasuk();

        $perGelombang = GelombangPpdb::with('tahunAjaran')
            ->get()
            ->sortByDesc(fn (GelombangPpdb $g) => $g->tahunAjaran->nama.$g->nama)
            ->values()
            ->map(function (GelombangPpdb $g) use ($masuk) {
                $isi = $masuk->where('gelombang_ppdb_id', $g->id);

                return [
                    'id' => $g->id,
                    'nama' => $g->nama,
                    'tahun_ajaran' => $g->tahunAjaran->nama,
                    'status_buka' => (bool) $g->status_buka,
                    'total' => $isi->count(),
                    'diproses' => $isi->whereIn('status', ['diajukan', 'perlu_perbaikan', 'diverifikasi'])->count(),
                    'diterima' => $isi->where('status', 'diterima')->count(),
                    'ditolak' => $isi->where('status', 'ditolak')->count(),
                    ...$this->keuangan($isi),
                ];
            });

        // Per kategori dipecah PER GELOMBANG juga, bukan ditotal lintas tahun:
        // kuota itu milik satu gelombang, jadi "sisa kuota" yang dijumlahkan
        // lintas gelombang tidak berarti apa-apa.
        //
        // Sengaja TIDAK mengirim terpakai() ke layar. Angkanya selalu sama dengan
        // total - ditolak, jadi menampilkannya sebagai kolom sendiri berarti tiga
        // angka yang saling menerangkan hal yang sama; pembacanya malah bertanya
        // kenapa "Pendaftar" dan "Terpakai" beda tipis (keputusan user 7 September
        // 2026). Yang tampil cukup 'ditolak' - itu yang menerangkan kenapa Sisa
        // tidak sama dengan Kuota - Pendaftar. 'sisa' sendiri tetap lewat model,
        // bukan dihitung ulang di sini.
        $perKategori = KuotaKategori::with(['kategoriSiswa', 'gelombang.tahunAjaran'])
            ->get()
            ->map(function (KuotaKategori $k) use ($masuk) {
                $isi = $masuk->where('gelombang_ppdb_id', $k->gelombang_ppdb_id)
                    ->where('kategori_siswa_id', $k->kategori_siswa_id);

                return [
                    'gelombang_id' => $k->gelombang_ppdb_id,
                    'gelombang' => $k->gelombang->nama,
                    'tahun_ajaran' => $k->gelombang->tahunAjaran->nama,
                    'kategori' => $k->kategoriSiswa->nama,
                    'total' => $isi->count(),
                    'diterima' => $isi->where('status', 'diterima')->count(),
                    'ditolak' => $isi->where('status', 'ditolak')->count(),
                    'kuota' => $k->kuota,
                    'sisa' => $k->sisa(),
                    'penuh' => $k->penuh(),
                ];
            })
            ->sortBy([['tahun_ajaran', 'desc'], ['gelombang', 'asc'], ['kategori', 'asc']])
            ->values();

        return Inertia::render('kepala-sekolah/rekapitulasi', [
            'perGelombang' => $perGelombang->all(),
            'perKategori' => $perKategori->all(),
            'temuan' => $this->temuanPerTahunAjaran($masuk),
            'tahunAjaran' => TahunAjaran::orderByDesc('nama')->pluck('nama')->all(),
            'filterAwal' => TahunAjaran::where('status_aktif', true)->value('nama') ?? '',
        ]);
    }

    /**
     * Empat kartu temuan, dihitung SEKALIGUS untuk tiap tahun ajaran plus satu
     * kunci '' berisi gabungan semua tahun.
     *
     * Bentuknya begini supaya penyaring tahun ajaran di layar tinggal MEMILIH
     * kelompok yang sudah jadi - tidak ada satu pun penjumlahan yang diulang di
     * TSX. Kalau angkanya dikirim mentah lalu dijumlahkan di sana, cepat atau
     * lambat halaman dan server akan berbeda pendapat soal angka yang sama.
     *
     * Ongkosnya kecil: seluruh pendaftaran memang sudah ada di memori, dan tahun
     * ajaran jumlahnya hitungan jari.
     */
    private function temuanPerTahunAjaran(Collection $masuk): array
    {
        $hasil = ['' => $this->temuan($masuk)];

        foreach ($masuk->groupBy(fn (PendaftaranPpdb $p) => $p->gelombang->tahunAjaran->nama) as $nama => $isi) {
            $hasil[$nama] = $this->temuan($isi);
        }

        return $hasil;
    }

    /**
     * Empat temuan untuk sekumpulan pendaftaran - dari mana calon murid datang,
     * dan bagaimana mereka menyelesaikan pembayaran.
     *
     * Keempatnya dihitung dari kumpulan data yang SAMA, dan di layar pun tampil
     * di bawah satu judul. Sempat dipecah jadi dua bagian, lalu disatukan lagi
     * karena dua judul membuat pembacanya menyangka ada dua sumber berbeda
     * (keputusan user, 8 September 2026).
     *
     * Sebaran wilayah tempat tinggal SENGAJA tidak ada (keputusan user,
     * 7 September 2026). Alamat disimpan sebagai teks bebas termasuk
     * kecamatannya, dan teks bebas tidak bisa diagregasi: satu kecamatan yang
     * sama akan terpecah jadi beberapa ejaan. Kalau kartu itu suatu saat
     * diinginkan, yang harus diubah lebih dulu bentuk isiannya, bukan di sini.
     */
    private function temuan(Collection $isi): array
    {
        return [
            // 'Belum/tidak ikut PAUD' TIDAK didorong ke bawah - itu jawaban yang
            // sah dan pantas bersaing di peringkat. Yang didorong cuma 'Belum
            // diisi', yang artinya tidak ada jawaban sama sekali.
            // Lima teratas saja. Kartu ini RINGKASAN - daftar sebelas baris
            // bikin halaman panjang tanpa menambah jawaban, karena ekornya
            // masing-masing cuma satu-dua anak dan tidak bisa ditindaklanjuti.
            'asalPaud' => $this->peringkat($isi, fn (PendaftaranPpdb $p) => $p->labelAsalPaud(), 'Belum diisi', 5),
            'sumberInformasi' => $this->sumberInformasi($isi),
            'jedaBayar' => $this->jedaBayar($isi),
            'polaCicilan' => $this->polaCicilan($isi),
        ];
    }

    /**
     * Peringkat jumlah menurut satu penggolong, terbanyak di atas.
     *
     * Dipotong 10 teratas; sisanya dilipat jadi satu baris. Bukan demi ruang -
     * daftar peringkat yang panjang justru menyembunyikan temuannya, karena
     * ekornya yang masing-masing berisi satu orang terlihat sama pentingnya
     * dengan puncaknya.
     *
     * $keBawah dipakai buat label yang secara arti BUKAN peringkat ("Tidak
     * menjawab", "Belum diisi"). Kalau ikut diurutkan menurut jumlah, dia bisa
     * nangkring di puncak dan terbaca seolah-olah itu jawaban terbanyak.
     */
    /**
     * Sumber informasi, disiapkan sebagai IRISAN DONAT - bukan peringkat batang.
     *
     * Bentuknya beda karena pertanyaannya beda. Asal PAUD itu peringkat ("siapa
     * penyumbang terbanyak", ekornya panjang dan terbuka); sumber informasi itu
     * komposisi ("dari seluruh yang menjawab, berapa bagiannya lewat tiap
     * saluran") dengan pilihan yang jumlahnya tetap dan tiap orang cuma memilih
     * satu. Memberi keduanya bentuk yang sama bikin halaman terbaca seperti
     * daftar berulang, padahal isinya menjawab dua hal berbeda.
     *
     * Dibatasi EMPAT irisan berwarna plus satu abu untuk sisanya. Batas itu
     * datang dari warna, bukan selera: palet proyek cuma punya empat warna yang
     * lolos uji keterbedaan buta warna (ΔE terburuk 11,7). Memaksa irisan kelima
     * berarti mengarang warna baru yang belum teruji.
     *
     * 'Tidak menjawab' sengaja DI LUAR donat, dilaporkan sebagai kalimat
     * tersendiri. Dia bukan saluran promosi, jadi memasukkannya sebagai irisan
     * bikin persentase tiap saluran mengecil oleh sesuatu yang bukan saluran.
     * Dengan dikeluarkan, persentasenya jadi "dari yang menjawab" - dan itu
     * memang pertanyaan yang benar.
     */
    private function sumberInformasi(Collection $isi): array
    {
        $jumlah = $isi->groupBy(fn (PendaftaranPpdb $p) => $p->labelSumberInformasi())->map->count();

        $tidakMenjawab = (int) ($jumlah->get('Tidak menjawab') ?? 0);
        $menjawab = $jumlah->except(['Tidak menjawab'])->sortDesc();

        $irisan = $menjawab->take(4)
            ->map(fn (int $n, string $label) => ['label' => $label, 'jumlah' => $n, 'agregat' => false])
            ->values();

        $sisa = $menjawab->skip(4);

        if ($sisa->isNotEmpty()) {
            $irisan->push([
                'label' => 'Saluran lain ('.$sisa->count().')',
                'jumlah' => (int) $sisa->sum(),
                'agregat' => true,
            ]);
        }

        return [
            'irisan' => $irisan->all(),
            'menjawab' => (int) $menjawab->sum(),
            'tidakMenjawab' => $tidakMenjawab,
        ];
    }

    private function peringkat(Collection $isi, callable $penggolong, ?string $keBawah = null, int $maksimal = 10): array
    {
        $jumlah = $isi->groupBy($penggolong)->map->count();

        $bawah = $keBawah !== null && $jumlah->has($keBawah) ? (int) $jumlah->get($keBawah) : 0;
        $utama = $jumlah->except($keBawah === null ? [] : [$keBawah])->sortDesc();

        $baris = $utama->take($maksimal)
            ->map(fn (int $n, string $label) => ['label' => $label, 'jumlah' => $n, 'agregat' => false])
            ->values();

        $sisa = $utama->skip($maksimal);

        // 'agregat' menandai baris yang BUKAN satu kelompok nyata: dia jumlah
        // dari banyak kelompok kecil. Ekornya sering menang telak melawan juara
        // sebenarnya - di data contoh, "Lainnya (8 kelompok)" bernilai 10
        // sementara sekolah terbanyak cuma 6. Kalau ikut menentukan panjang
        // batang, mata pembaca tertarik ke kelompok yang justru tidak bisa
        // ditindaklanjuti. Layar memakai tanda ini untuk mengeluarkannya dari
        // skala dan memudarkan warnanya.
        if ($sisa->isNotEmpty()) {
            $baris->push([
                'label' => 'Lainnya ('.$sisa->count().' kelompok)',
                'jumlah' => (int) $sisa->sum(),
                'agregat' => true,
            ]);
        }

        if ($bawah > 0) {
            $baris->push(['label' => $keBawah, 'jumlah' => $bawah, 'agregat' => true]);
        }

        return $baris->all();
    }

    /**
     * Berapa lama wali menggantung antara berkasnya lolos dan transfer pertama.
     *
     * Yang BELUM transfer sama sekali sengaja dipisah, tidak ikut ke rata-rata.
     * Jedanya belum selesai berjalan - memasukkannya dengan angka apa pun
     * (0 maupun umur hari ini) akan menggeser rata-ratanya ke arah yang salah.
     * Justru kelompok inilah yang paling perlu ditindaklanjuti, jadi dia
     * dilaporkan sebagai angkanya sendiri.
     */
    private function jedaBayar(Collection $isi): array
    {
        $jeda = $isi->map(fn (PendaftaranPpdb $p) => $p->hariSampaiTransferPertama())
            ->filter(fn (?int $h) => $h !== null)
            ->values();

        $ember = ['0-3 hari' => 0, '4-7 hari' => 0, '8-14 hari' => 0, 'Lebih dari 14 hari' => 0];

        foreach ($jeda as $hari) {
            $kunci = match (true) {
                $hari <= 3 => '0-3 hari',
                $hari <= 7 => '4-7 hari',
                $hari <= 14 => '8-14 hari',
                default => 'Lebih dari 14 hari',
            };
            $ember[$kunci]++;
        }

        return [
            'terukur' => $jeda->count(),
            // Dibulatkan ke satu angka di belakang koma - ketelitian lebih dari
            // itu palsu pada data sekecil ini.
            'rataHari' => $jeda->isEmpty() ? null : round($jeda->avg(), 1),
            'terlamaHari' => $jeda->isEmpty() ? null : (int) $jeda->max(),
            'belumTransfer' => $isi->filter(fn (PendaftaranPpdb $p) => $p->belumTransferSamaSekali())->count(),
            // Ember jeda semuanya kelompok nyata, tidak ada yang berupa lipatan -
            // makanya 'agregat' selalu false di sini.
            'sebaran' => collect($ember)
                ->map(fn (int $n, string $label) => ['label' => $label, 'jumlah' => $n, 'agregat' => false])
                ->values()
                ->all(),
        ];
    }

    /**
     * Dari yang SUDAH mencapai minimal: berapa yang menuntaskannya sekali
     * transfer, berapa yang mencicil. Gambaran daya beli seangkatan yang selama
     * ini tidak pernah diringkas.
     *
     * Yang belum mencapai minimal tidak ikut - mereka belum selesai, jadi belum
     * ketahuan akan sekaligus atau mencicil.
     */
    private function polaCicilan(Collection $isi): array
    {
        $selesai = $isi->filter(fn (PendaftaranPpdb $p) => $p->sudahPenuhiMinimal());

        $sekaligus = $selesai->filter(fn (PendaftaranPpdb $p) => $p->jumlahTransferSah() <= 1)->count();

        return [
            'sekaligus' => $sekaligus,
            'dicicil' => $selesai->count() - $sekaligus,
        ];
    }

    /**
     * Semua pendaftaran KECUALI draft.
     *
     * Draft belum pernah dikirim wali: sekolah belum punya hubungan apa pun
     * dengannya, dan kursi kuota pun belum dipegang. Menghitungnya sebagai
     * "pendaftar" bikin angka di laporan lebih besar daripada kenyataannya.
     */
    private function pendaftaranMasuk(): Collection
    {
        return PendaftaranPpdb::with([
            'kategoriSiswa', 'gelombang.tahunAjaran', 'pembayaran', 'tagihanItem',
            'asalPaud',
        ])
            ->where('status', '!=', 'draft')
            ->get();
    }

    private function gelombangBerjalan(): ?GelombangPpdb
    {
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        if ($tahunAktif === null) {
            return null;
        }

        return GelombangPpdb::with('tahunAjaran')
            ->where('tahun_ajaran_id', $tahunAktif->id)
            ->where('status_buka', true)
            ->latest()
            ->first();
    }

    /**
     * Angka uang untuk sekumpulan pendaftaran.
     *
     * 'sudah_masuk' HANYA menghitung transfer yang sudah disahkan staf -
     * memakai totalTerbayar() milik model, bukan menjumlahkan sendiri. Bukti
     * yang masih menunggu diperiksa dilaporkan terpisah, karena sebagiannya
     * bisa saja ditolak; menggabungkannya berarti melaporkan uang yang belum
     * tentu ada.
     */
    private function keuangan(Collection $pendaftaran): array
    {
        $tagihan = $pendaftaran->sum(fn (PendaftaranPpdb $p) => $p->totalTagihan());
        $masuk = $pendaftaran->sum(fn (PendaftaranPpdb $p) => $p->totalTerbayar());

        $menunggu = $pendaftaran->sum(
            fn (PendaftaranPpdb $p) => $p->pembayaran
                ->where('status', 'menunggu_verifikasi')
                ->sum('nominal_transfer')
        );

        return [
            'total_tagihan' => (int) $tagihan,
            'sudah_masuk' => (int) $masuk,
            'menunggu_diperiksa' => (int) $menunggu,
            'sisa_tagihan' => (int) max(0, $tagihan - $masuk),
        ];
    }

    /**
     * Sisa daya tampung per kategori. Angkanya lewat KuotaKategori, bukan
     * dihitung ulang - aturan status mana yang memegang kursi tinggal di model.
     */
    private function kuota(GelombangPpdb $gelombang): array
    {
        return KuotaKategori::with('kategoriSiswa')
            ->where('gelombang_ppdb_id', $gelombang->id)
            ->get()
            ->sortBy(fn (KuotaKategori $k) => $k->kategoriSiswa->nama)
            ->values()
            ->map(fn (KuotaKategori $k) => [
                'nama' => $k->kategoriSiswa->nama,
                'kuota' => $k->kuota,
                'terpakai' => $k->terpakai(),
                'sisa' => $k->sisa(),
                'penuh' => $k->penuh(),
            ])
            ->all();
    }
}
