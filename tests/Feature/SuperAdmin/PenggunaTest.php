<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed();

    $this->admin = User::where('email', 'superadmin@ppdbalfath.test')->firstOrFail();
    $this->wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();
    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();
});

test('super admin bisa membuka daftar pengguna', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.pengguna.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('super-admin/pengguna')
            ->has('pengguna', User::count())
        );
});

test('peran lain tidak boleh membuka kelola pengguna', function (string $email) {
    $this->actingAs(User::where('email', $email)->firstOrFail())
        ->get(route('super-admin.pengguna.index'))
        ->assertForbidden();
})->with([
    'staf@ppdbalfath.test',
    'kepsek@ppdbalfath.test',
    'wali@ppdbalfath.test',
]);

test('halaman tambah pengguna terbuka dan membawa daftar peran', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.pengguna.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('super-admin/pengguna-form')
            ->has('peran', 4)
            ->missing('pengguna')
        );
});

/**
 * Alasan peran terkunci datang dari server, bukan disusun ulang di TSX -
 * jadi kalimat yang dibaca admin dan syarat yang ditegakkan update() tidak
 * bisa berbeda pendapat.
 */
test('halaman ubah menyebut kenapa peran wali terkunci', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.pengguna.edit', $this->wali))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('super-admin/pengguna-form')
            ->where('pengguna.id', $this->wali->id)
            ->where('pengguna.diri_sendiri', false)
            ->whereNot('pengguna.alasan_peran_terkunci', null)
        );
});

test('halaman ubah akun yang perannya bebas tidak menampilkan alasan terkunci', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.pengguna.edit', $this->staf))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pengguna.alasan_peran_terkunci', null)
        );
});

test('akun baru dibuat dengan kata sandi ter-hash dan langsung aktif', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.pengguna.store'), [
            'name' => 'Staf Baru',
            'email' => 'stafbaru@ppdbalfath.test',
            'telepon' => '081200000009',
            'role' => 'staf_ppdb',
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
        ])
        ->assertRedirect(route('super-admin.pengguna.index'));

    $baru = User::where('email', 'stafbaru@ppdbalfath.test')->firstOrFail();

    expect($baru->role)->toBe('staf_ppdb')
        ->and($baru->status_aktif)->toBeTrue()
        // Bukan sekadar 'tidak sama dengan teks aslinya': yang diuji adalah
        // kata sandinya benar-benar bisa dipakai masuk, jadi hash-nya sekali -
        // bukan dua kali oleh cast 'hashed' plus Hash::make.
        ->and(Hash::check('rahasia-sekali', $baru->password))->toBeTrue();
});

test('email yang sudah dipakai ditolak', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.pengguna.store'), [
            'name' => 'Kembaran',
            'email' => $this->staf->email,
            'role' => 'staf_ppdb',
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
        ])
        ->assertSessionHasErrors('email');
});

