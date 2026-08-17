<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriSiswa extends Model
{
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