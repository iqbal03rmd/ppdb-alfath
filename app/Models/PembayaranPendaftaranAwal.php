<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranPendaftaranAwal extends Model
{
    protected $table = 'pembayaran_pendaftaran_awal';

    protected $fillable = [
        'user_id',
        'gelombang_ppdb_id',
        'pendaftaran_ppdb_id',
        'diverifikasi_oleh',
        'nominal_tagihan',
        'nominal_transfer',
        'tanggal_transfer',
        'bukti_transfer',
        'status',
        'catatan_verifikasi',
        'diverifikasi_pada',
        'digunakan_pada',
    ];

    protected $casts = [
        'nominal_tagihan' => 'integer',
        'nominal_transfer' => 'integer',
        'tanggal_transfer' => 'date',
        'diverifikasi_pada' => 'datetime',
        'digunakan_pada' => 'datetime',
    ];

    public function wali(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function scopeBelumDigunakan(Builder $query): Builder
    {
        return $query->whereNull('pendaftaran_ppdb_id')->whereNull('digunakan_pada');
    }

    public function scopeTerverifikasi(Builder $query): Builder
    {
        return $query->where('status', 'terverifikasi');
    }
}
