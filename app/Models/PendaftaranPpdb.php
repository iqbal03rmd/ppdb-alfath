<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PendaftaranPpdb extends Model
{
    /**
     * Formulir & berkas cuma boleh diubah selama masih di dua status ini.
     */
    public const STATUS_BISA_DIEDIT = ['draft', 'perlu_perbaikan'];

    /**
     * Pembayaran baru boleh diakses setelah berkas diverifikasi staf, biar nggak
     * ada duit "nyangkut" buat pendaftaran yang ternyata perlu diperbaiki.
     *
     * 'ditolak' SENGAJA nggak masuk - status itu dipakai staf buat menutup
     * pendaftaran yang nggak dibayar sampai batas waktu. Kalau yang ditolak itu
     * bukti transfernya (bukan pendaftarannya), status pendaftaran tetap
     * 'diverifikasi' dan ditangani lewat pembayaran.status, bukan di sini.
     */
    public const STATUS_BOLEH_BAYAR = ['diverifikasi', 'diterima'];

    /**
     * Boleh MELIHAT tagihan & riwayat transfer - lebih longgar daripada
     * STATUS_BOLEH_BAYAR karena 'ditolak' ikut masuk. Wali yang pendaftarannya
     * ditolak setelah terlanjur menyetor uang tetap harus bisa melihat catatan
     * pembayarannya sendiri (buat menanyakan sisa/refund ke sekolah); yang
     * dicabut cuma hak menambah transfer baru, bukan hak melihat.
     */
    public const STATUS_BOLEH_LIHAT_TAGIHAN = ['diverifikasi', 'diterima', 'ditolak'];

    /**
     * Dokumen wajib dasar untuk semua kategori. Kategori tertentu menambah
     * dokumen khusus - lihat dokumenWajib().
     */
    private const DOKUMEN_WAJIB_DASAR = ['kartu_keluarga', 'akta', 'ktp_orangtua', 'pas_foto'];

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

    public function tagihanItem(): HasMany
    {
        return $this->hasMany(TagihanItem::class, 'pendaftaran_ppdb_id');
    }

    /**
     * Dokumen wajib untuk pendaftaran INI - dasar, ditambah dokumen khusus
     * sesuai kategori yang diklaim (mis. Anak Yatim butuh surat kematian ayah).
     * Satu-satunya tempat aturan ini didefinisikan; dipakai halaman Unggah
     * Berkas, checklist progres, dan validasi sebelum kirim/kirim perbaikan.
     */
    public function dokumenWajib(): array
    {
        $wajib = self::DOKUMEN_WAJIB_DASAR;

        if ($this->kategoriSiswa->nama === 'Anak Yatim') {
            $wajib[] = 'surat_kematian_ayah';
        }

        return $wajib;
    }

    /**
     * Jenis dokumen wajib yang belum diunggah. Kosong = berkas sudah lengkap.
     */
    public function dokumenKurang(): array
    {
        return array_values(array_diff($this->dokumenWajib(), $this->dokumen->pluck('jenis_dokumen')->all()));
    }

    public function berkasLengkap(): bool
    {
        return $this->dokumenKurang() === [];
    }

    public function bisaDiedit(): bool
    {
        return in_array($this->status, self::STATUS_BISA_DIEDIT);
    }

    public function bolehBayar(): bool
    {
        return in_array($this->status, self::STATUS_BOLEH_BAYAR);
    }

    public function bolehLihatTagihan(): bool
    {
        return in_array($this->status, self::STATUS_BOLEH_LIHAT_TAGIHAN);
    }

    /**
     * Terbitkan tagihan: SALIN komponen_biaya + tarif_kategori yang berlaku
     * saat ini jadi baris tagihan_item yang beku. Setelah ini, perubahan tarif
     * oleh Admin tidak lagi mengubah tagihan pendaftaran ini.
     *
     * Idempotent - aman dipanggil berkali-kali, cuma menerbitkan sekali.
     * Sekarang dipanggil lazy dari PembayaranController (saat wali pertama kali
     * melihat tagihannya). Nanti kalau modul Staf jadi, panggil method yang sama
     * di titik verifikasi berkas biar tagihan terbit lebih awal - nggak perlu
     * ubah apa pun di sini karena idempotent.
     */
    public function terbitkanTagihan(): void
    {
        if ($this->tagihanItem()->exists()) {
            return;
        }

        $komponen = KomponenBiaya::with(['tarif' => fn ($q) => $q->where('kategori_siswa_id', $this->kategori_siswa_id)])
            ->where('gelombang_ppdb_id', $this->gelombang_ppdb_id)
            ->get();

        // Admin belum bikin komponen biaya buat gelombang ini - JANGAN terbitkan
        // tagihan kosong, nanti kebekukan di Rp0 selamanya walau tarifnya
        // di-setting belakangan. Biarkan belum terbit sampai datanya siap.
        if ($komponen->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($komponen) {
            // Kunci baris pendaftaran biar dua request bersamaan nggak dua-duanya
            // lolos cek exists() di atas dan menerbitkan tagihan dobel.
            static::whereKey($this->getKey())->lockForUpdate()->first();

            if ($this->tagihanItem()->exists()) {
                return;
            }

            foreach ($komponen as $k) {
                $this->tagihanItem()->create([
                    'nama_komponen' => $k->nama,
                    'keterangan' => $k->keterangan,
                    'nominal' => $k->tarif->first()?->nominal ?? 0,
                ]);
            }
        });

        // Relasi yang mungkin sudah ter-load sebelum penerbitan jadi basi -
        // buang biar pembacaan berikutnya ambil data yang baru.
        $this->unsetRelation('tagihanItem');
    }

    /**
     * Total tagihan dibaca dari SNAPSHOT (tagihan_item), bukan dihitung ulang
     * dari master tarif - lihat terbitkanTagihan(). 0 kalau tagihan belum terbit.
     *
     * Sengaja baca lewat properti relasi ($this->tagihanItem), bukan query
     * builder - biar kalau relasinya sudah di-eager-load (mis. daftar
     * pendaftaran), penjumlahannya dilakukan di PHP tanpa query tambahan
     * per baris. Di jalur yang butuh data terkini (mis. di dalam transaksi
     * pembayaran), controller memanggil load() dulu supaya nggak baca yang basi.
     */
    public function totalTagihan(): int
    {
        return (int) $this->tagihanItem->sum('nominal');
    }

    public function tagihanSudahTerbit(): bool
    {
        return $this->tagihanItem->isNotEmpty();
    }

    /**
     * Jumlah yang udah kebayar - cuma yang statusnya terverifikasi yang dihitung.
     * Bisa lebih dari satu baris pembayaran_ppdb (cicilan).
     */
    public function totalTerbayar(): int
    {
        return (int) $this->pembayaran->where('status', 'terverifikasi')->sum('nominal_transfer');
    }

    public function adaPembayaranPending(): bool
    {
        return $this->pembayaran->contains('status', 'menunggu_verifikasi');
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

        if ($this->adaPembayaranPending()) {
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