<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsalPaud extends Model
{
    /**
     * Jenis satuan PAUD, beserta kepanjangannya buat ditampilkan ke wali -
     * singkatannya saja tidak semua orang tua tahu.
     *
     * RA berdiri sendiri dan itu bukan kelengkapan formalitas: RA didata Kemenag
     * lewat EMIS sementara sisanya lewat Dapodik, jadi kalau jenis ini tidak
     * dibedakan, tidak akan pernah ketahuan berapa besar sebetulnya jalur
     * madrasah menyuplai murid ke sekolah ini.
     */
    public const JENIS = [
        'TK' => 'TK (Taman Kanak-kanak)',
        'RA' => 'RA (Raudhatul Athfal)',
        'KB' => 'KB (Kelompok Bermain)',
        'TPA' => 'TPA (Taman Penitipan Anak)',
        'SPS' => 'SPS (Satuan PAUD Sejenis)',
    ];

    protected $table = 'asal_paud';

    protected $fillable = ['nama', 'jenis', 'npsn', 'kecamatan'];

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    /**
     * Nama untuk LAPORAN: jenis, nama, dan kecamatannya.
     *
     * Kecamatan ikut karena nama sekolah tidak unik. Di Pekanbaru ada empat
     * pasang yang persis kembar - TK NURUL IMAN berdiri di Bukit Raya DAN di
     * Tenayan Raya, begitu juga TK AMAL IKHLAS, TK BAITURRAHMAN, dan TK ISLAM
     * ASY SYAKIRIN. Tanpa kecamatan, dua sekolah berbeda muncul sebagai dua
     * batang yang tulisannya sama persis di grafik, dan tidak ada cara membaca
     * mana yang mana.
     */
    public function namaLengkap(): string
    {
        return $this->kecamatan
            ? "{$this->jenis} {$this->nama} · {$this->kecamatan}"
            : "{$this->jenis} {$this->nama}";
    }

    /**
     * Nama untuk DAFTAR PILIHAN di formulir. Sama seperti di atas tapi tanpa
     * jenis - di formulir daftarnya sudah dipecah <optgroup> per jenis, jadi
     * menyebutnya lagi cuma bikin tiap baris diawali huruf yang sama dan
     * mengetik untuk melompat jadi tidak berguna.
     */
    public function namaDenganKecamatan(): string
    {
        return $this->kecamatan ? "{$this->nama} · {$this->kecamatan}" : $this->nama;
    }
}
