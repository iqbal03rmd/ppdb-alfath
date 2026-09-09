<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kebijakan satu gelombang untuk satu jalur pendaftaran: daya tampung dan
 * minimal bayarnya.
 *
 * Dulu bernama KuotaKategori. Namanya diganti waktu minimal bayar ikut pindah
 * ke sini - keduanya keputusan untuk kombinasi gelombang x kategori yang sama,
 * dan tabel bernama "kuota" yang memuat angka rupiah itu nama yang berbohong.
 */
class KebijakanKategori extends Model
{
    /**
     * Status pendaftaran yang MEMEGANG kursi.
     *
     * Aturannya satu kalimat: kursi dipegang sejak formulir disubmit, sampai
     * pendaftaran ditolak. 'draft' belum submit (belum pegang), 'ditolak' sudah
     * keluar (melepas), selebihnya memegang.
     *
     * Kursi diambil sejak 'diajukan' - bukan menunggu diverifikasi - supaya
     * antrean yang belum sempat diperiksa staf nggak bisa menumpuk melebihi
     * daya tampung, dan urutannya adil: siapa cepat submit, bukan siapa yang
     * berkasnya kebetulan diperiksa lebih dulu.
     *
     * 'perlu_perbaikan' WAJIB ikut memegang kursi. Kalau dilepas, wali yang
     * cuma diminta membetulkan berkas bisa kehilangan kursinya ke orang lain
     * dan terkunci di luar saat mengirim perbaikan - padahal dia nggak salah apa-apa.
     *
     * 'ditolak' melepas kursi kembali. Karena kuota dihitung langsung dari data
     * (bukan disimpan sebagai penghitung), pelepasan itu terjadi sendiri tanpa
     * perlu dibereskan manual.
     */
    public const STATUS_MEMAKAI_KUOTA = ['diajukan', 'perlu_perbaikan', 'diverifikasi', 'diterima'];

    protected $table = 'kebijakan_kategori';

    protected $fillable = ['gelombang_ppdb_id', 'kategori_siswa_id', 'kuota', 'minimal_bayar'];

    protected $casts = [
        'kuota' => 'integer',
        'minimal_bayar' => 'integer',
    ];

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }

    public function terpakai(): int
    {
        return static::terpakaiUntuk($this->gelombang_ppdb_id, $this->kategori_siswa_id);
    }

    /**
     * Kursi terpakai untuk satu kombinasi gelombang+kategori, TANPA perlu baris
     * kebijakannya ada.
     *
     * Perlu berdiri sendiri karena halaman pengaturan harus menampilkan "sudah
     * terpakai" juga buat kategori yang belum punya kebijakan sama sekali - dan
     * itu justru kategori yang paling perlu diisi.
     */
    public static function terpakaiUntuk(int $gelombangId, int $kategoriSiswaId): int
    {
        return PendaftaranPpdb::where('gelombang_ppdb_id', $gelombangId)
            ->where('kategori_siswa_id', $kategoriSiswaId)
            ->whereIn('status', self::STATUS_MEMAKAI_KUOTA)
            ->count();
    }

    /**
     * Sisa kursi. null kalau daya tampungnya tidak dibatasi.
     */
    public function sisa(): ?int
    {
        return $this->kuota === null ? null : max(0, $this->kuota - $this->terpakai());
    }

    public function penuh(): bool
    {
        return $this->kuota !== null && $this->sisa() <= 0;
    }

    /**
     * Kebijakan untuk satu kombinasi gelombang+kategori. null artinya Admin
     * belum menetapkan apa pun untuk jalur itu di gelombang ini.
     */
    public static function untuk(int $gelombangId, int $kategoriSiswaId): ?self
    {
        return static::where('gelombang_ppdb_id', $gelombangId)
            ->where('kategori_siswa_id', $kategoriSiswaId)
            ->first();
    }

    /**
     * Kuota yang belum ditetapkan Admin dianggap TIDAK DIBATASI, jadi false -
     * baik karena barisnya belum ada maupun karena kolom kuotanya null.
     *
     * Bedanya besar dengan "nol": kalau yang belum diisi dianggap nol, seluruh
     * pendaftaran ikut tertutup cuma gara-gara data master belum sempat diisi.
     */
    public static function penuhUntuk(int $gelombangId, int $kategoriSiswaId): bool
    {
        return (bool) static::untuk($gelombangId, $kategoriSiswaId)?->penuh();
    }

    /**
     * Minimal bayar khusus jalur ini di gelombang ini. null = ikut nilai bawaan
     * gelombang (gelombang_ppdb.minimal_pembayaran).
     */
    public static function minimalBayarUntuk(int $gelombangId, int $kategoriSiswaId): ?int
    {
        return static::untuk($gelombangId, $kategoriSiswaId)?->minimal_bayar;
    }
}
