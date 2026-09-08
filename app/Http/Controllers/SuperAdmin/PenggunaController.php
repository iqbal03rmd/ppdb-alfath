<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola akun pengguna - satu-satunya tempat peran (role) ditetapkan.
 *
 * TIDAK ADA AKSI HAPUS di modul ini, dan itu bukan kelalaian. Rantai foreign
 * key-nya cascade sampai ke uang:
 *
 *   users -> pendaftaran_ppdb -> pembayaran_ppdb + tagihan_item
 *
 * Artinya satu klik "hapus" pada akun wali memusnahkan seluruh pendaftaran
 * anaknya BESERTA ledger transfernya - diam-diam, dan tidak bisa dibatalkan.
 * Yang tersedia cuma menonaktifkan (users.status_aktif): hak masuknya dicabut
 * tanpa menyentuh satu baris pun riwayat.
 *
 * Kalau suatu saat penghapusan permanen benar-benar dibutuhkan, yang harus
 * diubah lebih dulu aturan cascade di migration - bukan menambahkan destroy()
 * di sini.
 */
class PenggunaController extends Controller
{
    /**
     * Peran yang sah beserta labelnya. Satu daftar ini dipakai bersama oleh
     * validasi DAN layar, jadi tidak mungkin ada peran yang bisa dipilih di
     * halaman tapi ditolak server, atau sebaliknya.
     */
    public const PERAN = [
        'wali_murid' => 'Wali Murid',
        'staf_ppdb' => 'Staf PPDB',
        'kepala_sekolah' => 'Kepala Sekolah',
        'super_admin' => 'Super Admin',
    ];

