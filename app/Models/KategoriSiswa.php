<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jalur pendaftaran.
 *
 * TIDAK ADA satu pun nama jalur yang dicocokkan sebagai teks oleh kode, dan itu
 * disengaja. Sebelum 8 September 2026 ada tiga: dokumen wajib, minimal bayar,
 * dan isian pendukung klaim - semuanya membandingkan `nama` dengan string yang
 * ditulis di kode. Cacatnya dua lapis:
 *
 *   1. Mengganti nama jalur mematikan aturannya DIAM-DIAM - tanpa error, tanpa
 *      log. Salah satunya memang sudah rusak begitu: perbandingan mencari
 *      'Anak Guru/Tenaga Kependidikan' padahal jalurnya bernama 'Anak Guru',
 *      jadi isian pendukungnya tidak pernah muncul dan staf memverifikasi klaim
 *      itu tanpa data apa pun.
 *   2. Jalur BARU yang ditambahkan Admin lewat UI tidak akan pernah bisa punya
 *      aturan khususnya sendiri tanpa mengubah kode.
 *
 * Ketiganya sekarang jadi data: tabel `dokumen_wajib_kategori`, kolom
 * `pertanyaan_khusus`, dan `kebijakan_kategori.minimal_bayar`. Nama jalur kembali
 * jadi sekadar label - boleh diganti kapan saja tanpa merusak apa pun.
 */
class KategoriSiswa extends Model
{
    protected $table = 'kategori_siswa';

    protected $fillable = ['nama', 'deskripsi', 'pertanyaan_khusus', 'status_aktif', 'urutan'];

    protected $casts = ['status_aktif' => 'boolean', 'urutan' => 'integer'];

    /**
     * Sama seperti KomponenBiaya dan BerkasPersyaratan: default kolom cuma
     * berlaku di baris database. Objek baru di PHP memegang null sampai dibaca
     * ulang, dan null terbaca NONAKTIF oleh scopeAktif().
     */
    protected $attributes = ['status_aktif' => true];

    /**
     * Jalur yang masih dibuka sekolah.
     *
     * Cuma menyaring PENAWARAN ke pendaftar baru. Pendaftaran yang sudah ada
     * tetap memegang jalurnya, dan namanya tetap terbaca di seluruh layar -
     * jalur ini dirujuk lewat foreign key, bukan disalin.
     */
    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Urutan tampil di daftar pilihan jalur yang dibaca wali. Nama dipakai
     * sebagai pemecah seri supaya dua jalur berurutan sama tidak bertukar tempat
     * tiap kali dibaca.
     */
    public function scopeTerurut($query)
    {
        return $query->orderBy('urutan')->orderBy('nama');
    }

    public function kebijakanKategori(): HasMany
    {
        return $this->hasMany(KebijakanKategori::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifKategori::class);
    }

    /**
     * Label isian pendukung klaim jalur ini, atau null kalau tidak meminta apa pun.
     */
    public function jumlahPendaftaran(): int
    {
        return $this->pendaftaran()->count();
    }
}
