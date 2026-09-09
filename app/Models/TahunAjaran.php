<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Jadikan tahun ajaran ini satu-satunya yang aktif.
     *
     * Seluruh sistem membaca tahun ajaran aktif lewat
     * `where('status_aktif', true)->first()` - Beranda Kepala Sekolah, penyaring
     * awal Semua Pendaftaran, dan rekapitulasi. `first()` berarti kalau ada dua
     * yang aktif, yang terpakai tinggal yang kebetulan lebih dulu terbaca, dan
     * angka laporan berubah-ubah tanpa sebab yang kelihatan.
     *
     * Karena itu mengaktifkan satu WAJIB mematikan yang lain, dan dua-duanya
     * dalam satu transaksi - kalau tidak, ada jeda saat nol tahun ajaran aktif
     * (laporan kosong) atau dua-duanya aktif sekaligus.
     */
    public function aktifkan(): void
    {
        DB::transaction(function () {
            static::where('status_aktif', true)
                ->whereKeyNot($this->getKey())
                ->update(['status_aktif' => false]);

            $this->update(['status_aktif' => true]);
        });
    }

    /**
     * Jumlah pendaftaran di seluruh gelombang tahun ajaran ini.
     *
     * Dipakai buat mengunci hal-hal yang tidak boleh diubah lagi begitu ada
     * orang di dalamnya - lihat komentar di SuperAdmin\TahunAjaranController.
     */
    public function jumlahPendaftaran(): int
    {
        return PendaftaranPpdb::whereIn('gelombang_ppdb_id', $this->gelombang()->select('id'))->count();
    }
}