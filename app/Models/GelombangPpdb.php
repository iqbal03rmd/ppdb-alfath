<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GelombangPpdb extends Model
{
    protected $table = 'gelombang_ppdb';

    protected $fillable = [
        'tahun_ajaran_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
        'batas_waktu_pembayaran', 'minimal_pembayaran', 'minimal_bayar_persen_yatim',
        'status_buka',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'batas_waktu_pembayaran' => 'date',
        'minimal_pembayaran' => 'integer',
        'minimal_bayar_persen_yatim' => 'integer',
        'status_buka' => 'boolean',
    ];

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function kuotaKategori(): HasMany
    {
        return $this->hasMany(KuotaKategori::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    public function komponenBiaya(): HasMany
    {
        return $this->hasMany(KomponenBiaya::class);
    }
}