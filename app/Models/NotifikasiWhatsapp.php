<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiWhatsapp extends Model
{
    protected $table = 'notifikasi_whatsapp';

    protected $fillable = [
        'user_id',
        'pendaftaran_ppdb_id',
        'jenis',
        'idempotency_key',
        'nomor_tujuan',
        'pesan',
        'status',
        'provider_message_id',
        'provider_request_id',
        'jumlah_percobaan',
        'keterangan_status',
        'diterima_gateway_pada',
        'gagal_pada',
    ];

    protected function casts(): array
    {
        return [
            'diterima_gateway_pada' => 'datetime',
            'gagal_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }
}
