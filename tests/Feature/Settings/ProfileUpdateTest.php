<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

/**
 * users.telepon sempat hanya bisa diubah Super Admin - halaman profil sendiri
 * tidak memuatnya sama sekali, padahal itu nomor pemiliknya dan dipakai sekolah
 * untuk menghubunginya.
 */
test('pemilik akun bisa mengubah nomor teleponnya sendiri', function () {
    $user = User::factory()->create(['telepon' => '081200000001']);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telepon' => '081298765432',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->telepon)->toBe('081298765432');
});

test('nomor telepon boleh dikosongkan', function () {
    $user = User::factory()->create(['telepon' => '081200000001']);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telepon' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->telepon)->toBeNull();
});

/**
 * Peran dan status aktif BUKAN hak pemiliknya - keduanya cuma boleh lewat
 * Super Admin. Kalau suatu saat ikut fillable di halaman ini, wali murid bisa
 * mengangkat dirinya sendiri jadi staf.
 */
test('peran dan status aktif tidak bisa diubah lewat halaman profil', function () {
    $user = User::factory()->create(['role' => 'wali_murid', 'status_aktif' => true]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'super_admin',
            'status_aktif' => false,
        ]);

    $user->refresh();

    expect($user->role)->toBe('wali_murid')
        ->and($user->status_aktif)->toBeTrue();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

/*
| Dua uji "user can delete their account" bawaan starter kit DIHAPUS bersama
| rutenya, dan diganti dua uji di bawah yang menjaganya tetap tertutup.
|
| Sebabnya: users cascade ke pendaftaran_ppdb, yang cascade lagi ke
| pembayaran_ppdb + tagihan_item. Wali yang sudah mentransfer bisa menghapus
| akunnya sendiri bermodal kata sandinya, dan catatan uang yang sudah masuk
| ikut lenyap. Jalur pengunduran diri yang benar ada di staf: menutup
| pendaftaran melepas kursi kuota tanpa menghapus riwayat apa pun.
*/

test('rute hapus akun sendiri sudah tidak ada', function () {
    $user = User::factory()->create();

    // 405, bukan 404: URI-nya masih ada untuk GET dan PATCH, cuma metode
    // DELETE-nya yang sudah tidak dilayani.
    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});

/**
 * Route DELETE yang BOLEH ada cuma pada data konfigurasi yang tidak memegang
 * uang: komponen biaya dan jalur pendaftaran. Foreign key keduanya sengaja
 * tidak cascade ke pendaftaran, jadi yang sudah dipakai tetap tertolak.
 *
 * Yang TIDAK boleh: pengguna, tahun ajaran, gelombang, pendaftaran, pembayaran.
 * Semuanya cascade sampai ke ledger transfer.
 */
test('tidak ada route DELETE pada data yang memegang uang', function () {
    // Ketiganya data konfigurasi yang TIDAK memegang uang maupun berkas, dan
    // controller-nya masing-masing menolak menghapus yang sudah dipakai.
    $boleh = [
        'super-admin/konfigurasi/komponen-biaya/{komponenBiaya}',
        'super-admin/konfigurasi/jalur/{jalur}',
        'super-admin/konfigurasi/berkas-persyaratan/{berkasPersyaratan}',
    ];

    $rute = collect(app('router')->getRoutes())
        // Dicocokkan persis ['DELETE'], bukan in_array: Route::redirect()
        // terdaftar sebagai ANY sehingga DELETE ikut masuk daftar metodenya -
        // padahal dia pengalih, bukan penghapus.
        ->filter(fn ($r) => $r->methods() === ['DELETE'])
        ->map(fn ($r) => $r->uri())
        ->reject(fn (string $uri) => in_array($uri, $boleh, true))
        ->values();

    expect($rute)->toBeEmpty();
});
