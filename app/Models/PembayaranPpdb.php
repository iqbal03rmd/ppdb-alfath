<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranPpdb extends Model
{
    protected $table = 'pembayaran_ppdb';

    protected $fillable = [
        'pendaftaran_ppdb_id', 'diverifikasi_oleh', 'nominal_transfer',
        'tanggal_transfer', 'bukti_transfer', 'status', 'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_transfer' => 'date',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}