<?php

namespace Database\Seeders;

use App\Models\AsalPaud;
use Illuminate\Database\Seeder;

/**
 * Daftar satuan PAUD se-Kota Pekanbaru - 634 sekolah, DATA SUNGGUHAN.
 *
 * Sumbernya Data Referensi Kemendikdasmen (Dapodik):
 * https://referensi.data.kemendikdasmen.go.id/pendidikan/paud/096000/2
 * Kode wilayahnya kode Kemdikbud, bukan Kemendagri - Kota Pekanbaru '096000',
 * lalu tiap kecamatan punya kodenya sendiri di tingkat berikutnya.
 *
 * Portal itu bukan API, jadi daftarnya diambil sekali lalu DIBEKUKAN ke
 * database/data/paud-pekanbaru.csv. Konsekuensinya harus disadari: data ini
 * tidak ikut memperbarui diri. Kalau ada PAUD baru berdiri, dia belum ada di
 * daftar sampai berkas CSV-nya diperbarui - dan sementara itu wali yang anaknya
 * dari sana memakai isian "ketik sendiri", yang memang disediakan untuk itu.
 *
 * CSV-nya sengaja berkas terpisah, bukan array di dalam kode: kalau nanti orang
 * sekolah mau membetulkan ejaan atau menambah satu baris, mereka tidak perlu
 * menyentuh PHP sama sekali.
 *
 * Catatan penggolongan, kalau suatu saat CSV-nya ditarik ulang:
 *
 *   - Jenis diambil dari AWALAN nama di Dapodik. Yang perlu diperhatikan,
 *     35 sekolah tertulis berawalan 'RA/BA/TA' dan 4 lagi 'RA.' - kalau
 *     ketiganya tidak dipetakan ke RA, jumlah RA anjlok dari 92 jadi 53 dan
 *     selisihnya diam-diam masuk ke SPS. Untuk SD Islam Terpadu, salah golong
 *     seperti itu justru mengaburkan temuan yang paling ingin dilihat.
 *   - 7 sekolah namanya tanpa awalan sama sekali; mereka dimasukkan SPS, yang
 *     memang kategori "satuan PAUD sejenis" alias penampung.
 *   - Nama dibiarkan apa adanya dari sumber, termasuk yang huruf besar semua.
 *     Merapikannya berarti mengarang ejaan, dan nama sekolah bukan milik kita.
 *
 * RA di sini datang dari Dapodik. Sebagian RA didata Kemenag lewat EMIS dan
 * boleh jadi tidak semuanya muncul di sini - jadi daftar RA-nya belum tentu
 * lengkap, walau jauh lebih baik daripada tidak ada sama sekali.
 */
class AsalPaudSeeder extends Seeder
{
    public function run(): void
    {
        $berkas = database_path('data/paud-pekanbaru.csv');

        if (! is_readable($berkas)) {
            return;
        }

        $handle = fopen($berkas, 'r');
        fgetcsv($handle); // Lewati baris judul: npsn, jenis, nama, kecamatan.
        $baris = [];
        $sekarang = now();

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 4) {
                continue;
            }

            [$npsn, $jenis, $nama, $kecamatan] = $data;

            $baris[] = [
                'npsn' => $npsn,
                'jenis' => $jenis,
                'nama' => $nama,
                'kecamatan' => $kecamatan,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        fclose($handle);

        // Sekali tulis per 200 baris, bukan 634 kali updateOrCreate. NPSN-nya
        // unik, jadi upsert aman dijalankan berulang kali.
        foreach (array_chunk($baris, 200) as $potongan) {
            AsalPaud::upsert($potongan, ['npsn'], ['jenis', 'nama', 'kecamatan', 'updated_at']);
        }
    }
}
