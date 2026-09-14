<?php

namespace App\Jobs;

use App\Models\NotifikasiWhatsapp;
use App\Services\FonnteService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class KirimNotifikasiWhatsApp implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $notifikasiId) {}

    public function uniqueId(): string
    {
        return (string) $this->notifikasiId;
    }

    public function handle(FonnteService $fonnte): void
    {
        $notifikasi = NotifikasiWhatsapp::find($this->notifikasiId);

        if ($notifikasi === null || $notifikasi->status === 'diterima_gateway') {
            return;
        }

        $notifikasi->increment('jumlah_percobaan');

        try {
            $hasil = match (config('services.whatsapp.driver')) {
                'log' => $fonnte->simulasikan($notifikasi->nomor_tujuan, $notifikasi->pesan),
                'fonnte' => $fonnte->kirim($notifikasi->nomor_tujuan, $notifikasi->pesan),
                default => throw new \RuntimeException('Driver WhatsApp tidak dikenali.'),
            };

            $notifikasi->update([
                // Respons sukses Fonnte berarti pesan diterima antrean gateway,
                // belum membuktikan pesan sudah dibaca penerima.
                'status' => 'diterima_gateway',
                'provider_message_id' => $hasil['message_id'],
                'provider_request_id' => $hasil['request_id'],
                'keterangan_status' => null,
                'diterima_gateway_pada' => now(),
                'gagal_pada' => null,
            ]);
        } catch (Throwable $e) {
            $notifikasi->update([
                'status' => 'menunggu',
                'keterangan_status' => str($e->getMessage())->limit(1000),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotifikasiWhatsapp::whereKey($this->notifikasiId)->update([
            'status' => 'gagal',
            'keterangan_status' => str($exception?->getMessage() ?? 'Pengiriman gagal tanpa keterangan.')->limit(1000),
            'gagal_pada' => now(),
        ]);
    }
}
