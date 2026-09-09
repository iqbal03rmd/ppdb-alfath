<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pos biaya PPDB - daftar global, dipakai semua gelombang.
 *
 * Komponennya sama tiap tahun ("Pembangunan" tetap Pembangunan); yang berubah
 * cuma nominalnya, dan itu tinggal di TarifKategori per gelombang x jalur.
 */
class KomponenBiaya extends Model
{
    protected $table = 'komponen_biaya';

    protected $fillable = ['nama', 'keterangan', 'urutan', 'status_aktif'];

    protected $casts = ['urutan' => 'integer', 'status_aktif' => 'boolean'];

    /**
     * Default kolom di migration cuma berlaku di baris database. Objek
     * KomponenBiaya yang baru dibuat di PHP memegang null sampai dibaca ulang -
     * dan null terbaca sebagai NONAKTIF oleh scopeAktif() di bawah.
     */
    protected $attributes = ['status_aktif' => true];

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifKategori::class);
    }

    /**
     * Pos yang masih ditagih sekolah.
     *
     * Dipakai di DUA tempat, dan dua-duanya perlu: penerbitan tagihan
     * (PendaftaranPpdb::terbitkanTagihan()) supaya pos mati berhenti ikut ke
     * tagihan baru, dan layar pengisian tarif supaya admin tidak mengisi harga
     * buat pos yang tidak akan ditagihkan.
     *
     * Yang TIDAK boleh ikut disaring: tagihan yang sudah terbit. Rinciannya
     * tinggal di tagihan_item sebagai salinan teks, jadi memang tidak
     * tersentuh - dan itu yang bikin mematikan pos aman buat data lama.
     */
    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Urutan tampil di rincian tagihan. Nama dipakai sebagai pemecah seri supaya
     * dua komponen berurutan sama tidak bertukar tempat tiap kali dibaca.
     */
    public function scopeTerurut($query)
    {
        return $query->orderBy('urutan')->orderBy('nama');
    }
}
