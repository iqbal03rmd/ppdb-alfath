<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPpdb extends Model
{
    protected $table = 'dokumen_ppdb';

    protected $fillable = ['pendaftaran_ppdb_id', 'jenis_dokumen', 'berkas'];

    /**
     * Nama jenis berkas ini menurut daftar master yang berlaku SEKARANG.
     *
     * Jatuh balik ke kodenya kalau jenisnya sudah dipensiunkan Admin. Itu
     * disengaja: berkas yang sudah diunggah tidak boleh hilang dari layar cuma
     * gara-gara jenisnya tidak diminta lagi.
     */
    public function label(): string
    {
        return BerkasPersyaratan::peta()[$this->jenis_dokumen] ?? $this->jenis_dokumen;
    }

    public function pendaftaranPpdb(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }
}