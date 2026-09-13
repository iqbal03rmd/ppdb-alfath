<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GelombangPpdb extends Model
{
    protected $table = 'gelombang_ppdb';

    protected $fillable = [
        'tahun_ajaran_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
        'batas_waktu_pembayaran',
        'status_buka',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'batas_waktu_pembayaran' => 'date',
        'status_buka' => 'boolean',
    ];

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function kebijakanKategori(): HasMany
    {
        return $this->hasMany(KebijakanKategori::class);
    }

    /**
     * Syarat berkas seluruh jalur pada gelombang ini.
     *
     * Eager-load relasi ini (`gelombang.dokumenWajib`) di halaman yang memanggil
     * PendaftaranPpdb::dokumenWajib() untuk BANYAK baris sekaligus, mis. antrian
     * Verifikasi Pendaftaran. Tanpa itu tiap baris menembak query sendiri.
     */
    public function dokumenWajib(): HasMany
    {
        return $this->hasMany(DokumenWajibKategori::class, 'gelombang_ppdb_id');
    }

    /**
     * Kode berkas yang wajib diunggah pendaftar sebuah jalur DI GELOMBANG INI.
     *
     * Dibaca lewat properti relasi (bukan query builder) supaya kalau relasinya
     * sudah di-eager-load, tidak ada query tambahan per baris.
     *
     * Jenis yang sudah DINONAKTIFKAN di Berkas Persyaratan ikut tersaring,
     * walau barisnya masih ada. Itu yang bikin mematikan sebuah jenis aman:
     * jalur yang terlanjur mewajibkannya berhenti memintanya, tanpa perlu
     * menyisir ulang semua gelombang.
     *
     * @return array<int, string>
     */
    public function dokumenWajibUntuk(int $kategoriSiswaId): array
    {
        $diminta = $this->dokumenWajib
            ->where('kategori_siswa_id', $kategoriSiswaId)
            ->pluck('jenis_dokumen')
            ->all();

        // Urutannya ikut daftar master, bukan urutan baris di database - tanpa
        // ini susunan checklist Unggah Berkas yang dibaca wali bisa
        // berpindah-pindah tanpa sebab.
        return array_values(array_filter(
            array_keys(BerkasPersyaratan::peta()),
            fn (string $jenis) => in_array($jenis, $diminta, true)
        ));
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    /**
     * Nominal seluruh komponen x jalur untuk gelombang ini. Komponennya sendiri
     * global (KomponenBiaya), jadi gelombang cuma memegang angkanya.
     */
    public function tarif(): HasMany
    {
        return $this->hasMany(TarifKategori::class);
    }

    /**
     * Buka pendaftaran di gelombang ini, dan tutup yang lain.
     *
     * `PendaftaranController::gelombangDibuka()` memilih gelombang berstatus
     * buka dengan `latest()->first()`. Kalau ada dua yang terbuka, satu di
     * antaranya tidak akan pernah kebagian pendaftar - dan tidak ada apa pun di
     * layar yang menerangkan kenapa. Jadi "buka" di sini berarti buka yang ini
     * SAJA: keadaan di database dibuat sama dengan yang sebenarnya berlaku.
     *
     * Menutup gelombang TIDAK menyentuh pendaftaran yang sudah ada di dalamnya.
     * Tenggat mereka tetap milik gelombangnya sendiri (jatuhTempoMinimal()),
     * jadi wali Gelombang 1 tetap memegang tanggal Gelombang 1 walau
     * gelombangnya sudah lama ditutup.
     */
    public function buka(): void
    {
        DB::transaction(function () {
            static::where('status_buka', true)
                ->whereKeyNot($this->getKey())
                ->update(['status_buka' => false]);

            $this->update(['status_buka' => true]);
        });
    }

    public function tutup(): void
    {
        $this->update(['status_buka' => false]);
    }

    /**
     * Kenapa gelombang ini tidak boleh dibuka - null berarti boleh.
     *
     * Dua syarat, dan dua-duanya perlu karena masing-masing menutup lubang yang
     * tidak ditutup yang lain:
     *
     *   tahun ajaran berjalan   supaya gelombang angkatan lampau tidak bisa
     *                           dihidupkan lagi. Tanggal saja tidak cukup:
     *                           admin yang menggeser tanggal gelombang tahun
     *                           lalu ke hari ini akan lolos, dan pendaftarnya
     *                           masuk ke angkatan yang salah - memakai
     *                           batas_pelunasan milik tahun lalu.
     *
     *   hari ini di dalam       supaya jendela yang sudah lewat tidak dibuka
     *   rentang tanggalnya      ulang, dan yang belum mulai tidak mendahului
     *                           jadwal yang sudah dibaca wali di halaman depan.
     *                           Tahun ajaran saja tidak cukup: Gelombang 1 yang
     *                           jendelanya habis ada di tahun ajaran yang sama
     *                           dengan Gelombang 2.
     *
     * Jalan keluarnya SELALU lewat Ubah, dan itu memang selalu tersedia:
     * kuncinya layar Ubah terikat pada status_buka, bukan pada tanggal. Gelombang
     * yang lewat jendelanya pasti dalam keadaan tertutup, jadi pasti boleh
     * diubah. Memperpanjang = mundurkan tanggal_selesai, lalu Buka lagi.
     */
    public function alasanTidakBisaDibuka(): ?string
    {
        $tahunAjaran = $this->tahunAjaran;

        if (! $tahunAjaran?->status_aktif) {
            return "{$this->nama} ada di tahun ajaran {$tahunAjaran?->nama} yang sudah tidak berjalan. "
                .'Jadikan tahun ajaran itu berjalan lebih dulu, atau buat gelombang baru di tahun ajaran yang sekarang.';
        }

        if ($this->belumMulai()) {
            return "Pendaftaran {$this->nama} baru dijadwalkan mulai {$this->tanggalPanjang($this->tanggal_mulai)}. "
                .'Kalau memang mau dibuka lebih awal, majukan dulu tanggal mulainya lewat Ubah - tanggal itu juga yang dibaca wali.';
        }

        if ($this->sudahLewatJendela()) {
            return "Jendela pendaftaran {$this->nama} sudah lewat pada {$this->tanggalPanjang($this->tanggal_selesai)}. "
                .'Kalau mau diperpanjang, mundurkan dulu tanggal selesainya lewat Ubah, baru dibuka lagi.';
        }

        return null;
    }

    public function bisaDibuka(): bool
    {
        return $this->alasanTidakBisaDibuka() === null;
    }

    /**
     * Jendela pendaftaran gelombang ini, dibandingkan PER HARI.
     *
     * Bukan per detik: kolomnya di-cast 'date', jadi tanggal_selesai berisi
     * pukul 00:00 - memakai isPast() akan menutup gelombang sejak pagi di hari
     * terakhirnya, padahal hari itu masih hak pendaftar.
     */
    public function belumMulai(): bool
    {
        return $this->tanggal_mulai !== null && Carbon::today()->lt($this->tanggal_mulai);
    }

    public function sudahLewatJendela(): bool
    {
        return $this->tanggal_selesai !== null && Carbon::today()->gt($this->tanggal_selesai);
    }

    /**
     * Keadaan gelombang ini dalam SATU kata, buat badge di daftar.
     *
     * Dihitung di sini, bukan disimpulkan ulang di TSX dari tiga boolean -
     * kalau layar menyusun sendiri kesimpulannya, cepat atau lambat badge-nya
     * bilang lain dari yang ditegakkan server.
     *
     *   menerima       saklar menyala DAN hari ini di dalam jendelanya
     *   perlu_ditutup  saklar menyala tapi di luar jendelanya - tidak ada yang
     *                  bisa mendaftar, tapi tandanya masih bilang "Dibuka"
     *   siap           tertutup, jendelanya masih berlaku - tinggal dibuka
     *   belum_mulai    tertutup, jendelanya baru datang nanti
     *   berakhir       jendelanya sudah lewat - ARSIP, beku permanen
     *   tahun_lampau   tahun ajarannya sudah tidak berjalan
     */
    public function keadaan(): string
    {
        if ($this->status_buka) {
            return $this->sedangMenerimaPendaftar() ? 'menerima' : 'perlu_ditutup';
        }

        if ($this->sudahLewatJendela()) {
            return 'berakhir';
        }

        if (! $this->tahunAjaran?->status_aktif) {
            return 'tahun_lampau';
        }

        return $this->belumMulai() ? 'belum_mulai' : 'siap';
    }

    /**
     * Kenapa ketentuan gelombang ini tidak boleh diubah - null berarti boleh.
     *
     * DUA kunci, dan urutannya penting karena yang pertama permanen:
     *
     *   1. JENDELANYA SUDAH LEWAT -> gelombang ini ARSIP, beku selamanya.
     *   2. Sedang terbuka          -> terkunci sementara, tinggal ditutup.
     *
     * Kunci pertama soal integritas, bukan kerapian. Pendaftaran lama membaca
     * gelombangnya HIDUP, bukan dari salinan:
     *
     *   batas_waktu_pembayaran -> PendaftaranPpdb::batasMinimalBayar()
     *   kuota                  -> KebijakanKategori::sisa() / penuh()
     *   berkas wajib           -> GelombangPpdb::dokumenWajibUntuk()
     *
     * Jadi mengubah gelombang yang sudah berakhir menggeser jatuh tempo,
     * kuota, dan syarat berkas seluruh pendaftar di dalamnya SECARA SURUT -
     * termasuk yang sudah terlanjur ditolak staf karena melewati tenggat itu.
     * Itu memusnahkan alasan keputusan yang sudah diambil manusia.
     *
     * Akibatnya yang harus diterima: PERPANJANGAN cuma bisa sebelum
     * gelombangnya berakhir. Sesudah lewat, jalannya membuat gelombang baru -
     * dan itu memang lebih benar: pendaftar perpanjangan dapat kuota, tenggat,
     * dan angka laporannya sendiri, tidak dicampur ke angkatan yang sudah
     * selesai dihitung.
     */
    public function alasanTidakBisaDiubah(): ?string
    {
        if ($this->sudahLewatJendela()) {
            return "Jendela pendaftaran {$this->nama} sudah berakhir pada {$this->tanggalPanjang($this->tanggal_selesai)}, "
                .'jadi ketentuannya dikunci permanen - pendaftar di dalamnya sudah memegang tenggat, kuota, dan syarat berkas ini. '
                .'Kalau sekolah mau menerima pendaftar lagi, buat gelombang baru.';
        }

        if ($this->status_buka) {
            return "{$this->nama} sedang menerima pendaftar, jadi ketentuannya tidak bisa diubah. "
                .'Tekan tombol Tutup di daftar Gelombang, ubah ketentuannya, lalu buka lagi.';
        }

        return null;
    }

    public function bisaDiubah(): bool
    {
        return $this->alasanTidakBisaDiubah() === null;
    }

    /**
     * Apakah gelombang ini benar-benar sedang menerima pendaftar.
     *
     * INI yang dipakai gerbang pendaftaran, bukan status_buka mentah. Sejak
     * 11 September 2026 keduanya tidak sama artinya:
     *
     *   status_buka              NIAT sekolah - saklar yang ditekan Admin
     *   tanggal_mulai/selesai    JENDELA-nya - yang tercetak ke halaman wali
     *
     * Pendaftaran terbuka kalau keduanya setuju. Tanpa syarat tanggal, gelombang
     * yang terlanjur terbuka melewati tanggal_selesai akan terus menerima
     * pendaftar SELAMANYA sampai ada orang yang ingat menekan Tutup - dan
     * tanggal penutupan yang sudah diumumkan ke orang tua jadi bohong tanpa ada
     * yang menyentuhnya.
     *
     * Sengaja TIDAK menulis status_buka jadi false sendiri. Menulis butuh
     * penjadwal yang jalan terus dan mengubah data tanpa ada manusia yang
     * menyaksikan; membaca lebih lengkap tidak butuh apa-apa - dua perbandingan
     * pada baris yang toh sudah diambil.
     */
    public function sedangMenerimaPendaftar(): bool
    {
        return $this->status_buka && ! $this->belumMulai() && ! $this->sudahLewatJendela();
    }

    /**
     * Bentuk query dari sedangMenerimaPendaftar() - pakai yang ini kalau sedang
     * MENCARI gelombangnya, bukan memeriksa satu baris yang sudah di tangan.
     *
     * Dipanggil `GelombangPpdb::menerimaPendaftar()`; namanya sengaja beda tipis
     * dari method barisnya supaya PHP tidak bingung mana yang statis.
     */
    public function scopeMenerimaPendaftar(Builder $query): Builder
    {
        $hariIni = Carbon::today()->toDateString();

        return $query->where('status_buka', true)
            ->where('tanggal_mulai', '<=', $hariIni)
            ->where('tanggal_selesai', '>=', $hariIni);
    }

    private function tanggalPanjang(Carbon $tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('d F Y');
    }

    /**
     * Gelombang yang sudah punya pendaftar tidak boleh dipindah tahun ajarannya.
     *
     * Pendaftaran, tagihan, dan tenggatnya menggantung pada gelombang ini;
     * memindahkannya ke tahun ajaran lain memindahkan seluruh angkatan sekaligus
     * dan mengubah `batas_pelunasan` yang berlaku bagi mereka.
     */
    public function bisaPindahTahunAjaran(): bool
    {
        return ! $this->pendaftaran()->exists();
    }
}