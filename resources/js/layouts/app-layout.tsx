import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';
import logoAlFath from '@/assets/logo-alfath.jpg';
import {
    Home,
    FileText,
    CircleCheckBig,
    Wallet,
    Users,
    ClipboardCheck,
    BarChart3,
    Database,
    Settings,
    LogOut,
} from 'lucide-react';

type MenuItem = { label: string; href: string; icon: ReactNode };

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
    settings: <Settings size={18} strokeWidth={1.8} />,
    signOut: <LogOut size={18} strokeWidth={1.8} />,
};

const menuByRole: Record<string, MenuItem[]> = {
    wali_murid: [
        { label: 'Beranda', href: '/wali-murid/dashboard', icon: Icon.home },
        { label: 'Pendaftaran', href: '/wali-murid/pendaftaran', icon: Icon.file },
        { label: 'Pembayaran', href: '/wali-murid/pembayaran', icon: Icon.wallet },
    ],
    staf_ppdb: [
        { label: 'Beranda', href: '/staf-ppdb/dashboard', icon: Icon.home },
        { label: 'Data Pendaftar', href: '/staf-ppdb/pendaftar', icon: Icon.users },
        { label: 'Verifikasi Berkas', href: '/staf-ppdb/verifikasi-berkas', icon: Icon.clipboardCheck },
        { label: 'Verifikasi Pembayaran', href: '/staf-ppdb/verifikasi-pembayaran', icon: Icon.wallet },
    ],
    kepala_sekolah: [
        { label: 'Beranda', href: '/kepala-sekolah/dashboard', icon: Icon.home },
        { label: 'Rekapitulasi PPDB', href: '/kepala-sekolah/rekapitulasi', icon: Icon.barChart },
    ],
    super_admin: [
        { label: 'Beranda', href: '/super-admin/dashboard', icon: Icon.home },
        { label: 'Kelola Pengguna', href: '/super-admin/pengguna', icon: Icon.users },
        { label: 'Data Master', href: '/super-admin/data-master', icon: Icon.database },
        { label: 'Pengaturan Sistem', href: '/super-admin/pengaturan', icon: Icon.settings },
    ],
};

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<SharedData>().props;
    const { url } = usePage();

    const role = String(auth.user?.role ?? '');
    const menuItems = menuByRole[role] ?? [];

    return (
        <div className="flex h-screen bg-[#F5F9FD]">
            <Head>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=fraunces:600" rel="stylesheet" />
            </Head>

            {/* Sidebar - utuh dari atas ke bawah */}
            <div className="flex h-screen w-72 shrink-0 flex-col border-r border-[#D4EBF8] bg-white shadow-[2px_0_12px_-4px_rgba(10,57,129,0.08)]">
                {/* Logo di tengah atas */}
                <div className="flex flex-col items-center gap-2.5 pt-12 pb-8">
                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-white p-1 shadow-md ring-4 ring-white ring-offset-2 ring-offset-[#D4EBF8]">
                        <img src={logoAlFath} alt="Logo SDIT Al-Fath" className="h-full w-full rounded-full object-cover" />
                    </div>
                    <div className="text-center">
                        <div style={{ fontFamily: 'Fraunces, serif' }} className="text-[18px] leading-tight font-semibold text-[#0A3981]">
                            SDIT Al-Fath
                        </div>
                        <div className="mt-0.5 flex items-center justify-center gap-1.5 text-[10px] text-gray-400">
                            <span className="h-px w-4 bg-[#E38E49]/50" />
                            Sistem Informasi PPDB
                            <span className="h-px w-4 bg-[#E38E49]/50" />
                        </div>
                    </div>
                </div>

                {/* Menu */}
                <nav className="flex-1 overflow-y-auto px-3 py-5">
                    {menuItems.map((item) => {
                        const active = url.startsWith(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={
                                    'mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors ' +
                                    (active ? 'bg-[#0A3981] font-semibold text-white shadow-sm' : 'text-gray-600 hover:bg-[#F5F9FD] hover:text-[#0A3981]')
                                }
                            >
                                <span
                                    className={
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full ' +
                                        (active ? 'bg-[#E38E49] text-white' : 'bg-[#D4EBF8]/60 text-[#1F509A]')
                                    }
                                >
                                    {item.icon}
                                </span>
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* Footer sidebar - identitas user + logout digabung satu baris */}
                <div className="border-t border-[#D4EBF8] p-3">
                    <div className="flex items-center gap-2.5 rounded-xl px-2 py-2">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#1F509A] text-xs font-semibold text-white">
                            {auth.user?.name?.charAt(0).toUpperCase() ?? '?'}
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-medium text-[#0A3981]">{auth.user?.name}</div>
                            <div className="truncate text-[11px] text-gray-400">{roleLabel[role] ?? role}</div>
                        </div>
                        <button
                            onClick={() => router.post(route('logout'))}
                            title="Keluar"
                            className="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                        >
                            {Icon.signOut}
                        </button>
                    </div>
                </div>
            </div>

            {/* Kolom kanan: cuma konten, info user dipindah jadi bagian header tiap halaman */}
            <div className="flex-1 overflow-y-auto">{children}</div>
        </div>
    );
}