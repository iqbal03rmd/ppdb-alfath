<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriSiswa extends Model
{
    /**
     * Jalur yang diperlakukan khusus di dua tempat: butuh surat kematian ayah
     * (PendaftaranPpdb::dokumenWajib()) dan minimal bayarnya dihitung persentase
     * (gelombang_ppdb.minimal_bayar_persen_yatim). Namanya dikunci di sini biar
     * dua aturan itu nggak pernah memakai ejaan yang beda.
     */
    public const ANAK_YATIM = 'Anak Yatim';

    protected $table = 'kategori_siswa';

    protected $fillable = ['nama', 'deskripsi'];

    public function kuotaKategori(): HasMany
    {
        return $this->hasMany(KuotaKategori::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifKategori::class);
    }
}