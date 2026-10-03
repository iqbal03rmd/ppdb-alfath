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
     * Pendaftaran yang sudah diajukan memegang kursi sampai ditolak.
     * Sebelum tahap ini, slot dihitung dari reservasi biaya pendaftaran
     * (termasuk draft yang terhubung), bukan dari draft sembarang.
     * Kedua kelompok saling terpisah agar satu anak tidak dihitung dua kali.
     */
    public const STATUS_MEMAKAI_KUOTA = ['diajukan', 'perlu_perbaikan', 'pembayaran', 'diterima'];

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
        $pendaftaran = PendaftaranPpdb::where('gelombang_ppdb_id', $gelombangId)
            ->where('kategori_siswa_id', $kategoriSiswaId)
            ->whereIn('status', self::STATUS_MEMAKAI_KUOTA)
            ->count();

        return $pendaftaran + PembayaranPendaftaranAwal::menahanKursi()
            ->where('gelombang_ppdb_id', $gelombangId)
            ->where('kategori_siswa_id', $kategoriSiswaId)
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
     * Minimal bayar jalur ini di gelombang ini - SATU-SATUNYA sumbernya.
     *
     * null = belum pernah diatur, BUKAN "ikut bawaan": nominal bawaan gelombang
     * sudah dibuang 11 September 2026. Yang memutuskan artinya
     * PendaftaranPpdb::hitungMinimalBayar(), dan di sana null jatuh ke total
     * tagihan - bukan nol.
     */
    public static function minimalBayarUntuk(int $gelombangId, int $kategoriSiswaId): ?int
    {
        return static::untuk($gelombangId, $kategoriSiswaId)?->minimal_bayar;
    }
}
