import logoAlFath from '@/assets/logo-alfath.jpg';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ChevronDown,
    ChevronsUpDown,
    CircleCheckBig,
    ClipboardCheck,
    Database,
    FileText,
    Home,
    LogOut,
    Menu,
    Settings,
    Users,
    Wallet,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

/**
 * `anak` mengubah item jadi menu lipat. Kalau ada, `href` cuma dipakai untuk
 * menentukan apakah kelompoknya sedang aktif - yang ditautkan anak-anaknya.
 */
type MenuItem = { label: string; href: string; icon: ReactNode; anak?: { label: string; href: string }[] };

const roleLabel: Record<string, string> = {
    wali_murid: 'Wali Murid',
    staf_ppdb: 'Staf PPDB',
    kepala_sekolah: 'Kepala Sekolah',
    super_admin: 'Super Admin',
};

const Icon = {
    home: <Home size={18} strokeWidth={1.8} />,
    file: <FileText size={18} strokeWidth={1.8} />,
    checkCircle: <CircleCheckBig size={18} strokeWidth={1.8} />,
    wallet: <Wallet size={18} strokeWidth={1.8} />,
    users: <Users size={18} strokeWidth={1.8} />,
    clipboardCheck: <ClipboardCheck size={18} strokeWidth={1.8} />,
    barChart: <BarChart3 size={18} strokeWidth={1.8} />,
    database: <Database size={18} strokeWidth={1.8} />,
};

const menuByRole: Record<string, MenuItem[]> = {
    wali_murid: [
        { label: 'Beranda', href: '/wali-murid/dashboard', icon: Icon.home },
        { label: 'Pendaftaran', href: '/wali-murid/pendaftaran', icon: Icon.file },
        { label: 'Riwayat Pembayaran', href: '/wali-murid/pembayaran', icon: Icon.wallet },
    ],
    staf_ppdb: [
        { label: 'Beranda', href: '/staf-ppdb/dashboard', icon: Icon.home },
        { label: 'Semua Pendaftaran', href: '/staf-ppdb/pendaftaran', icon: Icon.users },
        { label: 'Verifikasi Pendaftaran', href: '/staf-ppdb/verifikasi-pendaftaran', icon: Icon.clipboardCheck },
        { label: 'Verifikasi Pembayaran', href: '/staf-ppdb/verifikasi-pembayaran', icon: Icon.wallet },
    ],
    kepala_sekolah: [
        { label: 'Beranda', href: '/kepala-sekolah/dashboard', icon: Icon.home },
        { label: 'Rekapitulasi PPDB', href: '/kepala-sekolah/rekapitulasi', icon: Icon.barChart },
    ],
    super_admin: [
        { label: 'Beranda', href: '/super-admin/dashboard', icon: Icon.home },
        { label: 'Kelola Pengguna', href: '/super-admin/pengguna', icon: Icon.users },
        {
            label: 'Konfigurasi PPDB',
            href: '/super-admin/konfigurasi',
            icon: Icon.database,
            // Urutannya mengikuti urutan PENGISIAN, bukan abjad. Empat yang di
            // atas mendefinisikan bahan-bahannya sendiri-sendiri; Gelombang PPDB
            // di paling bawah karena dialah yang merakit semuanya jadi satu
            // angkatan - jadwal, kuota & minimal bayar tiap jalur, berkas wajib
            // tiap jalur, dan nominal tiap komponen. Membukanya sebelum bahannya
            // ada berarti memilih dari daftar yang masih kosong.
            anak: [
                { label: 'Tahun Ajaran', href: '/super-admin/konfigurasi/tahun-ajaran' },
                { label: 'Komponen Biaya', href: '/super-admin/konfigurasi/komponen-biaya' },
                { label: 'Berkas Persyaratan', href: '/super-admin/konfigurasi/berkas-persyaratan' },
                { label: 'Jalur Pendaftaran', href: '/super-admin/konfigurasi/jalur' },
                { label: 'Gelombang PPDB', href: '/super-admin/konfigurasi/gelombang' },
            ],
        },
    ],
};

const gayaMenu = 'mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors ';
const gayaAktif = 'bg-[#0A3981] font-semibold text-white shadow-sm';
const gayaDiam = 'text-gray-600 hover:bg-[#F5F9FD] hover:text-[#0A3981]';

function Lencana({ aktif, children }: { aktif: boolean; children: ReactNode }) {
    return (
        <span
            className={
                'flex h-7 w-7 shrink-0 items-center justify-center rounded-full ' +
                (aktif ? 'bg-[#E38E49] text-white' : 'bg-[#D4EBF8]/60 text-[#1F509A]')
            }
        >
            {children}
        </span>
    );
}

function MenuTunggal({ item, url }: { item: MenuItem; url: string }) {
    const aktif = url.startsWith(item.href);

    return (
        <Link href={item.href} className={gayaMenu + (aktif ? gayaAktif : gayaDiam)}>
            <Lencana aktif={aktif}>{item.icon}</Lencana>
            {item.label}
        </Link>
    );
}

