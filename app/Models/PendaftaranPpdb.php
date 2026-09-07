<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
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

    /**
     * Status yang masih boleh DITUTUP staf ('ditolak').
     *
     * 'draft' tidak masuk: belum pernah disubmit, tidak memegang kursi kuota,
     * jadi tidak ada apa pun yang perlu ditutup. 'diterima' juga tidak - kalau
     * penerimaannya salah, yang dicabut pengesahan transfernya, dan status turun
     * sendiri lewat segarkanStatusPenerimaan().
     */
    public const STATUS_BISA_DITUTUP = ['diajukan', 'perlu_perbaikan', 'diverifikasi'];

    /**
     * Pilihan "tahu PPDB dari mana", beserta labelnya buat wali.
     *
     * Daftarnya sengaja pendek. Makin banyak pilihan, makin tipis sebarannya,
     * dan tidak ada satu pun yang cukup besar buat jadi dasar keputusan. Tiap
     * pilihan di sini mewakili satu saluran promosi yang sekolah betul-betul
     * bisa memutuskan mau ditambah atau dihentikan - kalau sebuah pilihan tidak
     * bisa ditindaklanjuti, dia tidak pantas ada di daftar ini.
     *
     * 'lainnya' yang membuat daftar ini bisa memperbaiki dirinya sendiri: apa
     * pun yang sering diketik wali di tahun_dari_lainnya tahun ini tinggal
     * dinaikkan jadi pilihan tetap tahun depan. Jadi daftarnya tidak perlu
     * ditebak benar sejak awal - dan memang tidak bisa, karena sekolahnya
     * sendiri belum pernah mendata ini.
     */
    public const SUMBER_INFORMASI = [
        'keluarga_teman' => 'Keluarga, teman, atau tetangga',
        'alumni_wali' => 'Alumni atau wali murid Al-Fath',
        'media_sosial' => 'Media sosial',
        'brosur_spanduk' => 'Brosur atau spanduk',
        'guru_paud' => 'Guru TK/RA',
        'acara_sekolah' => 'Acara sekolah',
        'lainnya' => 'Lainnya',
    ];

    protected $table = 'pendaftaran_ppdb';

    protected $fillable = [
        'user_id',
        'gelombang_ppdb_id',
        'kategori_siswa_id',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'nomor_pendaftaran',
        'nama_pendaftar',
        'nik',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'asal_paud_id',
        'asal_paud_lainnya',
        'tanpa_paud',
        'tahu_dari',
        'tahu_dari_lainnya',
        'nama_saudara',
        'nama_orang_tua_guru',
        'status',
        'minimal_bayar',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'diverifikasi_pada' => 'datetime',
        'tanpa_paud' => 'boolean',
        'minimal_bayar' => 'integer',
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

    public function asalPaud(): BelongsTo
    {
        return $this->belongsTo(AsalPaud::class);
    }

    public function waliMurid(): HasMany
    {
        return $this->hasMany(WaliMurid::class, 'pendaftaran_ppdb_id');
    }

    /**
     * Asal PAUD buat laporan. Tiga keadaan yang sengaja dibedakan:
     *
     *   - terdaftar di master  -> namanya, lengkap dengan jenis (TK/RA/...)
     *   - diketik sendiri wali -> teksnya apa adanya
     *   - tidak lewat PAUD     -> kelompok tersendiri, BUKAN "tidak diisi"
     *
     * Yang ketiga penting dibedakan dari yang kosong: "anak ini memang tidak
     * pernah TK" itu jawaban, sedangkan "belum diisi" itu ketiadaan jawaban.
     * Menggabungkannya bikin satu kelompok yang tidak bisa ditafsirkan.
     */
    public function labelAsalPaud(): string
    {
        if ($this->tanpa_paud) {
            return 'Belum/tidak ikut PAUD';
        }

        if ($this->asalPaud !== null) {
            return $this->asalPaud->namaLengkap();
        }

        return $this->asal_paud_lainnya ?: 'Belum diisi';
    }

    public function labelSumberInformasi(): string
    {
        if ($this->tahu_dari === null) {
            return 'Tidak menjawab';
        }

        if ($this->tahu_dari === 'lainnya' && $this->tahu_dari_lainnya) {
            return $this->tahu_dari_lainnya;
        }

        return self::SUMBER_INFORMASI[$this->tahu_dari] ?? $this->tahu_dari;
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

        if ($this->kategoriSiswa->nama === KategoriSiswa::ANAK_YATIM) {
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
     * Boleh ditutup staf? Cuma soal status - tenggat TIDAK ikut membatasi di
     * sini.
     *
     * Sempat dibuat sebaliknya (yang 'diverifikasi' baru boleh ditutup setelah
     * tenggatnya lewat), dan itu keliru: wali yang menyatakan mengundurkan diri
     * di tengah gelombang jadi tidak bisa ditutup sama sekali, dan kursinya
     * tertahan sampai tenggat - persis masalah yang mau dihilangkan.
     *
     * Tenggat cuma membatasi ALASAN 'gagal_minimal_bayar', bukan hak menutupnya.
     * Dua pertanyaan berbeda: "boleh ditutup?" dan "alasan mana yang benar?".
     */
    public function bisaDitutup(): bool
    {
        return in_array($this->status, self::STATUS_BISA_DITUTUP);
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

            $total = 0;

            foreach ($komponen as $k) {
                $nominal = (int) ($k->tarif->first()?->nominal ?? 0);
                $total += $nominal;

                $this->tagihanItem()->create([
                    'nama_komponen' => $k->nama,
                    'keterangan' => $k->keterangan,
                    'nominal' => $nominal,
                ]);
            }

            // Minimal bayar ikut dibekukan di sini, bukan dihitung ulang tiap
            // dibaca - alasannya persis sama dengan snapshot tagihan di atas:
            // kebijakan yang diubah Admin belakangan nggak boleh mengubah
            // kewajiban orang yang tagihannya sudah terbit.
            $this->forceFill(['minimal_bayar' => $this->hitungMinimalBayar($total)])->save();
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
     * Minimal bayar supaya pendaftaran bisa 'diterima'. Dua-duanya diambil dari
     * gelombang, jadi sekali bikin gelombang semua kebijakannya selesai:
     *
     *   - jalur umum  -> minimal_pembayaran (nominal tetap). Reguler/Saudara/
     *     Anak Guru selisih tagihannya tipis, jadi satu angka masih adil.
     *   - Anak Yatim  -> minimal_bayar_persen_yatim, persen dari total tagihannya
     *     sendiri. Jalur ini dibebaskan uang pendaftaran & uang pangkal sehingga
     *     tagihannya jauh lebih kecil; nominal umum mustahil dipenuhi.
     *
     * Hasilnya SELALU dibatasi setinggi-tingginya sebesar total tagihan. Tanpa
     * batas ini, minimal 3jt pada jalur yang tagihannya cuma 925rb bikin jalur
     * itu mustahil diterima - bayar lunas pun masih dianggap kurang.
     */
    private function hitungMinimalBayar(int $totalTagihan): int
    {
        $persenYatim = $this->gelombang->minimal_bayar_persen_yatim;

        $minimal = $this->kategoriSiswa->nama === KategoriSiswa::ANAK_YATIM && $persenYatim !== null
            ? (int) round($totalTagihan * $persenYatim / 100)
            : (int) ($this->gelombang->minimal_pembayaran ?? 0);

        return min(max(0, $minimal), $totalTagihan);
    }

    /**
     * Minimal bayar yang berlaku buat pendaftaran ini - dibaca dari SNAPSHOT,
     * bukan dihitung ulang. Null selama tagihan belum terbit.
     */
    public function minimalBayar(): ?int
    {
        return $this->minimal_bayar;
    }

    /**
     * Syarat penerimaan sudah terpenuhi? Selama tagihan belum terbit, jawabannya
     * selalu tidak - tanpa tagihan nggak ada angka yang bisa dibandingkan, dan
     * membiarkannya lolos berarti pendaftaran tanpa tagihan langsung diterima.
     */
    public function sudahPenuhiMinimal(): bool
    {
        if (! $this->tagihanSudahTerbit() || $this->minimal_bayar === null) {
            return false;
        }

        return $this->totalTerbayar() >= $this->minimal_bayar;
    }

    /**
     * Berapa lagi yang harus disetor supaya diterima. 0 = syaratnya sudah lewat,
     * sisanya (kalau ada) tinggal cicilan yang nggak memicu penolakan.
     */
    public function kurangMinimal(): int
    {
        if ($this->minimal_bayar === null) {
            return 0;
        }

        return max(0, $this->minimal_bayar - $this->totalTerbayar());
    }

    /**
     * Tenggat MINIMAL bayar - milik gelombang tempat pendaftaran ini dibuat,
     * bukan gelombang yang kebetulan sedang dibuka sekarang. Ini yang jadi dasar
     * staf menetapkan 'ditolak'.
     */
    public function batasMinimalBayar(): ?Carbon
    {
        return $this->gelombang->batas_waktu_pembayaran;
    }

    /**
     * Tenggat PELUNASAN sisa cicilan - satu tanggal milik tahun ajaran, berlaku
     * lintas gelombang. Lewat tanggal ini pendaftaran TIDAK ditolak dan kursinya
     * tidak dilepas; penagihannya diteruskan sekolah di luar sistem.
     */
    public function batasPelunasan(): ?Carbon
    {
        return $this->gelombang->tahunAjaran->batas_pelunasan;
    }

    /**
     * SATU-SATUNYA tanggal yang pantas disebut "jatuh tempo" ke wali: batas
     * mencapai minimal bayar. Cuma tanggal ini yang punya akibat - lewat tanpa
     * memenuhi minimal, pendaftaran ditutup dan kursinya lepas.
     *
     * Batas pelunasan sisa cicilan SENGAJA nggak ikut di sini. Dulu satu method
     * mengembalikan dua-duanya bergantian, dan itu keliru: satu label "Jatuh
     * Tempo" jadi memayungi tanggal yang bisa membatalkan pendaftaran DAN
     * tanggal yang nggak berakibat apa-apa. Di kartu ringkasan yang mengambil
     * tanggal paling dekat lintas anak, keduanya bahkan bisa saling menutupi -
     * tenggat cicilan yang tidak genting bisa menyembunyikan tenggat minimal
     * yang genting cuma karena tanggalnya lebih awal.
     *
     * Null berarti tidak ada jatuh tempo: sudah lewat minimal, sudah lunas, atau
     * pendaftarannya ditolak (wali sudah tidak boleh menambah transfer, jadi
     * menagihnya cuma bikin bingung).
     */
    public function jatuhTempoMinimal(): ?Carbon
    {
        if (! $this->bolehBayar() || $this->sisaTagihan() <= 0 || $this->sudahPenuhiMinimal()) {
            return null;
        }

        return $this->batasMinimalBayar();
    }

    /**
     * Tanggal cicilan sisa - keterangan, BUKAN jatuh tempo. Cuma diisi buat
     * pendaftaran yang sudah lewat minimal dan masih punya sisa; selain itu null
     * supaya nggak ada tanggal nganggur di layar.
     */
    public function tanggalPelunasanCicilan(): ?Carbon
    {
        if (! $this->bolehBayar() || $this->sisaTagihan() <= 0 || ! $this->sudahPenuhiMinimal()) {
            return null;
        }

        return $this->batasPelunasan();
    }

    /**
     * Berapa hari wali menggantung antara berkasnya dinyatakan lolos dan
     * transfer PERTAMA-nya masuk. Null kalau salah satu ujungnya belum ada.
     *
     * Diukur dari created_at baris pembayaran, BUKAN tanggal_transfer. Yang
     * kedua diketik sendiri wali dan sering ngawur (salah pilih tanggal, atau
     * sengaja dimundurkan); yang pertama dicatat sistem saat dia betul-betul
     * menekan kirim.
     *
     * Transfer yang belakangan DITOLAK staf tetap dihitung. Yang diukur di sini
     * "kapan wali bergerak", bukan "kapan uangnya sah" - orang yang mengirim
     * bukti di hari kedua lalu buktinya buram tetap orang yang tidak menunda.
     */
    public function hariSampaiTransferPertama(): ?int
    {
        $pertama = $this->pembayaran->sortBy('created_at')->first();

        if ($this->diverifikasi_pada === null || $pertama === null) {
            return null;
        }

        // Tidak pernah negatif: kalau ada data aneh (mis. hasil seed yang
        // urutannya terbalik), yang keluar 0, bukan angka minus yang lalu
        // menyeret rata-ratanya ke bawah tanpa ada yang sadar.
        return max(0, (int) $this->diverifikasi_pada->startOfDay()
            ->diffInDays($pertama->created_at->startOfDay()));
    }

    /**
     * Sudah boleh bayar tapi belum menyetor sepeser pun. Ini kelompok yang
     * paling perlu ditelepon staf - dan di laporan dia sengaja tidak dicampur
     * ke rata-rata jeda, karena jedanya belum selesai berjalan.
     */
    public function belumTransferSamaSekali(): bool
    {
        return $this->diverifikasi_pada !== null && $this->pembayaran->isEmpty();
    }

    /**
     * Berapa kali transfer yang SAH. Dasar buat memisahkan yang membayar
     * sekaligus dari yang mencicil.
     */
    public function jumlahTransferSah(): int
    {
        return $this->pembayaran->where('status', 'terverifikasi')->count();
    }

    /**
     * Tagihannya sudah TUNTAS - bukan sekadar sudah mencapai minimal bayar.
     *
     * Dua hal ini gampang tertukar dan akibatnya jauh berbeda:
     * sudahPenuhiMinimal() menentukan diterima atau tidak, sedangkan yang ini
     * cuma menerangkan apakah masih ada sisa yang dicicil. Wali yang baru
     * membayar minimal SUDAH diterima dan tidak sedang bermasalah - dia hanya
     * belum lunas, dan mencicil memang diperbolehkan sampai batas pelunasan.
     *
     * Syarat "terbayar > 0" bukan basa-basi: tanpa itu, pendaftaran yang
     * tagihannya belum terbit (total 0, terbayar 0) ikut terhitung lunas.
     */
    public function sudahLunas(): bool
    {
        return $this->totalTerbayar() > 0 && $this->sisaTagihan() <= 0;
    }

    /**
     * Menunggak: sudah diterima, tenggat pelunasan lewat, sisa tagihan masih ada.
     */
    public function menunggak(): bool
    {
        $batas = $this->batasPelunasan();

        return $batas !== null
            && $batas->isPast()
            && $this->bolehBayar()
            && $this->sudahPenuhiMinimal()
            && $this->sisaTagihan() > 0;
    }

    /**
     * Hitung ulang status penerimaan dari uang yang sudah terverifikasi.
     *
     * Dipanggil dari DUA arah yang harus simetris: saat staf memverifikasi
     * transfer, DAN saat staf membatalkan verifikasi. Otomatisasi satu arah
     * lebih berbahaya daripada manual - status 'diterima' yang lahir dari salah
     * periksa nggak akan pernah tercabut kalau pembatalannya nggak ikut
     * menurunkan status.
     *
     * Yang TIDAK disentuh:
     *   - 'ditolak' -> ketetapan staf; transfer yang masuk belakangan nggak boleh
     *     diam-diam membatalkan penolakan.
     *   - status sebelum berkas diverifikasi -> belum sampai urusan uang.
     */
    public function segarkanStatusPenerimaan(): void
    {
        if (! in_array($this->status, self::STATUS_BOLEH_BAYAR)) {
            return;
        }

        DB::transaction(function () {
            static::whereKey($this->getKey())->lockForUpdate()->first();

            // Baca ulang di dalam kunci - relasi yang ter-load sebelum transaksi
            // bisa saja sudah basi, dan ini menulis status berdasarkan uang.
            $this->load(['pembayaran', 'tagihanItem', 'kategoriSiswa']);

            $seharusnya = $this->sudahPenuhiMinimal() ? 'diterima' : 'diverifikasi';

            if ($seharusnya !== $this->status) {
                $this->forceFill(['status' => $seharusnya])->save();
            }
        });
    }

    /**
     * Status pelunasan gabungan (BUKAN status satu baris pembayaran_ppdb) -
     * dipakai buat nentuin checklist "Pembayaran" di accordion udah selesai
     * apa belum, dan badge ringkas yang ditampilkan ke wali.
     */
    public function statusPelunasan(): string
    {
        $totalTerbayar = $this->totalTerbayar();

        if ($this->sudahLunas()) {
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