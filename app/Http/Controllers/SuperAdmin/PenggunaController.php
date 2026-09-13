<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola akun pengguna - satu-satunya tempat peran (role) ditetapkan.
 *
 * Penghapusan hanya tersedia untuk akun tanpa satu pun jejak aktivitas PPDB.
 * Rantai foreign key pemilik pendaftaran cascade sampai ke uang:
 *
 *   users -> pendaftaran_ppdb -> pembayaran_ppdb + tagihan_item
 *
 * Artinya satu klik "hapus" pada akun wali memusnahkan seluruh pendaftaran
 * anaknya BESERTA ledger transfernya - diam-diam, dan tidak bisa dibatalkan.
 * Karena itu akun yang memiliki pendaftaran atau pernah memverifikasi data tidak
 * boleh dihapus. Untuk akun tersebut, nonaktifkan tetap menjadi satu-satunya
 * cara mencabut akses tanpa menghilangkan riwayat.
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
            ->withCount(['pendaftaran', 'pendaftaranDiverifikasi', 'pembayaranDiverifikasi'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'telepon' => $u->telepon,
                'role' => $u->role,
                'peran' => self::PERAN[$u->role] ?? $u->role,
                'status_aktif' => (bool) $u->status_aktif,
                'jumlah_pendaftaran' => $u->pendaftaran_count,
                // Ditandai dari server supaya layar tidak perlu membanding-
                // bandingkan id sendiri untuk tahu mana barisnya sendiri.
                'diri_sendiri' => $u->id === $request->user()->id,
                'alasan_peran_terkunci' => $this->alasanPeranTerkunci(
                    $request,
                    $u,
                    $u->pendaftaran_count > 0
                ),
                'bisa_dihapus' => $this->bisaDihapus(
                    $request,
                    $u,
                    $u->pendaftaran_count > 0
                        || $u->pendaftaran_diverifikasi_count > 0
                        || $u->pembayaran_diverifikasi_count > 0
                ),
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
     * Hapus permanen hanya untuk akun yang belum meninggalkan jejak bisnis.
     * Pemeriksaan dilakukan lagi di dalam transaksi: nilai `bisa_dihapus` dari
     * index cuma untuk presentasi dan tidak pernah dipercaya sebagai pengaman.
     */
    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        $nama = $pengguna->name;

        DB::transaction(function () use ($request, $pengguna): void {
            $target = User::query()->lockForUpdate()->findOrFail($pengguna->id);

            if (! $this->bisaDihapus($request, $target)) {
                throw ValidationException::withMessages([
                    'pengguna' => 'Akun tidak bisa dihapus karena sudah memiliki aktivitas PPDB atau merupakan akun Anda sendiri.',
                ]);
            }

            // Tabel session tidak memakai foreign key ke users. Bersihkan agar
            // sesi akun yang dihapus tidak meninggalkan baris yatim.
            DB::table('sessions')->where('user_id', $target->id)->delete();
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $target->delete();
        });

        return to_route('super-admin.pengguna.index')
            ->with('success', "Akun {$nama} dihapus permanen.");
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
    private function alasanPeranTerkunci(Request $request, User $pengguna, ?bool $punyaPendaftaran = null): ?string
    {
        if ($pengguna->id === $request->user()->id) {
            return 'Anda tidak bisa mengubah peran akun Anda sendiri.';
        }

        if ($pengguna->role === 'wali_murid' && ($punyaPendaftaran ?? $pengguna->pendaftaran()->exists())) {
            return 'Peran akun ini terkunci karena sudah melakukan pendaftaran PPDB.';
        }

        return null;
    }

    private function bisaDihapus(Request $request, User $pengguna, ?bool $punyaJejak = null): bool
    {
        if ($pengguna->id === $request->user()->id) {
            return false;
        }

        $punyaJejak ??= $pengguna->pendaftaran()->exists()
            || $pengguna->pendaftaranDiverifikasi()->exists()
            || $pengguna->pembayaranDiverifikasi()->exists();

        return ! $punyaJejak;
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
