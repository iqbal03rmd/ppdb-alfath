<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuotaKategori extends Model
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

    protected $table = 'kuota_kategori';

    protected $fillable = ['gelombang_ppdb_id', 'kategori_siswa_id', 'kuota'];

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
        return PendaftaranPpdb::where('gelombang_ppdb_id', $this->gelombang_ppdb_id)
            ->where('kategori_siswa_id', $this->kategori_siswa_id)
            ->whereIn('status', self::STATUS_MEMAKAI_KUOTA)
            ->count();
    }

    public function sisa(): int
    {
        return max(0, $this->kuota - $this->terpakai());
    }

    public function penuh(): bool
    {
        return $this->sisa() <= 0;
    }

    /**
     * Kuota untuk satu kombinasi gelombang+kategori. null artinya Admin belum
     * menetapkan kuota - diperlakukan sebagai "tidak dibatasi", bukan "nol",
     * biar pendaftaran nggak ikut tertutup gara-gara data master belum diisi.
     */
    public static function untuk(int $gelombangId, int $kategoriSiswaId): ?self
    {
        return static::where('gelombang_ppdb_id', $gelombangId)
            ->where('kategori_siswa_id', $kategoriSiswaId)
            ->first();
    }

    /**
     * Kuota yang belum ditetapkan Admin dianggap tidak dibatasi, jadi false.
     */
    public static function penuhUntuk(int $gelombangId, int $kategoriSiswaId): bool
    {
        return (bool) static::untuk($gelombangId, $kategoriSiswaId)?->penuh();
    }
}