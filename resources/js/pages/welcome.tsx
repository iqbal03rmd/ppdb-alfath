import logoAlFath from '@/assets/logo-alfath.jpg';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarDays, CheckCircle2, FileText, MessageCircle, UploadCloud, Wallet } from 'lucide-react';

interface LandingProps {
    pengaturan: {
        nama_sekolah: string;
        tagline: string | null;
        alamat: string | null;
        telepon: string | null;
        email: string | null;
        judul_landing: string;
        deskripsi_landing: string;
        pengumuman_landing: string | null;
        whatsapp_kontak: string | null;
        whatsapp_url: string | null;
    };
    gelombang: {
        nama: string;
        tahun_ajaran: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
    } | null;
}

const langkah = [
    {
        ikon: <FileText size={21} strokeWidth={1.8} />,
        judul: 'Isi formulir',
        teks: 'Lengkapi data calon peserta didik dan wali dalam satu akun.',
    },
    {
        ikon: <UploadCloud size={21} strokeWidth={1.8} />,
        judul: 'Kirim berkas',
        teks: 'Unggah dokumen sesuai jalur pendaftaran lalu kirim untuk diperiksa.',
    },
    {
        ikon: <Wallet size={21} strokeWidth={1.8} />,
        judul: 'Pantau dan bayar',
        teks: 'Ikuti status pemeriksaan, rincian tagihan, dan progres pembayaran.',
    },
];

