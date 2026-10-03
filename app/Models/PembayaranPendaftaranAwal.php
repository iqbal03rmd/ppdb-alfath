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
        'kategori_siswa_id',
        'reservasi_berakhir_pada',
        'pendaftaran_ppdb_id',
        'diverifikasi_oleh',
        'nominal_tagihan',
        'nominal_transfer',
        'tanggal_transfer',
        'bukti_transfer',
        'bukti_dikirim_pada',
        'status',
        'catatan_verifikasi',
        'diverifikasi_pada',
        'digunakan_pada',
    ];

    protected $casts = [
        'kategori_siswa_id' => 'integer',
        'reservasi_berakhir_pada' => 'datetime',
        'nominal_tagihan' => 'integer',
        'nominal_transfer' => 'integer',
        'tanggal_transfer' => 'date',
        'bukti_dikirim_pada' => 'datetime',
        'diverifikasi_pada' => 'datetime',
        'digunakan_pada' => 'datetime',
    ];

    public function wali(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }

    /** Bukti yang sudah dikirim tidak kedaluwarsa selama menunggu staf/perbaikan. */
    public function scopeDapatDilanjutkan(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', ['menunggu_verifikasi', 'terverifikasi'])
                ->orWhere(fn (Builder $q) => $q->where('status', 'ditolak')->whereNotNull('reservasi_berakhir_pada'))
                ->orWhere(fn (Builder $q) => $q->where('status', 'menunggu_pembayaran')
                    ->where('reservasi_berakhir_pada', '>', now()));
        });
    }

    /** Hitung reservasi saja; pendaftaran yang sudah diajukan dihitung terpisah. */
    public function scopeMenahanKursi(Builder $query): Builder
    {
        return $query->whereNotNull('kategori_siswa_id')
            ->whereNotNull('reservasi_berakhir_pada')
            ->whereHas('gelombang', fn (Builder $q) => $q->menerimaPendaftar())
            ->dapatDilanjutkan()
            ->where(fn (Builder $q) => $q->where(fn (Builder $q) => $q->belumDigunakan())
                ->orWhereHas('pendaftaran', fn (Builder $q) => $q->where('status', 'draft')));
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
