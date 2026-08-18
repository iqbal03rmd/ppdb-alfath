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
                    'email_verified_at' => now(),
                    'password' => 'password',
                ]
            );
        }
    }
}