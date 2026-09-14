<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Suno Gunawan',
                'email' => 'wali@ppdbalfath.test',
                'telepon' => '081200000001',
                'role' => 'wali_murid',
                'notifikasi_whatsapp_aktif' => true,
                'persetujuan_whatsapp_pada' => now(),
            ],
            [
                'name' => 'Dedi Kurniawan',
                'email' => 'staf@ppdbalfath.test',
                'telepon' => '081200000002',
                'role' => 'staf_ppdb',
            ],
            [
                'name' => 'Hj. Marwah, S.Pd.',
                'email' => 'kepsek@ppdbalfath.test',
                'telepon' => '081200000003',
                'role' => 'kepala_sekolah',
            ],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@ppdbalfath.test',
                'telepon' => '081200000004',
                'role' => 'super_admin',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'telepon' => $data['telepon'],
                    'role' => $data['role'],
                    'status_aktif' => true,
                    'notifikasi_whatsapp_aktif' => $data['notifikasi_whatsapp_aktif'] ?? false,
                    'persetujuan_whatsapp_pada' => $data['persetujuan_whatsapp_pada'] ?? null,
                    'email_verified_at' => now(),
                    'password' => 'password',
                ]
            );
        }
    }
}
