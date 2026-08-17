<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PendaftaranPpdb extends Model
{
    protected $table = 'pendaftaran_ppdb';

    protected $fillable = [
        'user_id',
        'gelombang_ppdb_id',
        'kategori_siswa_id',
        'diverifikasi_oleh',
        'nomor_pendaftaran',
        'nama_pendaftar',
        'nik',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'nama_saudara',
        'nama_orang_tua_guru',
        'status',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function waliMurid(): HasMany
    {
        return $this->hasMany(WaliMurid::class, 'pendaftaran_ppdb_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPpdb::class, 'pendaftaran_ppdb_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranPpdb::class, 'pendaftaran_ppdb_id');
    }

    public function pembayaranTerakhir(): HasOne
    {
        return $this->hasOne(PembayaranPpdb::class, 'pendaftaran_ppdb_id')->latestOfMany();
    }
}