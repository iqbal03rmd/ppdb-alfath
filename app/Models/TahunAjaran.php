<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAjaran extends Model
{
    protected $table = 'tahun_ajaran';

    protected $fillable = ['nama', 'status_aktif', 'tahun_mulai', 'batas_pelunasan'];

    protected $casts = [
        'status_aktif' => 'boolean',
        'batas_pelunasan' => 'date',
    ];

    public function gelombang(): HasMany
    {
        return $this->hasMany(GelombangPpdb::class);
    }
}