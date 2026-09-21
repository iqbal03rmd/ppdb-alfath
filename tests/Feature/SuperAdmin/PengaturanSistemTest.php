<?php

use App\Models\PendaftaranPpdb;
use App\Models\PengaturanSistem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('email', 'superadmin@ppdbalfath.test')->firstOrFail();
});

test('super admin bisa membuka pengaturan sistem', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.pengaturan-sistem.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/pengaturan-sistem')
            ->where('pengaturan.nama_sekolah', 'SD IT AL FATH')
        );
});

test('peran lain tidak boleh mengubah pengaturan sistem', function (string $email) {
    $pengguna = User::where('email', $email)->firstOrFail();

    $this->actingAs($pengguna)
        ->put(route('super-admin.pengaturan-sistem.update'), pengaturanSistemPayload())
        ->assertForbidden();
})->with([
    'wali@ppdbalfath.test',
    'staf@ppdbalfath.test',
    'kepsek@ppdbalfath.test',
]);

test('super admin bisa memperbarui identitas rekening dan konten landing', function () {
    $data = pengaturanSistemPayload();

    $this->actingAs($this->admin)
        ->put(route('super-admin.pengaturan-sistem.update'), $data)
        ->assertRedirect(route('super-admin.pengaturan-sistem.edit'))
        ->assertSessionHas('success');

    expect(PengaturanSistem::query()->count())->toBe(1)
        ->and(PengaturanSistem::saatIni()->only(array_keys($data)))->toMatchArray($data);
});

test('informasi rekening harus diisi sebagai satu paket', function () {
    $data = pengaturanSistemPayload();
    $data['nama_pemilik_rekening'] = '';

    $this->actingAs($this->admin)
        ->put(route('super-admin.pengaturan-sistem.update'), $data)
        ->assertSessionHasErrors('nama_pemilik_rekening');
});

test('landing page membaca konten dinamis dan gelombang yang sedang dibuka', function () {
    PengaturanSistem::tersimpan()->update(pengaturanSistemPayload());

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('pengaturan.nama_sekolah', 'SD IT AL FATH Pekanbaru')
            ->where('pengaturan.judul_landing', 'PPDB Tahun Ajaran Baru')
            ->where('pengaturan.whatsapp_url', 'https://wa.me/6281234567890')
            ->where('gelombang.nama', 'Gelombang 1')
            ->where('gelombang.tahun_ajaran', '2026/2027')
        );
});

test('halaman pembayaran wali membaca rekening dari pengaturan sistem', function () {
    PengaturanSistem::tersimpan()->update(pengaturanSistemPayload());
    $pendaftaran = PendaftaranPpdb::where('nomor_pendaftaran', 'PPDB-2026-00004')->firstOrFail();

    $this->actingAs($pendaftaran->user)
        ->get(route('wali-murid.pembayaran.show', $pendaftaran))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wali-murid/pembayaran')
            ->where('informasiPembayaran.nama_bank', 'Bank Syariah Indonesia')
            ->where('informasiPembayaran.nomor_rekening', '1234567890')
            ->where('informasiPembayaran.nama_pemilik_rekening', 'Yayasan Al-Fath')
        );
});

/** @return array<string, string> */
function pengaturanSistemPayload(): array
{
    return [
        'nama_sekolah' => 'SD IT AL FATH Pekanbaru',
        'tagline' => 'Berilmu, Beriman, dan Berakhlak',
        'alamat' => 'Jl. Contoh No. 1, Pekanbaru',
        'telepon' => '0761123456',
        'email' => 'info@alfath.sch.id',
        'nama_bank' => 'Bank Syariah Indonesia',
        'nomor_rekening' => '1234567890',
        'nama_pemilik_rekening' => 'Yayasan Al-Fath',
        'instruksi_pembayaran' => 'Cantumkan nomor pendaftaran pada berita transfer.',
        'judul_landing' => 'PPDB Tahun Ajaran Baru',
        'deskripsi_landing' => 'Pendaftaran peserta didik baru kini dapat dilakukan secara daring.',
        'pengumuman_landing' => 'Kuota terbatas selama gelombang pendaftaran berlangsung.',
        'whatsapp_kontak' => '081234567890',
    ];
}
