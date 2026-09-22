<?php

namespace App\Console\Commands;

use App\Models\PendaftaranPpdb;
use App\Models\PengaturanSistem;
use App\Services\NotifikasiWhatsAppService;
use Illuminate\Console\Command;

class KirimPengingatJatuhTempoPembayaran extends Command
{
    protected $signature = 'ppdb:kirim-pengingat-jatuh-tempo';

    protected $description = 'Antrekan pengingat WhatsApp sebelum tenggat minimal pembayaran PPDB';

    public function handle(NotifikasiWhatsAppService $notifikasi): int
    {
        $hariSebelum = (int) PengaturanSistem::saatIni()->hari_pengingat_jatuh_tempo;
        $tanggalTenggat = today('Asia/Jakarta')->addDays($hariSebelum)->toDateString();
        $jumlahBaru = 0;

        PendaftaranPpdb::query()
            ->with(['user', 'gelombang', 'tagihanItem', 'pembayaran'])
            ->where('status', 'pembayaran')
            ->whereNotNull('minimal_bayar')
            ->whereHas('tagihanItem')
            ->whereHas('gelombang', fn ($query) => $query->whereDate('batas_waktu_pembayaran', $tanggalTenggat))
            ->chunkById(100, function ($pendaftaranList) use ($notifikasi, $hariSebelum, &$jumlahBaru): void {
                foreach ($pendaftaranList as $pendaftaran) {
                    if ($pendaftaran->sudahPenuhiMinimal()) {
                        continue;
                    }

                    $hasil = $notifikasi->pengingatJatuhTempo($pendaftaran, $hariSebelum);

                    if ($hasil->wasRecentlyCreated) {
                        $jumlahBaru++;
                    }
                }
            });

        $this->info("{$jumlahBaru} pengingat jatuh tempo dicatat untuk diproses.");

        return self::SUCCESS;
    }
}
