import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';
import logoAlFath from '@/assets/logo-alfath.jpg';

type MenuItem = { label: string; href: string; icon: ReactNode };

const roleLabel: Record<string, string> = {
    wali_murid: 'Wali Murid',
    staf_ppdb: 'Staf PPDB',
    kepala_sekolah: 'Kepala Sekolah',
    super_admin: 'Super Admin',
};

const Icon = {
    home: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <path d="M3 11l9-8 9 8" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M5 10v10h14V10" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
    file: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <path d="M6 2h9l5 5v15H6z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M9 12h6M9 16h6M9 8h2" strokeLinecap="round" />
        </svg>
    ),
    checkCircle: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <circle cx="12" cy="12" r="9" />
            <path d="M8.5 12.5l2.5 2.5 5-5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
    wallet: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <rect x="3" y="6" width="18" height="13" rx="2" />
            <path d="M3 10h18" />
            <circle cx="16.5" cy="14" r="0.6" fill="currentColor" />
        </svg>
    ),
    users: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <circle cx="9" cy="8" r="3" />
            <path d="M2 20c0-3.3 3-6 7-6s7 2.7 7 6" strokeLinecap="round" />
            <path d="M16 4.5c1.7.4 3 2 3 3.5s-1.3 3.1-3 3.5" strokeLinecap="round" />
            <path d="M18.5 14.3c2 .7 3.5 2.7 3.5 5.7" strokeLinecap="round" />
        </svg>
    ),
    clipboardCheck: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <rect x="5" y="4" width="14" height="17" rx="2" />
            <path d="M9 4V3a1 1 0 011-1h4a1 1 0 011 1v1" />
            <path d="M9 12l2 2 4-4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
    barChart: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <path d="M4 20V10M12 20V4M20 20v-7" strokeLinecap="round" />
        </svg>
    ),
    database: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <ellipse cx="12" cy="5" rx="8" ry="3" />
            <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5" />
            <path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
        </svg>
    ),
    settings: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 13a7.7 7.7 0 000-2l2-1.5-2-3.4-2.3.9a7.7 7.7 0 00-1.7-1L15 3h-4l-.4 2.5a7.7 7.7 0 00-1.7 1l-2.3-.9-2 3.4L6.6 11a7.7 7.7 0 000 2l-2 1.5 2 3.4 2.3-.9a7.7 7.7 0 001.7 1L11 21h4l.4-2.5a7.7 7.7 0 001.7-1l2.3.9 2-3.4z" />
        </svg>
    ),
    signOut: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M16 17l5-5-5-5M21 12H9" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
};

const menuByRole: Record<string, MenuItem[]> = {
    wali_murid: [
        { label: 'Beranda', href: '/wali-murid/dashboard', icon: Icon.home },
        { label: 'Formulir', href: '/wali-murid/formulir', icon: Icon.file },
        { label: 'Status Pendaftaran', href: '/wali-murid/status-pendaftaran', icon: Icon.checkCircle },
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