test('mengubah data tanpa mengisi kata sandi tidak mengganti kata sandi lama', function () {
    $sebelum = $this->staf->password;

    $this->actingAs($this->admin)
        ->put(route('super-admin.pengguna.update', $this->staf), [
            'name' => 'Dedi Kurniawan, S.Kom.',
            'email' => $this->staf->email,
            'telepon' => $this->staf->telepon,
            'role' => 'staf_ppdb',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('super-admin.pengguna.index'));

    $this->staf->refresh();

    expect($this->staf->name)->toBe('Dedi Kurniawan, S.Kom.')
        ->and($this->staf->password)->toBe($sebelum);
});

test('mengisi kata sandi baru menggantinya', function () {
    $this->actingAs($this->admin)
        ->put(route('super-admin.pengguna.update', $this->staf), [
            'name' => $this->staf->name,
            'email' => $this->staf->email,
            'role' => 'staf_ppdb',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ]);

    expect(Hash::check('kata-sandi-baru', $this->staf->refresh()->password))->toBeTrue();
});

/*
| Pagar-pagar yang menahan admin mengunci dirinya sendiri di luar, atau
| memutus pendaftaran dari pemiliknya. Semuanya ditegakkan di server - bukan
| cuma tombol yang disembunyikan, karena rutenya bisa ditembak langsung.
*/

test('super admin tidak bisa menonaktifkan akunnya sendiri', function () {
    $this->actingAs($this->admin)
        ->post(route('super-admin.pengguna.status', $this->admin), ['status_aktif' => false])
        ->assertForbidden();

    expect($this->admin->refresh()->status_aktif)->toBeTrue();
});

test('super admin tidak bisa mengubah peran akunnya sendiri', function () {
    $this->actingAs($this->admin)
        ->put(route('super-admin.pengguna.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'wali_murid',
        ])
        ->assertForbidden();

    expect($this->admin->refresh()->role)->toBe('super_admin');
});

test('peran wali yang sudah punya pendaftaran tidak bisa diubah', function () {
    expect($this->wali->pendaftaran()->count())->toBeGreaterThan(0);

    $this->actingAs($this->admin)
        ->put(route('super-admin.pengguna.update', $this->wali), [
            'name' => $this->wali->name,
            'email' => $this->wali->email,
            'role' => 'staf_ppdb',
        ])
        ->assertForbidden();

    expect($this->wali->refresh()->role)->toBe('wali_murid');
});

test('wali tanpa pendaftaran masih boleh diubah perannya', function () {
    $baru = User::create([
        'name' => 'Wali Belum Daftar',
        'email' => 'walibaru@ppdbalfath.test',
        'role' => 'wali_murid',
        'status_aktif' => true,
        'password' => 'password',
    ]);

    $this->actingAs($this->admin)
        ->put(route('super-admin.pengguna.update', $baru), [
            'name' => $baru->name,
            'email' => $baru->email,
            'role' => 'staf_ppdb',
        ])
        ->assertRedirect(route('super-admin.pengguna.index'));

    expect($baru->refresh()->role)->toBe('staf_ppdb');
});

test('menonaktifkan akun tidak menghapus pendaftaran maupun pembayarannya', function () {
    $jumlahPendaftaran = $this->wali->pendaftaran()->count();
    $jumlahPembayaran = \App\Models\PembayaranPpdb::whereIn(
        'pendaftaran_ppdb_id',
        $this->wali->pendaftaran()->pluck('id')
    )->count();

    $this->actingAs($this->admin)
        ->post(route('super-admin.pengguna.status', $this->wali), ['status_aktif' => false]);

    expect($this->wali->refresh()->status_aktif)->toBeFalse()
        ->and($this->wali->pendaftaran()->count())->toBe($jumlahPendaftaran)
        ->and(\App\Models\PembayaranPpdb::whereIn(
            'pendaftaran_ppdb_id',
            $this->wali->pendaftaran()->pluck('id')
        )->count())->toBe($jumlahPembayaran);
});

test('akun yang dinonaktifkan bisa diaktifkan kembali', function () {
    $this->staf->update(['status_aktif' => false]);

    $this->actingAs($this->admin)
        ->post(route('super-admin.pengguna.status', $this->staf), ['status_aktif' => true]);

    expect($this->staf->refresh()->status_aktif)->toBeTrue();
});

/*
| Penonaktifan harus benar-benar berakibat. Kalau dua uji di bawah ini hilang,
| tombol "Nonaktifkan" tinggal jadi kolom database yang tidak dibaca siapa pun.
*/

test('akun nonaktif ditolak saat login walau kata sandinya benar', function () {
    $this->staf->update(['status_aktif' => false]);

    $this->post(route('login'), [
        'email' => $this->staf->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('sesi yang sedang berjalan langsung diputus begitu akunnya dinonaktifkan', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertOk();

    $this->staf->update(['status_aktif' => false]);

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('akun aktif tidak terganggu middleware', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertOk();
});

/**
 * Tidak boleh ada satu pun route yang bisa menghapus pengguna: users cascade
 * ke pendaftaran_ppdb, yang cascade lagi ke pembayaran_ppdb. Satu DELETE di
 * modul ini berarti ledger transfer bisa musnah tanpa jejak.
 */
test('tidak ada route penghapusan pengguna', function () {
    $rute = collect(app('router')->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'super-admin.'))
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true));

    expect($rute)->toBeEmpty();
});