/**
 * Menu lipat. Terbuka sendiri kalau salah satu anaknya sedang dibuka - jadi
 * admin yang mendarat di halaman lewat tautan langsung tetap melihat dia ada
 * di kelompok mana, tanpa harus membuka lipatannya sendiri.
 *
 * Keadaan bukanya disimpan di state supaya bisa ditutup manual, tapi ditata
 * ulang tiap kali halaman aktifnya berpindah ke dalam kelompok ini.
 */
function MenuLipat({ item, url }: { item: MenuItem; url: string }) {
    const adaYangAktif = url.startsWith(item.href);
    const [terbuka, setTerbuka] = useState(adaYangAktif);

    useEffect(() => {
        if (adaYangAktif) setTerbuka(true);
    }, [adaYangAktif]);

    return (
        <div className="mb-1">
            <button
                type="button"
                onClick={() => setTerbuka((t) => !t)}
                aria-expanded={terbuka}
                className={'w-full ' + gayaMenu + (adaYangAktif ? gayaAktif : gayaDiam)}
            >
                <Lencana aktif={adaYangAktif}>{item.icon}</Lencana>
                <span className="flex-1 text-left">{item.label}</span>
                <ChevronDown size={16} strokeWidth={2} className={'transition-transform ' + (terbuka ? 'rotate-180' : '')} />
            </button>

            {terbuka && (
                <div className="mt-1 space-y-0.5 pl-[46px]">
                    {item.anak?.map((anak) => {
                        const aktif = url.startsWith(anak.href);

                        return (
                            <Link
                                key={anak.href}
                                href={anak.href}
                                className={
                                    'block rounded-lg px-3 py-2 text-[13px] transition-colors ' +
                                    (aktif ? 'bg-[#D4EBF8]/70 font-semibold text-[#0A3981]' : 'text-gray-600 hover:bg-[#F5F9FD] hover:text-[#0A3981]')
                                }
                            >
                                {anak.label}
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage<SharedData>().props;
    const { url } = usePage();

    const role = String(auth.user?.role ?? '');
    const menuItems = menuByRole[role] ?? [];

    // Notifikasi flash dari redirect controller - jarang muncul (mis. status
    // pendaftaran diubah staf saat halaman wali sudah terlanjur terbuka), jadi
    // dibikin melayang & hilang sendiri: nggak menggeser layout halaman.
    const [notif, setNotif] = useState<{ pesan: string; tipe: 'error' | 'success' } | null>(null);

    useEffect(() => {
        if (flash?.error) setNotif({ pesan: flash.error, tipe: 'error' });
        else if (flash?.success) setNotif({ pesan: flash.success, tipe: 'success' });
        else return;

        const timer = setTimeout(() => setNotif(null), 6000);

        return () => clearTimeout(timer);
    }, [flash?.error, flash?.success]);

    // Di bawah lg sidebar jadi laci geser. Wali murid mayoritas mendaftar lewat
    // ponsel - sidebar tetap 288px bakal memakan 74% layar 390px.
    const [laciTerbuka, setLaciTerbuka] = useState(false);

    // Tutup laci tiap pindah halaman, biar nggak menghalangi konten tujuan.
    useEffect(() => setLaciTerbuka(false), [url]);

    return (
        <div className="flex h-screen bg-[#F5F9FD]">
            <Head>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=fraunces:600" rel="stylesheet" />
            </Head>

            {/* Latar gelap di belakang laci - hanya pada layar kecil */}
            {laciTerbuka && (
                <button
                    type="button"
                    aria-label="Tutup menu"
                    onClick={() => setLaciTerbuka(false)}
                    className="fixed inset-0 z-30 bg-[#0A3981]/40 lg:hidden"
                />
            )}

            {/* Sidebar - utuh dari atas ke bawah; jadi laci geser di bawah lg */}
            <div
                className={
                    'fixed inset-y-0 left-0 z-40 flex h-screen w-72 shrink-0 flex-col border-r border-[#D4EBF8] bg-white shadow-[2px_0_12px_-4px_rgba(10,57,129,0.08)] transition-transform duration-200 lg:static lg:translate-x-0 ' +
                    (laciTerbuka ? 'translate-x-0' : '-translate-x-full')
                }
            >
                {/* Logo di tengah atas */}
                <div className="flex flex-col items-center gap-2.5 pt-12 pb-8">
                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-white p-1 shadow-md ring-4 ring-white ring-offset-2 ring-offset-[#D4EBF8]">
                        <img src={logoAlFath} alt="Logo SDIT Al-Fath" className="h-full w-full rounded-full object-cover" />
                    </div>
                    <div className="text-center">
                        <div style={{ fontFamily: 'Fraunces, serif' }} className="text-[18px] leading-tight font-semibold text-[#0A3981]">
                            SDIT Al-Fath
                        </div>
                        <div className="mt-0.5 flex items-center justify-center gap-1.5 text-[11px] text-gray-500">
                            <span className="h-px w-4 bg-[#E38E49]/50" />
                            Sistem Informasi PPDB
                            <span className="h-px w-4 bg-[#E38E49]/50" />
                        </div>
                    </div>
                </div>

                {/* Menu */}
                <nav className="flex-1 overflow-y-auto px-3 py-5">
                    {menuItems.map((item) =>
                        item.anak ? <MenuLipat key={item.href} item={item} url={url} /> : <MenuTunggal key={item.href} item={item} url={url} />,
                    )}
                </nav>

                {/* Footer sidebar - blok identitas yang membuka menu akun.
                    Pengaturan Akun SENGAJA tidak ikut daftar menu di atas: yang
                    di atas fitur PPDB (Beranda, Pendaftaran, Verifikasi), yang di
                    sini urusan akun orangnya sendiri. Menaruhnya sederet bikin
                    dua golongan berbeda terbaca setara.

                    Keluar ikut pindah ke dalam menu ini. Jadi dua klik, dan itu
                    ditukar dengan satu keuntungan: dulu dia cuma ikon tanpa
                    tulisan yang artinya harus ditebak. */}
                <div className="border-t border-[#D4EBF8] p-3">
                    <DropdownMenu>
                        <DropdownMenuTrigger className="flex w-full items-center gap-2.5 rounded-xl px-2 py-2 text-left transition-colors hover:bg-[#F5F9FD] focus:outline-none data-[state=open]:bg-[#F5F9FD]">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#1F509A] text-xs font-semibold text-white">
                                {auth.user?.name?.charAt(0).toUpperCase() ?? '?'}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium text-[#0A3981]">{auth.user?.name}</div>
                                <div className="truncate text-xs text-gray-500">{roleLabel[role] ?? role}</div>
                            </div>
                            {/* Satu-satunya tanda bahwa blok ini bisa diklik.
                                Tanpa dia, blok identitas kelihatan seperti
                                keterangan biasa dan menunya tidak pernah ketemu. */}
                            <ChevronsUpDown size={16} strokeWidth={1.8} className="shrink-0 text-gray-500" />
                        </DropdownMenuTrigger>

                        {/* side="top": sidebar-nya mentok ke dasar layar, jadi
                            menu yang membuka ke bawah akan terpotong. */}
                        <DropdownMenuContent side="top" align="start" sideOffset={8} className="w-64 rounded-xl">
                            {/* Email tampil DI SINI saja, bukan di blok pemicunya:
                                di sana ruangnya cuma cukup untuk nama, dan email
                                yang terpotong tengah jalan lebih buruk daripada
                                tidak ditampilkan. */}
                            <div className="px-2 py-1.5">
                                <p className="truncate text-sm font-medium text-[#0A3981]">{auth.user?.name}</p>
                                <p className="truncate text-xs text-gray-500">{auth.user?.email}</p>
                            </div>

                            <DropdownMenuSeparator />

                            <DropdownMenuItem asChild className="cursor-pointer rounded-lg">
                                <Link href="/settings/profile" className="flex items-center gap-2.5">
                                    <Settings size={16} strokeWidth={1.8} className="text-gray-500" />
                                    Pengaturan Akun
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                onSelect={() => router.post(route('logout'))}
                                className="cursor-pointer rounded-lg text-red-700 focus:bg-red-50 focus:text-red-800"
                            >
                                <LogOut size={16} strokeWidth={1.8} />
                                Keluar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            {/* Kolom kanan: bilah atas (hanya layar kecil) + konten */}
            <div className="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <div className="sticky top-0 z-20 flex items-center gap-3 border-b border-[#D4EBF8] bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
                    <button
                        type="button"
                        onClick={() => setLaciTerbuka(true)}
                        aria-label="Buka menu"
                        className="rounded-lg p-2 text-[#1F509A] hover:bg-[#F5F9FD]"
                    >
                        <Menu size={20} strokeWidth={1.8} />
                    </button>
                    <span style={{ fontFamily: 'Fraunces, serif' }} className="text-[15px] font-semibold text-[#0A3981]">
                        SDIT Al-Fath
                    </span>
                </div>
                {children}
            </div>

            {/* Toast melayang di atas layout - sengaja fixed, bukan bagian dari
                aliran halaman, biar munculnya nggak menggeser konten apa pun. */}
            {notif && (
                <div
                    role="status"
                    className={
                        'fixed top-5 right-5 z-50 flex max-w-md items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg ' +
                        (notif.tipe === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700')
                    }
                >
                    <span className="flex-1">{notif.pesan}</span>
                    <button
                        onClick={() => setNotif(null)}
                        className="shrink-0 font-semibold opacity-60 hover:opacity-100"
                        aria-label="Tutup notifikasi"
                    >
                        ✕
                    </button>
                </div>
            )}
        </div>
    );
}
