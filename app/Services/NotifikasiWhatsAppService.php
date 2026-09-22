<?php

namespace App\Services;

use App\Jobs\KirimNotifikasiWhatsApp;
use App\Models\NotifikasiWhatsapp;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\PengaturanSistem;
use App\Rules\NomorWhatsApp;

class NotifikasiWhatsAppService
{
    public function pembayaranDiterima(PembayaranPpdb $pembayaran): NotifikasiWhatsapp
    {
        $pendaftaran = $this->pendaftaranTerkini($pembayaran);

        return $this->catat(
            $pendaftaran,
            'pembayaran_diterima',
            'pembayaran_diterima:'.$pembayaran->id,
            $this->pesanPembayaranDiterima($pendaftaran, $pembayaran),
        );
    }

    public function pembayaranDitolak(PembayaranPpdb $pembayaran): NotifikasiWhatsapp
    {
        $pendaftaran = $this->pendaftaranTerkini($pembayaran);

        return $this->catat(
            $pendaftaran,
            'pembayaran_ditolak',
            'pembayaran_ditolak:'.$pembayaran->id,
            $this->pesanPembayaranDitolak($pendaftaran, $pembayaran),
        );
    }

    public function pengingatJatuhTempo(PendaftaranPpdb $pendaftaran, int $hariSebelum): NotifikasiWhatsapp
    {
        $pendaftaran->loadMissing(['user', 'gelombang', 'tagihanItem', 'pembayaran']);
        $tanggal = $pendaftaran->batasMinimalBayar()?->format('Y-m-d') ?? 'tanpa-tanggal';

        return $this->catat(
            $pendaftaran,
            'pengingat_jatuh_tempo_minimal',
            "pengingat_jatuh_tempo_minimal:{$pendaftaran->id}:{$tanggal}",
            $this->pesanPengingatJatuhTempo($pendaftaran, $hariSebelum),
        );
    }

    private function pendaftaranTerkini(PembayaranPpdb $pembayaran): PendaftaranPpdb
    {
        return $pembayaran->pendaftaran()
            ->with(['user', 'gelombang', 'tagihanItem', 'pembayaran'])
            ->firstOrFail();
    }

    private function catat(PendaftaranPpdb $pendaftaran, string $jenis, string $idempotencyKey, string $pesan): NotifikasiWhatsapp
    {
        $pendaftaran->loadMissing('user');
        $nomorTujuan = NomorWhatsApp::normalisasi($pendaftaran->user->telepon);
        [$status, $keterangan] = $this->statusAwal($pendaftaran, $nomorTujuan);

        $notifikasi = NotifikasiWhatsapp::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $pendaftaran->user_id,
                'pendaftaran_ppdb_id' => $pendaftaran->id,
                'jenis' => $jenis,
                'nomor_tujuan' => $nomorTujuan,
                'pesan' => $pesan,
                'status' => $status,
                'keterangan_status' => $keterangan,
            ],
        );

        if ($notifikasi->wasRecentlyCreated && $status === 'menunggu') {
            KirimNotifikasiWhatsApp::dispatch($notifikasi->id)->afterCommit();
        }

        return $notifikasi;
    }

    /** @return array{string, string|null} */
    private function statusAwal(PendaftaranPpdb $pendaftaran, ?string $nomorTujuan): array
    {
        if (! config('services.whatsapp.enabled')) {
            return ['dilewati', 'Pengiriman WhatsApp sedang dinonaktifkan.'];
        }

        if (! $pendaftaran->user->notifikasi_whatsapp_aktif) {
            return ['dilewati', 'Wali tidak mengaktifkan notifikasi WhatsApp.'];
        }

        if ($nomorTujuan === null) {
            return ['dilewati', 'Nomor WhatsApp wali kosong atau tidak valid.'];
        }

        return ['menunggu', null];
    }

    private function pesanPembayaranDiterima(PendaftaranPpdb $pendaftaran, PembayaranPpdb $pembayaran): string
    {
        $rincianStatus = $pendaftaran->sudahPenuhiMinimal()
            ? 'Minimal pembayaran telah terpenuhi. Status pendaftaran: Diterima.'
            : 'Kekurangan agar pendaftaran diterima: '.$this->rupiah($pendaftaran->kurangMinimal()).'.';

        return $this->susunPesan($pendaftaran, [
            "Pembayaran sebesar {$this->rupiah($pembayaran->nominal_transfer)} untuk {$pendaftaran->nama_pendaftar} ({$pendaftaran->nomor_pendaftaran}) telah diterima.",
            '',
            'Total pembayaran terverifikasi: '.$this->rupiah($pendaftaran->totalTerbayar()),
            $rincianStatus,
            '',
            'Lihat rincian pembayaran:',
            route('wali-murid.pembayaran.show', $pendaftaran, absolute: true),
        ]);
    }

    private function pesanPembayaranDitolak(PendaftaranPpdb $pendaftaran, PembayaranPpdb $pembayaran): string
    {
        $baris = [
            "Bukti pembayaran sebesar {$this->rupiah($pembayaran->nominal_transfer)} untuk {$pendaftaran->nama_pendaftar} ({$pendaftaran->nomor_pendaftaran}) ditolak.",
            '',
            'Alasan: '.($pembayaran->catatan_verifikasi ?? 'Bukti pembayaran belum dapat diterima.'),
        ];

        if (! $pendaftaran->sudahPenuhiMinimal()) {
            $baris[] = 'Kekurangan agar pendaftaran diterima: '.$this->rupiah($pendaftaran->kurangMinimal()).'.';
        }

        array_push(
            $baris,
            '',
            'Periksa catatan staf dan unggah bukti pembayaran yang benar:',
            route('wali-murid.pembayaran.show', $pendaftaran, absolute: true),
        );

        return $this->susunPesan($pendaftaran, $baris);
    }

    private function pesanPengingatJatuhTempo(PendaftaranPpdb $pendaftaran, int $hariSebelum): string
    {
        $tenggat = $pendaftaran->batasMinimalBayar()?->locale('id')->translatedFormat('d F Y') ?? '-';

        return $this->susunPesan($pendaftaran, [
            "Pengingat pembayaran untuk {$pendaftaran->nama_pendaftar} ({$pendaftaran->nomor_pendaftaran}).",
            '',
            "Tersisa {$hariSebelum} hari sebelum jatuh tempo pada {$tenggat}.",
            'Total pembayaran terverifikasi: '.$this->rupiah($pendaftaran->totalTerbayar()),
            'Minimal pembayaran: '.$this->rupiah((int) $pendaftaran->minimalBayar()),
            'Kekurangan agar pendaftaran diterima: '.$this->rupiah($pendaftaran->kurangMinimal()),
            '',
            'Segera unggah bukti pembayaran melalui:',
            route('wali-murid.pembayaran.show', $pendaftaran, absolute: true),
        ]);
    }

    /** @param list<string> $baris */
    private function susunPesan(PendaftaranPpdb $pendaftaran, array $baris): string
    {
        $namaSekolah = PengaturanSistem::saatIni()->nama_sekolah;

        return implode("\n", [
            "Assalamu'alaikum, Bapak/Ibu {$pendaftaran->user->name}.",
            '',
            ...$baris,
            '',
            "Pesan otomatis PPDB {$namaSekolah}. Notifikasi dapat dinonaktifkan melalui menu Pengaturan akun.",
        ]);
    }

    private function rupiah(int $nominal): string
    {
        return 'Rp'.number_format($nominal, 0, ',', '.');
    }
}
