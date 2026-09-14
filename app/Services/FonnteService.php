<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class FonnteService
{
    /** @return array{message_id: string|null, request_id: string|null} */
    public function kirim(string $nomorTujuan, string $pesan): array
    {
        $token = (string) config('services.fonnte.token');

        if ($token === '') {
            throw new RuntimeException('FONNTE_TOKEN belum dikonfigurasi.');
        }

        $response = Http::asForm()
            ->withHeaders(['Authorization' => $token])
            ->timeout((int) config('services.fonnte.timeout', 15))
            ->post((string) config('services.fonnte.endpoint'), [
                'target' => $nomorTujuan,
                'message' => $pesan,
                'connectOnly' => true,
                'countryCode' => '0',
                'preview' => false,
            ]);

        $response->throw();
        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? $data['Status'] ?? false) !== true) {
            throw new RuntimeException((string) ($data['reason'] ?? 'Fonnte menolak permintaan pengiriman.'));
        }

        $messageId = is_array($data['id'] ?? null) ? ($data['id'][0] ?? null) : ($data['id'] ?? null);

        return [
            'message_id' => $messageId !== null ? (string) $messageId : null,
            'request_id' => isset($data['requestid']) ? (string) $data['requestid'] : null,
        ];
    }

    /** @return array{message_id: string, request_id: null} */
    public function simulasikan(string $nomorTujuan, string $pesan): array
    {
        Log::info('Simulasi notifikasi WhatsApp', [
            'nomor_tujuan' => str_repeat('*', max(0, strlen($nomorTujuan) - 4)).substr($nomorTujuan, -4),
            'panjang_pesan' => mb_strlen($pesan),
        ]);

        return ['message_id' => 'log-'.Str::uuid(), 'request_id' => null];
    }
}