export default function Welcome({ pengaturan, gelombang }: LandingProps) {
    const { auth } = usePage<SharedData>().props;
    const tujuanUtama = auth.user ? auth.home_url ?? '/' : route('register');
    const labelUtama = auth.user ? 'Buka Beranda' : gelombang ? 'Daftar Sekarang' : 'Buat Akun Wali';

    return (
        <div className="min-h-screen bg-[#F5F9FD] text-gray-900">
            <Head title={`PPDB ${pengaturan.nama_sekolah}`}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=fraunces:600|instrument-sans:400,500,600,700" rel="stylesheet" />
            </Head>

            <header className="border-b border-[#D4EBF8] bg-white/95 backdrop-blur">
                <PageContainer wide flush>
                    <div className="flex min-h-20 items-center justify-between gap-4 py-3">
                        <div className="flex min-w-0 items-center gap-3">
                            <img
                                src={logoAlFath}
                                alt={`Logo ${pengaturan.nama_sekolah}`}
                                className="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-[#D4EBF8]"
                            />
                            <div className="min-w-0">
                                <p style={{ fontFamily: 'Fraunces, serif' }} className="truncate text-lg font-semibold text-[#0A3981]">
                                    {pengaturan.nama_sekolah}
                                </p>
                                <p className="hidden truncate text-xs text-gray-500 sm:block">Sistem Informasi PPDB</p>
                            </div>
                        </div>

                        <nav className="flex shrink-0 items-center gap-2">
                            {!auth.user && (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="rounded-xl font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                >
                                    <Link href={route('login')}>Masuk</Link>
                                </Button>
                            )}
                            <Button asChild className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                                <Link href={tujuanUtama}>{auth.user ? 'Beranda' : 'Daftar'}</Link>
                            </Button>
                        </nav>
                    </div>
                </PageContainer>
            </header>

            <main>
                <section className="relative overflow-hidden bg-gradient-to-br from-[#0A3981] to-[#1F509A] py-16 sm:py-24">
                    <div aria-hidden className="absolute -top-24 -right-16 h-80 w-80 rounded-full bg-white/8" />
                    <div aria-hidden className="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-[#D4EBF8]/8" />

                    <PageContainer wide flush>
                        <div className="relative grid items-center gap-12 lg:grid-cols-[1.3fr_0.7fr]">
                            <div>
                                {pengaturan.pengumuman_landing && (
                                    <div className="mb-6 inline-flex max-w-xl items-start gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-[#D4EBF8]">
                                        <CheckCircle2 className="mt-0.5 shrink-0" size={17} strokeWidth={2} />
                                        <span>{pengaturan.pengumuman_landing}</span>
                                    </div>
                                )}

                                <p className="text-sm font-semibold tracking-wide text-[#D4EBF8] uppercase">PPDB {pengaturan.nama_sekolah}</p>
                                <h1 className="mt-3 max-w-3xl text-4xl leading-tight font-bold text-white sm:text-5xl">{pengaturan.judul_landing}</h1>
                                <p className="mt-5 max-w-2xl text-base leading-7 text-[#D4EBF8] sm:text-lg">{pengaturan.deskripsi_landing}</p>

                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button asChild size="lg" className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                                        <Link href={tujuanUtama}>
                                            {labelUtama}
                                            <ArrowRight size={17} strokeWidth={2} />
                                        </Link>
                                    </Button>
                                    {pengaturan.whatsapp_url && (
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="rounded-xl border-white/30 bg-white/10 font-semibold text-white hover:bg-white/20 hover:text-white"
                                        >
                                            <a href={pengaturan.whatsapp_url} target="_blank" rel="noopener noreferrer">
                                                <MessageCircle size={17} strokeWidth={2} />
                                                Tanya PPDB
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <div className="rounded-2xl border border-white/15 bg-white p-6 shadow-2xl shadow-[#0A3981]/30 sm:p-8">
                                <span className="flex h-12 w-12 items-center justify-center rounded-full bg-[#D4EBF8]/70 text-[#1F509A]">
                                    <CalendarDays size={23} strokeWidth={1.8} />
                                </span>
                                {gelombang ? (
                                    <>
                                        <p className="mt-5 text-xs font-semibold tracking-wide text-green-700 uppercase">Pendaftaran sedang dibuka</p>
                                        <h2 className="mt-1 text-xl font-bold text-[#0A3981]">{gelombang.nama}</h2>
                                        <p className="mt-1 text-sm text-gray-500">Tahun Ajaran {gelombang.tahun_ajaran}</p>
                                        <div className="mt-5 rounded-xl bg-[#F5F9FD] p-4">
                                            <p className="text-xs text-gray-500">Periode pendaftaran</p>
                                            <p className="mt-1 text-sm font-semibold text-gray-800">
                                                {gelombang.tanggal_mulai} – {gelombang.tanggal_selesai}
                                            </p>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <p className="mt-5 text-xs font-semibold tracking-wide text-gray-500 uppercase">Informasi pendaftaran</p>
                                        <h2 className="mt-1 text-xl font-bold text-[#0A3981]">Belum ada gelombang yang dibuka</h2>
                                        <p className="mt-3 text-sm leading-6 text-gray-500">
                                            Jadwal berikutnya akan muncul di halaman ini setelah ditetapkan sekolah.
                                        </p>
                                    </>
                                )}
                            </div>
                        </div>
                    </PageContainer>
                </section>

                <section className="py-16 sm:py-20">
                    <PageContainer wide flush>
                        <div className="text-center">
                            <p className="text-xs font-semibold tracking-wide text-[#1F509A] uppercase">Alur pendaftaran</p>
                            <h2 className="mt-2 text-2xl font-bold text-[#0A3981]">Satu akun untuk seluruh proses PPDB</h2>
                        </div>
                        <div className="mt-10 grid gap-5 md:grid-cols-3">
                            {langkah.map((item, index) => (
                                <div
                                    key={item.judul}
                                    className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-[#D4EBF8]/70 text-[#1F509A]">
                                            {item.ikon}
                                        </span>
                                        <span className="text-3xl font-bold text-[#D4EBF8]">0{index + 1}</span>
                                    </div>
                                    <h3 className="mt-5 text-base font-semibold text-gray-900">{item.judul}</h3>
                                    <p className="mt-2 text-sm leading-6 text-gray-500">{item.teks}</p>
                                </div>
                            ))}
                        </div>
                    </PageContainer>
                </section>
            </main>

            <footer className="border-t border-[#D4EBF8] bg-white py-8">
                <PageContainer wide flush>
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p style={{ fontFamily: 'Fraunces, serif' }} className="font-semibold text-[#0A3981]">
                                {pengaturan.nama_sekolah}
                            </p>
                            {pengaturan.tagline && <p className="mt-1 text-sm text-gray-500">{pengaturan.tagline}</p>}
                            {pengaturan.alamat && <p className="mt-1 max-w-xl text-sm text-gray-500">{pengaturan.alamat}</p>}
                        </div>
                        <div className="text-sm text-gray-500 sm:text-right">
                            {pengaturan.telepon && <p>{pengaturan.telepon}</p>}
                            {pengaturan.email && <p>{pengaturan.email}</p>}
                            {pengaturan.whatsapp_kontak && <p>WhatsApp: {pengaturan.whatsapp_kontak}</p>}
                        </div>
                    </div>
                </PageContainer>
            </footer>
        </div>
    );
}
