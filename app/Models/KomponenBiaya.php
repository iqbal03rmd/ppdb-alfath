<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenBiaya extends Model
{
    protected $table = 'komponen_biaya';

    protected $fillable = ['gelombang_ppdb_id', 'nama', 'keterangan'];

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifKategori::class);
    }
}