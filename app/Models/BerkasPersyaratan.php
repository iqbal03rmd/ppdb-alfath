<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master jenis berkas yang bisa diminta ke pendaftar.
 *
 * Menggantikan konstanta DokumenPpdb::LABEL (10 September 2026). Yang dulu jadi
 * alasan mengunci daftar ini di kode - "tiap jenis butuh penanganan unggahannya
 * sendiri" - tidak pernah benar: unggahannya satu jalur generik untuk semua
 * jenis, dibedakan cuma oleh kodenya.
 *
 * Yang TIDAK boleh ikut berubah waktu Admin mengubah daftar ini: berkas yang
 * sudah diunggah. dokumen_ppdb menyimpan `kode`-nya sebagai teks, jadi jenis
 * yang dipensiunkan tidak membuat berkas lama menggantung - persis seperti
 * tagihan_item menyimpan nama komponen.
 */
class BerkasPersyaratan extends Model
{
    protected $table = 'berkas_persyaratan';

    protected $fillable = ['kode', 'nama', 'keterangan', 'urutan', 'status_aktif'];

    protected $casts = ['urutan' => 'integer', 'status_aktif' => 'boolean'];

    /**
     * Sama seperti KomponenBiaya: default kolom cuma berlaku di baris database.
     * Objek baru di PHP memegang null sampai dibaca ulang, dan null terbaca
     * NONAKTIF oleh scopeAktif().
     */
    protected $attributes = ['status_aktif' => true];

    /**
     * Peta kode => nama untuk jenis yang masih diminta.
     *
     * Di-cache selama satu request karena dipanggil per baris pendaftaran di
     * halaman daftar (lewat GelombangPpdb::dokumenWajibUntuk()) - tanpa ini satu
     * halaman berisi 50 pendaftaran menembak 50 query yang isinya sama.
     *
     * @var array<string, string>|null
     */
    private static ?array $peta = null;

    /**
     * @return array<string, string>
     */
    public static function peta(): array
    {
        return static::$peta ??= static::aktif()->terurut()->pluck('nama', 'kode')->all();
    }

    public static function lupakanPeta(): void
    {
        static::$peta = null;
    }

    protected static function booted(): void
    {
        // Cache di atas hidup sepanjang PROSES, bukan cuma request - di test
        // satu proses menjalankan banyak kasus berturut-turut. Tanpa ini, kasus
        // yang menambah jenis berkas lalu membaca peta() dapat data basi dari
        // kasus sebelumnya.
        static::saved(fn () => static::lupakanPeta());
        static::deleted(fn () => static::lupakanPeta());
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    public function scopeTerurut($query)
    {
        return $query->orderBy('urutan')->orderBy('nama');
    }
}
