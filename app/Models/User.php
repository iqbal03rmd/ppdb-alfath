<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telepon',
        'status_aktif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Akun HIDUP sampai ada yang sengaja mematikannya.
     *
     * Nilai bawaannya sebetulnya sudah ada di migration, tapi default kolom cuma
     * berlaku di baris databasenya - objek User yang baru dibuat di PHP tetap
     * memegang null sampai dibaca ulang. Sejak PastikanAkunAktif menendang
     * keluar siapa pun yang status_aktif-nya tidak benar, null itu berarti akun
     * yang baru saja dibuat bisa langsung terkunci di luar.
     *
     * Ditaruh di sini, bukan di factory atau seeder saja, supaya jalur mana pun
     * yang membuat user - termasuk yang ditulis belakangan - ikut aman.
     */
    protected $attributes = [
        'status_aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status_aktif' => 'boolean',
        ];
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    public function pendaftaranDiverifikasi(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class, 'diverifikasi_oleh');
    }

    public function pembayaranDiverifikasi(): HasMany
    {
        return $this->hasMany(PembayaranPpdb::class, 'diverifikasi_oleh');
    }

    public function isWaliMurid(): bool
    {
        return $this->role === 'wali_murid';
    }

    public function isStafPpdb(): bool
    {
        return $this->role === 'staf_ppdb';
    }

    public function isKepalaSekolah(): bool
    {
        return $this->role === 'kepala_sekolah';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function homeRouteName(): string
    {
        return match ($this->role) {
            'wali_murid' => 'wali-murid.dashboard',
            'staf_ppdb' => 'staf-ppdb.dashboard',
            'kepala_sekolah' => 'kepala-sekolah.dashboard',
            'super_admin' => 'super-admin.dashboard',
            default => throw new \RuntimeException(
                'Role pengguna tidak dikenali: '.($this->role ?? 'NULL').'. Cek data user di database.'
            ),
        };
    }
}