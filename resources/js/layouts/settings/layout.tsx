import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Link, usePage } from '@inertiajs/react';

/**
 * Pembungkus dua halaman Pengaturan Akun (Profil & Kata Sandi).
 *
 * Dulu berupa sidebar kecil di kolom kiri isi halaman. Itu dibuang: aplikasi
 * ini sudah punya sidebar sungguhan di sebelah kiri, dan sidebar kedua di
 * dalamnya bikin ada dua daftar menu bertingkat yang bersaing minta dibaca.
 * Dua halaman saja tidak butuh sidebar - tab mendatar cukup, dan letaknya
 * langsung di atas isinya jadi hubungannya jelas.
 *
 * `wide`, seperti hampir semua halaman lain. Lebarnya dipakai lewat pembagian
 * 2:1 yang sama dengan halaman Ubah Pengguna - kiri yang diketik, kanan
 * keterangan. Tanpa panel kanan, satu kartu formulir terentang 1280px dan
 * kolom "Nama Lengkap" jadi selebar layar; `samping` karena itu WAJIB diisi.
 */
const tab = [
    { label: 'Profil', href: '/settings/profile' },
    { label: 'Kata Sandi', href: '/settings/password' },
];

export default function SettingsLayout({ children, samping }: { children: React.ReactNode; samping: React.ReactNode }) {
    // Dari Inertia, bukan window.location.pathname: yang kedua dibaca sekali
    // saat render pertama dan tidak ikut berubah waktu pindah tab lewat
    // navigasi Inertia - tab aktifnya bisa tertinggal di halaman sebelumnya.
    const { url } = usePage();

    return (
        <>
            <PageHeader title="Pengaturan Akun" subtitle="Data akun Anda sendiri dan kata sandi untuk masuk" wide />

            <PageContainer wide>
                <div className="mb-6 flex gap-2 border-b border-[#D4EBF8]">
                    {tab.map((t) => {
                        const aktif = url.startsWith(t.href);

                        return (
                            <Link
                                key={t.href}
                                href={t.href}
                                className={
                                    '-mb-px border-b-2 px-4 py-2.5 text-sm transition-colors ' +
                                    (aktif
                                        ? 'border-[#E38E49] font-semibold text-[#0A3981]'
                                        : 'border-transparent text-gray-500 hover:text-[#0A3981]')
                                }
                            >
                                {t.label}
                            </Link>
                        );
                    })}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">{children}</div>
                    <div className="space-y-6">{samping}</div>
                </div>
            </PageContainer>
        </>
    );
}
