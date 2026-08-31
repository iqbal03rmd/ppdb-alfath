<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanItem extends Model
{
    protected $table = 'tagihan_item';

    protected $fillable = ['pendaftaran_ppdb_id', 'nama_komponen', 'keterangan', 'nominal'];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }
}
