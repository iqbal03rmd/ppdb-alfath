<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class GelombangPpdb extends Model
{
    protected $table = 'gelombang_ppdb';

    protected $fillable = [
        'tahun_ajaran_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
        'batas_waktu_pembayaran', 'minimal_pembayaran',
        'status_buka',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'batas_waktu_pembayaran' => 'date',
        'minimal_pembayaran' => 'integer',
        'status_buka' => 'boolean',
    ];

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function kebijakanKategori(): HasMany
    {
        return $this->hasMany(KebijakanKategori::class);
    }

    /**
     * Syarat berkas seluruh jalur pada gelombang ini.
     *
     * Eager-load relasi ini (`gelombang.dokumenWajib`) di halaman yang memanggil
     * PendaftaranPpdb::dokumenWajib() untuk BANYAK baris sekaligus, mis. antrian
     * Verifikasi Pendaftaran. Tanpa itu tiap baris menembak query sendiri.
     */
    public function dokumenWajib(): HasMany
    {
        return $this->hasMany(DokumenWajibKategori::class, 'gelombang_ppdb_id');
    }

    /**
     * Kode berkas yang wajib diunggah pendaftar sebuah jalur DI GELOMBANG INI.
     *
     * Dibaca lewat properti relasi (bukan query builder) supaya kalau relasinya
     * sudah di-eager-load, tidak ada query tambahan per baris.
     *
     * Jenis yang sudah DINONAKTIFKAN di Berkas Persyaratan ikut tersaring,
     * walau barisnya masih ada. Itu yang bikin mematikan sebuah jenis aman:
     * jalur yang terlanjur mewajibkannya berhenti memintanya, tanpa perlu
     * menyisir ulang semua gelombang.
     *
     * @return array<int, string>
     */
    public function dokumenWajibUntuk(int $kategoriSiswaId): array
    {
        $diminta = $this->dokumenWajib
            ->where('kategori_siswa_id', $kategoriSiswaId)
            ->pluck('jenis_dokumen')
            ->all();

        // Urutannya ikut daftar master, bukan urutan baris di database - tanpa
        // ini susunan checklist Unggah Berkas yang dibaca wali bisa
        // berpindah-pindah tanpa sebab.
        return array_values(array_filter(
            array_keys(BerkasPersyaratan::peta()),
            fn (string $jenis) => in_array($jenis, $diminta, true)
        ));
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranPpdb::class);
    }

    /**
     * Nominal seluruh komponen x jalur untuk gelombang ini. Komponennya sendiri
     * global (KomponenBiaya), jadi gelombang cuma memegang angkanya.
     */
    public function tarif(): HasMany
    {
        return $this->hasMany(TarifKategori::class);
    }

    /**
     * Buka pendaftaran di gelombang ini, dan tutup yang lain.
     *
     * `PendaftaranController::gelombangDibuka()` memilih gelombang berstatus
     * buka dengan `latest()->first()`. Kalau ada dua yang terbuka, satu di
     * antaranya tidak akan pernah kebagian pendaftar - dan tidak ada apa pun di
     * layar yang menerangkan kenapa. Jadi "buka" di sini berarti buka yang ini
     * SAJA: keadaan di database dibuat sama dengan yang sebenarnya berlaku.
     *
     * Menutup gelombang TIDAK menyentuh pendaftaran yang sudah ada di dalamnya.
     * Tenggat mereka tetap milik gelombangnya sendiri (jatuhTempoMinimal()),
     * jadi wali Gelombang 1 tetap memegang tanggal Gelombang 1 walau
     * gelombangnya sudah lama ditutup.
     */
    public function buka(): void
    {
        DB::transaction(function () {
            static::where('status_buka', true)
                ->whereKeyNot($this->getKey())
                ->update(['status_buka' => false]);

            $this->update(['status_buka' => true]);
        });
    }

    public function tutup(): void
    {
        $this->update(['status_buka' => false]);
    }

    /**
     * Gelombang yang sudah punya pendaftar tidak boleh dipindah tahun ajarannya.
     *
     * Pendaftaran, tagihan, dan tenggatnya menggantung pada gelombang ini;
     * memindahkannya ke tahun ajaran lain memindahkan seluruh angkatan sekaligus
     * dan mengubah `batas_pelunasan` yang berlaku bagi mereka.
     */
    public function bisaPindahTahunAjaran(): bool
    {
        return ! $this->pendaftaran()->exists();
    }
}