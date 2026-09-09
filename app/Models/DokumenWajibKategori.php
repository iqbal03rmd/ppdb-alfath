<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu jenis berkas yang wajib diunggah pendaftar sebuah jalur,
 * pada SATU gelombang.
 *
 * Gelombang ikut jadi kunci supaya syarat yang berubah tidak berlaku surut ke
 * angkatan yang sudah lewat - alasan lengkapnya di migration-nya.
 */
class DokumenWajibKategori extends Model
{
    protected $table = 'dokumen_wajib_kategori';

    protected $fillable = ['gelombang_ppdb_id', 'kategori_siswa_id', 'jenis_dokumen'];

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }
}
