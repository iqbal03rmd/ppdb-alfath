<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPpdb extends Model
{
    protected $table = 'dokumen_ppdb';

    protected $fillable = ['pendaftaran_ppdb_id', 'jenis_dokumen', 'berkas'];

    public function pendaftaranPpdb(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }
}