<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nominal satu komponen biaya untuk satu jalur di satu gelombang.
 *
 * Nominal 0 SAH - artinya jalur itu dibebaskan dari pos ini. Yang berarti
 * "belum diatur" adalah barisnya yang tidak ada.
 */
class TarifKategori extends Model
{
    protected $table = 'tarif_kategori';

    protected $fillable = ['gelombang_ppdb_id', 'komponen_biaya_id', 'kategori_siswa_id', 'nominal'];

    protected $casts = ['nominal' => 'integer'];

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function komponenBiaya(): BelongsTo
    {
        return $this->belongsTo(KomponenBiaya::class);
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }
}
