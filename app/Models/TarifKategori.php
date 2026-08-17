<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifKategori extends Model
{
    protected $table = 'tarif_kategori';

    protected $fillable = ['komponen_biaya_id', 'kategori_siswa_id', 'nominal'];

    public function komponenBiaya(): BelongsTo
    {
        return $this->belongsTo(KomponenBiaya::class);
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }
}