<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PendaftaranPpdb extends Model
{
    protected $table = 'pendaftaran_ppdb';

    protected $fillable = [
        'user_id',
        'gelombang_ppdb_id',
        'kategori_siswa_id',
        'diverifikasi_oleh',
        'nomor_pendaftaran',
        'nama_pendaftar',
        'nik',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'nama_saudara',
        'nama_orang_tua_guru',
        'status',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function kategoriSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriSiswa::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function waliMurid(): HasMany
    {
        return $this->hasMany(WaliMurid::class, 'pendaftaran_ppdb_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPpdb::class, 'pendaftaran_ppdb_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranPpdb::class, 'pendaftaran_ppdb_id');
    }

    public function pembayaranTerakhir(): HasOne
    {
        return $this->hasOne(PembayaranPpdb::class, 'pendaftaran_ppdb_id')->latestOfMany();
    }

    /**
     * Total tagihan = jumlah komponen_biaya untuk gelombang ini, masing-masing
     * dinilai sesuai tarif_kategori punya kategori_siswa pendaftaran ini.
     * Rp0 per komponen kalau Admin belum setting tarifnya.
     */
    public function totalTagihan(): int
    {
        return KomponenBiaya::with(['tarif' => fn ($q) => $q->where('kategori_siswa_id', $this->kategori_siswa_id)])
            ->where('gelombang_ppdb_id', $this->gelombang_ppdb_id)
            ->get()
            ->sum(fn (KomponenBiaya $k) => $k->tarif->first()?->nominal ?? 0);
    }

    /**
     * Jumlah yang udah kebayar - cuma yang statusnya terverifikasi yang dihitung.
     * Bisa lebih dari satu baris pembayaran_ppdb (cicilan).
     */
    public function totalTerbayar(): int
    {
        return (int) $this->pembayaran()->where('status', 'terverifikasi')->sum('nominal_transfer');
    }

    public function sisaTagihan(): int
    {
        return max(0, $this->totalTagihan() - $this->totalTerbayar());
    }

    /**
     * Status pelunasan gabungan (BUKAN status satu baris pembayaran_ppdb) -
     * dipakai buat nentuin checklist "Pembayaran" di accordion udah selesai
     * apa belum, dan badge ringkas yang ditampilkan ke wali.
     */
    public function statusPelunasan(): string
    {
        $totalTerbayar = $this->totalTerbayar();

        if ($totalTerbayar > 0 && $this->sisaTagihan() <= 0) {
            return 'lunas';
        }

        if ($this->pembayaran()->where('status', 'menunggu_verifikasi')->exists()) {
            return 'menunggu_verifikasi';
        }

        if ($totalTerbayar > 0) {
            return 'dicicil';
        }

        if ($this->pembayaranTerakhir?->status === 'ditolak') {
            return 'ditolak';
        }

        return 'belum_bayar';
    }
}