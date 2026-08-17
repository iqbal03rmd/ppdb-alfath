<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaliMurid extends Model
{
    protected $table = 'wali_murid';
 
    protected $fillable = ['pendaftaran_ppdb_id', 'nama', 'nik', 'hubungan', 'telepon'];
 
    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }
}
