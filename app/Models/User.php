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
}