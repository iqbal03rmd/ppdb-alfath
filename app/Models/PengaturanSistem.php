<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris pengaturan global yang dapat dirawat Super Admin.
 *
 * Bentuk kolom tetap sengaja dipilih daripada tabel key-value: aturan validasi,
 * tipe data, dan pemakai setiap nilai jadi terlihat jelas di kode maupun skema.
 */
class PengaturanSistem extends Model
{
    protected $table = 'pengaturan_sistem';

    protected $fillable = [
        'nama_sekolah',
        'tagline',
        'alamat',
        'telepon',
        'email',
        'nama_bank',
        'nomor_rekening',
        'nama_pemilik_rekening',
        'instruksi_pembayaran',
        'judul_landing',
        'deskripsi_landing',
        'pengumuman_landing',
        'whatsapp_kontak',
    ];

    /** @return array<string, string|null> */
    public static function bawaan(): array
    {
        return [
            'nama_sekolah' => 'SD IT AL FATH',
            'tagline' => 'Mendidik Generasi Berilmu, Beriman, dan Berakhlak',
            'alamat' => null,
            'telepon' => null,
            'email' => null,
            'nama_bank' => null,
            'nomor_rekening' => null,
            'nama_pemilik_rekening' => null,
            'instruksi_pembayaran' => null,
            'judul_landing' => 'Penerimaan Peserta Didik Baru',
            'deskripsi_landing' => 'Daftarkan putra-putri Anda secara daring dan pantau seluruh proses PPDB dalam satu tempat.',
            'pengumuman_landing' => null,
            'whatsapp_kontak' => null,
        ];
    }

    /**
     * Pembaca tidak menulis database. Kalau seeder belum dijalankan, halaman
     * publik tetap punya isi aman dari objek sementara dengan nilai bawaan.
     */
    public static function saatIni(): self
    {
        return static::query()->first() ?? new static(static::bawaan());
    }

    /**
     * Jalur penyimpanan memastikan baris singleton tersedia sebelum diubah.
     */
    public static function tersimpan(): self
    {
        return static::query()->firstOrCreate([], static::bawaan());
    }

    public function informasiRekeningLengkap(): bool
    {
        return filled($this->nama_bank)
            && filled($this->nomor_rekening)
            && filled($this->nama_pemilik_rekening);
    }
}
