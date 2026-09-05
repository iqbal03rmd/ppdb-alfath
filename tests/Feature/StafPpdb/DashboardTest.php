<?php

use App\Models\GelombangPpdb;
use App\Models\PembayaranPpdb;
use App\Models\PendaftaranPpdb;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed();

    $this->staf = User::where('email', 'staf@ppdbalfath.test')->firstOrFail();
});

test('staf bisa membuka beranda', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertOk();
});

test('wali murid tidak boleh membuka beranda staf', function () {
    $wali = User::where('email', 'wali@ppdbalfath.test')->firstOrFail();

    $this->actingAs($wali)
        ->get(route('staf-ppdb.dashboard'))
        ->assertForbidden();
});

test('angka antrian cocok dengan isi antriannya', function () {
    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('staf-ppdb/dashboard')
            ->where('antrian.pendaftaran.jumlah', PendaftaranPpdb::where('status', 'diajukan')->count())
            ->where('antrian.transfer.jumlah', PembayaranPpdb::where('status', 'menunggu_verifikasi')->count())
        );
});

/**
 * Diagram status: angkanya harus sama dengan hitungan langsung di database.
 */
test('sebaran status pendaftaran cocok dengan datanya', function () {
    $harusnya = PendaftaranPpdb::query()
        ->selectRaw('status, count(*) as jumlah')
        ->groupBy('status')
        ->pluck('jumlah', 'status')
        ->all();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('statistik.pendaftaran', $harusnya));
});

/**
 * Yang belum boleh melihat tagihan TIDAK ikut dihitung di diagram pembayaran -
 * dia memang belum punya urusan uang, bukan berarti belum bayar. Kalau ikut
 * terhitung, "belum bayar" jadi terlihat jauh lebih besar daripada kenyataannya.
 */
test('diagram pembayaran hanya menghitung yang sudah boleh lihat tagihan', function () {
    $harusnya = PendaftaranPpdb::with(['pembayaran', 'tagihanItem'])
        ->get()
        ->filter(fn (PendaftaranPpdb $p) => $p->bolehLihatTagihan())
        ->count();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertInertia(function (AssertableInertia $page) use ($harusnya) {
            $pembayaran = $page->toArray()['props']['statistik']['pembayaran'];

            expect(array_sum($pembayaran))->toBe($harusnya)
                ->and($harusnya)->toBeLessThan(PendaftaranPpdb::count());
        });
});

test('kartu kuota menampilkan sisa daya tampung gelombang yang dibuka', function () {
    $gelombang = GelombangPpdb::with('tahunAjaran')->where('status_buka', true)->firstOrFail();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertInertia(function (AssertableInertia $page) use ($gelombang) {
            $props = $page->toArray()['props'];

            expect($props['kuota']['gelombang'])->toContain($gelombang->nama)
                ->and($props['kuota']['kategori'])->not->toBeEmpty();

            foreach ($props['kuota']['kategori'] as $kategori) {
                expect($kategori['sisa'])->toBe(max(0, $kategori['kuota'] - $kategori['terpakai']));
            }
        });
});

test('banner menyebut gelombang yang sedang berjalan', function () {
    $gelombang = GelombangPpdb::where('status_buka', true)->firstOrFail();

    $this->actingAs($this->staf)
        ->get(route('staf-ppdb.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kuota.tanggal_selesai', $gelombang->tanggal_selesai->locale('id')->translatedFormat('d F Y'))
        );
});