    public function index(Request $request): Response
    {
        $pengguna = User::query()
            // Dipakai buat dua hal di layar: menerangkan kenapa peran sebuah
            // akun terkunci, dan menyebut apa yang ikut terdampak kalau akunnya
            // dinonaktifkan. Lewat withCount, bukan memuat seluruh relasinya.
            ->withCount('pendaftaran')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'telepon' => $u->telepon,
                'peran' => self::PERAN[$u->role] ?? $u->role,
                'status_aktif' => (bool) $u->status_aktif,
                'jumlah_pendaftaran' => $u->pendaftaran_count,
                // Ditandai dari server supaya layar tidak perlu membanding-
                // bandingkan id sendiri untuk tahu mana barisnya sendiri.
                'diri_sendiri' => $u->id === $request->user()->id,
            ])
            ->all();

        return Inertia::render('super-admin/pengguna', [
            'pengguna' => $pengguna,
            'peran' => self::PERAN,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('super-admin/pengguna-form', [
            'peran' => self::PERAN,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // 'lowercase' disamakan dengan ProfileUpdateRequest. Tanpa itu, akun
            // yang dibuat admin dengan huruf besar akan ditolak saat pemiliknya
            // menyimpan profilnya sendiri - error di kolom yang tidak dia sentuh.
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(self::PERAN))],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], $this->pesanValidasi());

        // Akun ini dibuatkan sekolah untuk orang yang sudah dikenal, jadi
        // emailnya tidak perlu dibuktikan lagi lewat tautan verifikasi.
        User::create([
            ...$data,
            'status_aktif' => true,
            'email_verified_at' => now(),
        ]);

        return to_route('super-admin.pengguna.index')
            ->with('success', "Akun {$data['name']} dibuat. Sampaikan kata sandinya langsung ke orangnya.");
    }

    public function edit(Request $request, User $pengguna): Response
    {
        return Inertia::render('super-admin/pengguna-form', [
            'peran' => self::PERAN,
            'pengguna' => [
                'id' => $pengguna->id,
                'name' => $pengguna->name,
                'email' => $pengguna->email,
                'telepon' => $pengguna->telepon,
                'role' => $pengguna->role,
                'status_aktif' => (bool) $pengguna->status_aktif,
                'jumlah_pendaftaran' => $pengguna->pendaftaran()->count(),
                'diri_sendiri' => $pengguna->id === $request->user()->id,
                // Alasan peran terkunci dihitung di sini, bukan disusun ulang
                // di TSX dari dua-tiga prop - supaya syarat yang ditegakkan
                // server dan kalimat yang dibaca admin tidak bisa berbeda.
                'alasan_peran_terkunci' => $this->alasanPeranTerkunci($request, $pengguna),
            ],
        ]);
    }

    public function update(Request $request, User $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($pengguna->id)],
            'telepon' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(self::PERAN))],
            // Dikosongkan = kata sandi lama dipertahankan. Jalur ini untuk admin
            // menolong orang yang lupa kata sandinya, bukan tempat mengubah kata
            // sandi sendiri - itu ada di Pengaturan.
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ], $this->pesanValidasi());

        if ($data['role'] !== $pengguna->role) {
            // Guard di server, bukan sekadar select yang dimatikan di layar:
            // rutenya bisa ditembak langsung.
            $alasan = $this->alasanPeranTerkunci($request, $pengguna);

            abort_if($alasan !== null, 403, $alasan ?? '');
        }

        $pengguna->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'telepon' => $data['telepon'] ?? null,
            'role' => $data['role'],
            // ?? null, bukan langsung $data['password']: kolom bertanda
            // 'nullable' tidak ikut muncul di hasil validasi kalau memang tidak
            // dikirim sama sekali - dan permintaan tanpa kolom itu sah.
            //
            // Cast 'hashed' di model yang meng-hash-nya. Jangan Hash::make lagi
            // di sini - nilainya ter-hash dua kali dan tidak akan pernah cocok.
            ...(($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        return to_route('super-admin.pengguna.index')
            ->with('success', "Data {$pengguna->name} diperbarui.");
    }

    /**
     * Menyalakan atau mematikan hak masuk sebuah akun.
     *
     * Nilainya dikirim eksplisit, bukan dibalik dari keadaan sekarang: saklar
     * buta bikin dua klik beruntun - atau dua tab yang terbuka bersamaan -
     * berakhir di keadaan yang bukan diinginkan siapa pun.
     */
    public function status(Request $request, User $pengguna): RedirectResponse
    {
        $data = $request->validate(['status_aktif' => ['required', 'boolean']]);

        // Menonaktifkan diri sendiri sama dengan mengunci diri di luar rumah:
        // yang bisa menghidupkan akun cuma Super Admin, dan dia sendiri sudah
        // tidak bisa masuk.
        //
        // Larangan ini sekaligus menjamin selalu ada minimal satu Super Admin
        // aktif - siapa pun yang menekan tombolnya, dirinya sendiri tetap hidup.
        abort_if(
            $pengguna->id === $request->user()->id,
            403,
            'Anda tidak bisa menonaktifkan akun Anda sendiri.'
        );

        $pengguna->update(['status_aktif' => $data['status_aktif']]);

        return back()->with(
            'success',
            $data['status_aktif']
                ? "Akun {$pengguna->name} diaktifkan kembali."
                : "Akun {$pengguna->name} dinonaktifkan. Sesinya yang sedang berjalan ikut diputus."
        );
    }

    /**
     * Kenapa peran akun ini tidak boleh diganti - null kalau boleh.
     *
     * Dua sebabnya, dua-duanya soal orang nyata:
     *
     * 1. Peran diri sendiri. Super Admin yang menurunkan dirinya jadi wali
     *    murid langsung kehilangan seluruh menu ini, dan tidak ada jalan
     *    mengembalikannya lewat aplikasi.
     *
     * 2. Wali murid yang sudah punya pendaftaran. Pendaftaran, berkas, dan
     *    transfernya menggantung pada user_id itu. Begitu perannya bukan
     *    wali_murid lagi, berkas anaknya tidak bisa dibuka siapa pun -
     *    termasuk dirinya - padahal datanya masih ada dan uangnya sudah masuk.
     */
    private function alasanPeranTerkunci(Request $request, User $pengguna): ?string
    {
        if ($pengguna->id === $request->user()->id) {
            return 'Anda tidak bisa mengubah peran akun Anda sendiri.';
        }

        if ($pengguna->role === 'wali_murid' && $pengguna->pendaftaran()->exists()) {
            return 'Peran akun ini terkunci karena sudah melakukan pendaftaran PPDB.';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function pesanValidasi(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.lowercase' => 'Tulis email dengan huruf kecil semua.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'role.required' => 'Pilih dulu perannya.',
            'password.confirmed' => 'Ulangan kata sandinya belum sama.',
        ];
    }
}
