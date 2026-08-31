<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPpdb extends Model
{
    /**
     * Label yang ditampilkan ke wali per jenis dokumen. SATU-SATUNYA tempat
     * daftar ini didefinisikan - kalau nambah jenis dokumen baru di migration,
     * tambahkan di sini saja.
     */
    public const LABEL = [
        'kartu_keluarga' => 'Kartu Keluarga (KK)',
        'akta' => 'Akta Kelahiran',
        'ktp_orangtua' => 'KTP Orang Tua / Wali',
        'pas_foto' => 'Pas Foto Calon Peserta Didik',
        'surat_kematian_ayah' => 'Surat Kematian Ayah',
        'surat_keterangan_tidak_mampu' => 'Surat Keterangan Tidak Mampu',
    ];

    protected $table = 'dokumen_ppdb';

    protected $fillable = ['pendaftaran_ppdb_id', 'jenis_dokumen', 'berkas'];

    public function label(): string
    {
        return self::LABEL[$this->jenis_dokumen] ?? $this->jenis_dokumen;
    }

    public function pendaftaranPpdb(): BelongsTo
    {
        return $this->belongsTo(PendaftaranPpdb::class, 'pendaftaran_ppdb_id');
    }
}