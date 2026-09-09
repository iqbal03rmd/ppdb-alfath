<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pendaftaran_ppdb', function (Blueprint $table) {
            $table->id();
            // Relasi
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('gelombang_ppdb_id')
                ->constrained('gelombang_ppdb')
                ->cascadeOnDelete();
            $table->foreignId('kategori_siswa_id')
                ->constrained('kategori_siswa');
            // Staf yang TERAKHIR memeriksa pendaftaran ini - diisi baik saat
            // menyetujui maupun saat meminta perbaikan, karena dua-duanya sama
            // saja tindakan memeriksa; yang beda cuma hasilnya.
            //
            // Ini BUKAN riwayat: kalau staf A minta perbaikan lalu staf B yang
            // menyetujui sesudah wali memperbaiki, yang tersimpan tinggal B.
            // Riwayat penuh butuh tabel log tersendiri.
            $table->foreignId('diverifikasi_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // KAPAN berkas dinyatakan lolos - diisi HANYA di
            // VerifikasiPendaftaranController::setujui(), bukan saat staf minta
            // perbaikan dan bukan saat menutup pendaftaran. Dua tindakan itu juga
            // "memeriksa", tapi yang diukur di sini satu hal spesifik: sejak kapan
            // wali boleh membayar. Itu titik awal jeda "berapa lama wali
            // menggantung sebelum transfer pertama" di laporan Kepala Sekolah.
            //
            // Sengaja BUKAN diturunkan dari updated_at: kolom itu ikut berubah
            // tiap kali baris disentuh, termasuk saat status naik otomatis jadi
            // 'diterima' gara-gara pembayaran masuk - jadi sering justru lebih
            // baru daripada transfer yang mau diukur.
            //
            // Ditimpa kalau wali sempat diminta perbaikan lalu disetujui lagi.
            // Itu memang yang benar: jam-nya mulai saat pembayaran betul-betul
            // terbuka buat dia, bukan saat percobaan yang gagal.
            $table->timestamp('diverifikasi_pada')->nullable();
            // Nomor pendaftaran
            $table->string('nomor_pendaftaran')->unique();
            // Data calon peserta didik
            $table->string('nama_pendaftar');
            $table->string('nik')->nullable();
            $table->date('tanggal_lahir');
            $table->string('tempat_lahir');
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan']);
            // --- Alamat tempat tinggal ---------------------------------------
            // TEMPAT TINGGAL sekarang, bukan alamat KTP dan bukan tempat_lahir di
            // atas - ketiganya sering berbeda dalam satu keluarga.
            //
            // Seluruhnya teks bebas, TERMASUK kecamatan, dan itu keputusan sadar
            // (user, 7 September 2026): alamat di sini cuma dipakai sekolah untuk
            // surat-menyurat, tidak ada satu pun laporan yang mengagregasinya.
            // Kalau suatu saat muncul kebutuhan "sebaran pendaftar per kecamatan",
            // kolom kecamatan HARUS lebih dulu diubah jadi daftar pilihan - dari
            // teks bebas, satu kecamatan yang sama akan terpecah jadi beberapa
            // ejaan ("Marpoyan Damai", "marpoyan", "Marpoyan Damai ") dan
            // grafiknya tidak akan pernah benar.
            $table->text('alamat');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kota_kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            // --- Data untuk laporan Kepala Sekolah ---------------------------
            // Dua hal di bawah ini tidak dipakai memproses pendaftaran sama
            // sekali. Adanya semata supaya Kepala Sekolah bisa melihat pola yang
            // selama ini tidak terlihat: pendaftar datang dari PAUD mana, dan tahu
            // PPDB ini dari mana. Keduanya berupa PILIHAN, bukan teks bebas,
            // justru karena dua-duanya diagregasi jadi grafik.
            //
            // ASAL PAUD - lihat catatan lengkapnya di migration asal_paud.
            $table->foreignId('asal_paud_id')->nullable()->constrained('asal_paud')->nullOnDelete();
            // Sekolah asal yang belum ada di daftar master. Sengaja disimpan
            // terpisah, TIDAK ditulis balik jadi baris master, supaya satu typo
            // tidak langsung memecah hitungan jadi dua "sekolah".
            $table->string('asal_paud_lainnya')->nullable();
            // Banyak anak masuk SD tanpa lewat PAUD sama sekali. Kalau pilihan
            // ini tidak disediakan, mereka akan asal pilih dan meracuni datanya.
            $table->boolean('tanpa_paud')->default(false);
            // TAHU PPDB DARI MANA - opsional, dan itu disengaja. Ini satu-satunya
            // isian yang tidak ada gunanya buat wali; kalau diwajibkan, orang asal
            // pilih supaya bisa lanjut, dan jawaban asal justru lebih merusak
            // daripada tidak ada jawaban. Daftar pilihannya di
            // PendaftaranPpdb::SUMBER_INFORMASI.
            $table->string('tahu_dari')->nullable();
            // Isian bebas untuk pilihan 'lainnya'. Ini yang membuat daftar
            // pilihannya bisa belajar sendiri: apa pun yang sering muncul di sini
            // tahun ini tinggal dinaikkan jadi pilihan tetap tahun depan.
            $table->string('tahu_dari_lainnya')->nullable();
            // Jawaban atas pertanyaan khusus jalur, beserta PERTANYAANNYA.
            //
            // Pertanyaannya ikut disalin ke sini, bukan dibaca ulang dari
            // kategori_siswa waktu ditampilkan - alasannya sama dengan
            // tagihan_item menyimpan nama komponen sebagai teks. Admin boleh
            // mengubah pertanyaan jalur kapan saja; kalau jawabannya cuma
            // menunjuk ke pertanyaan yang berlaku sekarang, jawaban lama
            // berubah arti tanpa ada yang menyentuhnya. "Kakak Budi" di bawah
            // pertanyaan "NIS saudara" bukan sekadar jelek - itu salah, dan
            // staf memverifikasinya sebagai kebenaran.
            //
            // Dulu dua kolom tetap: nama_saudara dan nama_orang_tua_guru. Itu
            // yang bikin pertanyaan baru mustahil tanpa migration.
            $table->string('pertanyaan_khusus')->nullable();
            $table->string('jawaban_khusus')->nullable();
            // Status pendaftaran
            $table->enum('status', [
                'draft',
                'diajukan',
                'diverifikasi',
                'perlu_perbaikan',
                'diterima',
                'ditolak',
            ])->default('draft');
            // Minimal bayar yang DIBEKUKAN buat pendaftaran ini, dihitung sekali
            // bersamaan dengan penerbitan tagihan_item. Alasannya sama dengan
            // snapshot tagihan: kebijakan yang diubah Admin belakangan nggak
            // boleh mengubah kewajiban orang yang tagihannya sudah terbit.
            // Null = tagihan belum terbit.
            $table->unsignedBigInteger('minimal_bayar')->nullable();
            // Catatan dari staf PPDB - dipakai untuk permintaan perbaikan DAN
            // alasan penutupan pendaftaran. Keduanya kalimat bebas yang dibaca
            // wali, jadi satu kolom saja.
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendaftaran_ppdb');
    }
};
