<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('email', 'superadmin@ppdbalfath.test')->firstOrFail();
});

test('super admin melihat ringkasan kesiapan sistem di beranda', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/dashboard')
            ->where('ringkasan.tahun_ajaran', '2026/2027')
            ->where('ringkasan.gelombang.nama', 'Gelombang 1')
            ->where('ringkasan.gelombang.keadaan', 'menerima')
            ->where('ringkasan.master_aktif.komponen_biaya', 1)
            ->where('ringkasan.master_aktif.berkas_persyaratan', 6)
            ->where('ringkasan.master_aktif.jalur', 4)
            ->has('kesiapan', 6)
            ->where('penggunaPeran.super_admin', 1)
            ->where('penggunaPeran.staf_ppdb', 1)
            ->where('penggunaPeran.kepala_sekolah', 1)
        );
});

test('beranda menandai informasi pembayaran yang belum dilengkapi', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kesiapan.5.kunci', 'pengaturan_sistem')
            ->where('kesiapan.5.siap', false)
            ->where('kesiapan.5.keterangan', 'Rekening pembayaran belum dilengkapi.')
        );
});

test('peran lain tidak dapat membuka beranda super admin', function (string $email) {
    $pengguna = User::where('email', $email)->firstOrFail();

    $this->actingAs($pengguna)
        ->get(route('super-admin.dashboard'))
        ->assertForbidden();
})->with([
    'wali@ppdbalfath.test',
    'staf@ppdbalfath.test',
    'kepsek@ppdbalfath.test',
]);
