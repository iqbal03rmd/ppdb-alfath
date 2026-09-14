<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'telepon' => '08123456789',
        'persetujuan_whatsapp' => true,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('wali-murid.dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->notifikasi_whatsapp_aktif)->toBeTrue()
        ->and($user->persetujuan_whatsapp_pada)->not->toBeNull();
});

test('wali tetap bisa mendaftar tanpa menyetujui notifikasi whatsapp', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'telepon' => '08123456789',
        'persetujuan_whatsapp' => false,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('wali-murid.dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->notifikasi_whatsapp_aktif)->toBeFalse()
        ->and($user->persetujuan_whatsapp_pada)->toBeNull();
});

test('pendaftaran akun menolak nomor whatsapp yang tidak valid', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'telepon' => '12345',
        'persetujuan_whatsapp' => true,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('telepon');
});
