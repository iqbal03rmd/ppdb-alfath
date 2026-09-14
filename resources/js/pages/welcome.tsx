import logoAlFath from '@/assets/logo-alfath.jpg';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Backpack,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    FileText,
    Heart,
    MessageCircle,
    ShieldCheck,
    Sparkles,
    UploadCloud,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';

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
        ikon: <UserPlus size={22} strokeWidth={1.8} />,
        judul: 'Registrasi',
        teks: 'Buat akun wali murid untuk memulai dan menyimpan seluruh proses pendaftaran.',
        warna: 'bg-[#E8F4FC] text-[#1F509A]',
    },
    {
        ikon: <FileText size={22} strokeWidth={1.8} />,
        judul: 'Formulir',
        teks: 'Lengkapi data calon peserta didik dan wali dengan tenang dari rumah.',
        warna: 'bg-[#F0EDFF] text-[#6652A3]',
    },
    {
        ikon: <UploadCloud size={22} strokeWidth={1.8} />,
        judul: 'Unggah Berkas',
        teks: 'Unggah dokumen sesuai jalur pendaftaran untuk diperiksa oleh sekolah.',
        warna: 'bg-[#FFF0DF] text-[#C96D25]',
    },
    {
        ikon: <Wallet size={22} strokeWidth={1.8} />,
        judul: 'Pembayaran',
        teks: 'Bayar biaya PPDB setelah berkas dinyatakan lengkap, lalu pantau progresnya.',
        warna: 'bg-[#E8F7EE] text-[#238154]',
    },
];

const kemudahan = [
    {
        ikon: <Users size={20} strokeWidth={1.8} />,
        judul: 'Satu akun keluarga',
        teks: 'Kelola pendaftaran anak dalam satu tempat.',
    },
    {
        ikon: <ShieldCheck size={20} strokeWidth={1.8} />,
        judul: 'Proses lebih jelas',
        teks: 'Setiap tahap dan status mudah dipantau.',
    },
    {
        ikon: <MessageCircle size={20} strokeWidth={1.8} />,
        judul: 'Mudah bertanya',
        teks: 'Hubungi sekolah saat butuh bantuan.',
    },
];

/**
 * Ilustrasi asli berbasis SVG agar ringan, tajam di semua layar, dan warnanya
 * benar-benar mengikuti identitas Al-Fath. Tidak memakai foto stok berarti
 * halaman juga tidak membuat asumsi tentang seragam atau bangunan sekolah.
 */
function IlustrasiSekolah() {
    return (
        <svg viewBox="0 0 620 500" className="h-auto w-full" role="img" aria-labelledby="judul-ilustrasi deskripsi-ilustrasi">
            <title id="judul-ilustrasi">Ilustrasi anak bersiap masuk sekolah dasar</title>
            <desc id="deskripsi-ilustrasi">Gedung sekolah dengan dua anak, pepohonan, buku, dan suasana pagi yang cerah.</desc>
            <defs>
                <linearGradient id="langit" x1="65" y1="40" x2="548" y2="450" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#DDF2FF" />
                    <stop offset="1" stopColor="#FFF2D9" />
                </linearGradient>
                <linearGradient id="halaman" x1="310" y1="354" x2="310" y2="476" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#CDEDD7" />
                    <stop offset="1" stopColor="#A9DEBB" />
                </linearGradient>
            </defs>

            <path
                d="M74 54C137 13 223 35 294 29c93-8 193-35 244 39 46 67 19 154 30 230 10 68 35 139-17 178-48 36-117-5-179-2-79 4-157 38-222 2-67-37-71-121-91-191C37 206 5 99 74 54Z"
                fill="url(#langit)"
            />
            <circle cx="492" cy="101" r="38" fill="#FFD36E" />
            <g fill="#fff" opacity=".92">
                <path d="M91 133c4-19 32-22 41-6 13-10 36-1 35 17H88c-7 0-8-10 3-11Z" />
                <path d="M422 166c5-22 37-25 47-7 16-12 42-1 41 20h-92c-8 0-9-12 4-13Z" />
            </g>
            <path d="M39 379c89-44 173-20 250-2 93 21 181-7 292 4v95H46l-7-97Z" fill="url(#halaman)" />
            <path d="M51 422c101-22 196-6 287 8 80 12 157 9 234-2" fill="none" stroke="#8FCCAA" strokeWidth="5" strokeLinecap="round" />

            <g stroke="#0A3981" strokeLinecap="round" strokeLinejoin="round">
                <path d="M304 83v60" strokeWidth="7" />
                <path d="m310 86 55 15-55 17V86Z" fill="#E38E49" strokeWidth="5" />
                <path d="m155 202 151-99 154 99" fill="#E38E49" strokeWidth="8" />
                <path d="M180 192h254v183H180V192Z" fill="#FFFDF7" strokeWidth="8" />
                <path d="M226 162h159v47H226v-47Z" fill="#F5B96E" strokeWidth="7" />
                <text x="305" y="192" textAnchor="middle" fill="#0A3981" stroke="none" fontSize="19" fontWeight="800" fontFamily="Nunito, sans-serif">
                    SEKOLAH
                </text>
                <path d="M278 281h56v94h-56v-94Z" fill="#1F509A" strokeWidth="7" />
                <circle cx="321" cy="329" r="4" fill="#FFD36E" stroke="none" />
                <g fill="#DDF2FF" strokeWidth="6">
                    <path d="M208 237h43v48h-43v-48Z" />
                    <path d="M361 237h43v48h-43v-48Z" />
                    <path d="M208 310h43v42h-43v-42Z" />
                    <path d="M361 310h43v42h-43v-42Z" />
                </g>
                <g strokeWidth="4">
                    <path d="M229 239v44M210 260h39" />
                    <path d="M382 239v44M363 260h39" />
                    <path d="M229 312v38M210 331h39" />
                    <path d="M382 312v38M363 331h39" />
                </g>
            </g>

            <g>
                <path d="M115 279v105" stroke="#8A5B3D" strokeWidth="12" strokeLinecap="round" />
                <circle cx="115" cy="266" r="48" fill="#5DB47A" />
                <circle cx="83" cy="282" r="30" fill="#72C78D" />
                <circle cx="147" cy="284" r="31" fill="#72C78D" />
                <path d="M480 288v97" stroke="#8A5B3D" strokeWidth="11" strokeLinecap="round" />
                <circle cx="480" cy="275" r="43" fill="#5DB47A" />
                <circle cx="452" cy="291" r="27" fill="#72C78D" />
                <circle cx="508" cy="292" r="28" fill="#72C78D" />
            </g>

            <g stroke="#0A3981" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="168" cy="347" r="25" fill="#F4B889" strokeWidth="5" />
                <path d="M143 342c3-29 44-34 52-4-8-8-18-10-27-10-10 0-19 5-25 14Z" fill="#303B59" strokeWidth="4" />
                <path d="M145 385c12-16 37-16 49 0l11 53h-71l11-53Z" fill="#E38E49" strokeWidth="5" />
                <path d="M150 438v31M190 438v31M141 470h18M182 470h18" strokeWidth="6" />
                <path d="m146 393-24 31M193 393l25 27" strokeWidth="6" />
                <path d="M136 386c-13 6-18 26-13 39" fill="none" stroke="#1F509A" strokeWidth="7" />
            </g>
            <g stroke="#0A3981" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="447" cy="348" r="25" fill="#D89B70" strokeWidth="5" />
                <path d="M423 340c4-29 43-31 50-2-14-7-33-7-50 2Z" fill="#303B59" strokeWidth="4" />
                <path d="M422 385c11-15 38-15 50 0l8 54h-66l8-54Z" fill="#1F509A" strokeWidth="5" />
                <path d="M424 439v30M469 439v30M414 470h19M460 470h19" strokeWidth="6" />
                <path d="m422 394-25 27M470 394l22 30" strokeWidth="6" />
                <path d="M469 385c14 7 19 26 13 40" fill="none" stroke="#E38E49" strokeWidth="7" />
            </g>

            <g transform="rotate(-8 506 377)" stroke="#0A3981" strokeWidth="4" strokeLinejoin="round">
                <path d="M491 352h39v50h-39v-50Z" fill="#FFD36E" />
                <path d="M510 352v50" />
                <path d="M496 363h9M515 363h9" strokeLinecap="round" />
            </g>
            <g fill="#E38E49">
                <path d="m83 198 5 11 12 2-9 8 2 12-10-6-11 6 3-12-9-8 12-2 5-11Z" />
                <path d="m535 223 3 8 9 1-7 6 2 9-7-5-8 5 2-9-6-6 8-1 4-8Z" />
            </g>
        </svg>
    );
}

export default function Welcome({ pengaturan, gelombang }: LandingProps) {
    const { auth } = usePage<SharedData>().props;
    const tujuanUtama = auth.user ? (auth.home_url ?? '/') : route('register');
    const labelUtama = auth.user ? 'Buka Beranda' : gelombang ? 'Daftar Sekarang' : 'Buat Akun Wali';

    return (
        <div className="min-h-screen bg-[#FFFCF6] text-gray-900" style={{ fontFamily: 'Nunito, sans-serif' }}>
            <Head title={`PPDB ${pengaturan.nama_sekolah}`}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=fraunces:600,700|nunito:400,500,600,700,800" rel="stylesheet" />
            </Head>

            <header className="relative z-30 border-b border-[#D4EBF8]/80 bg-white/90 backdrop-blur-md">
                <PageContainer wide flush>
                    <div className="flex min-h-20 items-center justify-between gap-4 py-3">
                        <Link href="/" className="flex min-w-0 items-center gap-3" aria-label={`Beranda ${pengaturan.nama_sekolah}`}>
                            <img
                                src={logoAlFath}
                                alt={`Logo ${pengaturan.nama_sekolah}`}
                                className="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-[#D4EBF8]"
                            />
                            <div className="min-w-0">
                                <p style={{ fontFamily: 'Fraunces, serif' }} className="truncate text-lg font-semibold text-[#0A3981]">
                                    {pengaturan.nama_sekolah}
                                </p>
                                <p className="hidden truncate text-xs font-medium text-gray-500 sm:block">Penerimaan Peserta Didik Baru</p>
                            </div>
                        </Link>

                        <nav className="flex shrink-0 items-center gap-1 sm:gap-2" aria-label="Navigasi utama">
                            <a
                                href="#alur"
                                className="hidden rounded-xl px-4 py-2 text-sm font-bold text-[#1F509A] transition-colors hover:bg-[#F5F9FD] md:block"
                            >
                                Alur PPDB
                            </a>
                            {!auth.user && (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="rounded-xl font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                >
                                    <Link href={route('login')}>Masuk</Link>
                                </Button>
                            )}
                            <Button asChild className="rounded-xl bg-[#E38E49] px-5 font-bold text-white shadow-sm hover:bg-[#D97D37]">
                                <Link href={tujuanUtama}>{auth.user ? 'Beranda' : 'Daftar'}</Link>
                            </Button>
                        </nav>
                    </div>
                </PageContainer>
            </header>

            <main>
                <section className="relative overflow-hidden border-b border-[#F1DFC1] bg-[linear-gradient(135deg,#FFF9EC_0%,#F5FAFF_58%,#EAF5FC_100%)] py-14 sm:py-20 lg:py-24">
                    <div aria-hidden className="absolute top-20 -left-16 h-48 w-48 rounded-full border-[28px] border-[#E38E49]/8" />
                    <div aria-hidden className="absolute right-[7%] -bottom-24 h-56 w-56 rounded-full bg-[#FFD36E]/15 blur-2xl" />

                    <PageContainer wide flush>
                        <div className="relative grid items-center gap-12 lg:grid-cols-[1.02fr_0.98fr] lg:gap-14">
                            <div className="relative z-10">
                                {pengaturan.pengumuman_landing && (
                                    <div className="mb-6 inline-flex max-w-xl items-start gap-2.5 rounded-full border border-[#E8CFA8] bg-white/80 px-4 py-2.5 text-sm font-semibold text-[#8A552A] shadow-sm backdrop-blur">
                                        <Sparkles className="mt-0.5 shrink-0 text-[#E38E49]" size={17} strokeWidth={2} />
                                        <span>{pengaturan.pengumuman_landing}</span>
                                    </div>
                                )}
                                <p className="flex items-center gap-2 text-sm font-extrabold tracking-[0.16em] text-[#1F509A] uppercase">
                                    <span className="h-2 w-2 rounded-full bg-[#E38E49]" /> PPDB {pengaturan.nama_sekolah}
                                </p>
                                <h1
                                    style={{ fontFamily: 'Fraunces, serif' }}
                                    className="mt-4 max-w-3xl text-4xl leading-[1.13] font-bold text-[#0A3981] sm:text-5xl lg:text-[3.45rem]"
                                >
                                    {pengaturan.judul_landing}
                                </h1>
                                <p className="mt-5 max-w-2xl text-base leading-7 text-[#52657B] sm:text-lg sm:leading-8">
                                    {pengaturan.deskripsi_landing}
                                </p>
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button
                                        asChild
                                        size="lg"
                                        className="h-12 rounded-xl bg-[#E38E49] px-6 font-extrabold text-white shadow-[0_8px_20px_-8px_rgba(227,142,73,0.9)] hover:bg-[#D97D37]"
                                    >
                                        <Link href={tujuanUtama}>
                                            {labelUtama}
                                            <ArrowRight size={17} strokeWidth={2.4} />
                                        </Link>
                                    </Button>
                                    {pengaturan.whatsapp_url && (
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="h-12 rounded-xl border-[#1F509A]/30 bg-white/70 px-6 font-bold text-[#1F509A] hover:bg-white hover:text-[#0A3981]"
                                        >
                                            <a href={pengaturan.whatsapp_url} target="_blank" rel="noopener noreferrer">
                                                <MessageCircle size={17} strokeWidth={2} /> Tanya PPDB
                                            </a>
                                        </Button>
                                    )}
                                </div>
                                <div className="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-[#52657B]">
                                    <span className="flex items-center gap-1.5">
                                        <CheckCircle2 size={16} className="text-[#238154]" /> Proses jelas
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <CheckCircle2 size={16} className="text-[#238154]" /> Bisa dipantau dari rumah
                                    </span>
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-[590px] pb-14 lg:pb-10">
                                <div className="relative overflow-hidden rounded-[2.25rem] border border-white/80 bg-white/55 p-3 shadow-[0_28px_70px_-28px_rgba(10,57,129,0.35)] backdrop-blur-sm sm:p-5">
                                    <IlustrasiSekolah />
                                </div>
                                <div className="absolute right-2 bottom-0 left-2 rounded-2xl border border-[#D4EBF8] bg-white p-4 shadow-[0_16px_35px_-18px_rgba(10,57,129,0.45)] sm:right-5 sm:left-auto sm:w-[315px] sm:p-5">
                                    <div className="flex items-start gap-3">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E8F4FC] text-[#1F509A]">
                                            <CalendarDays size={21} strokeWidth={1.9} />
                                        </span>
                                        {gelombang ? (
                                            <div className="min-w-0">
                                                <p className="text-xs font-extrabold tracking-wide text-[#238154] uppercase">Pendaftaran dibuka</p>
                                                <p className="mt-0.5 truncate font-extrabold text-[#0A3981]">{gelombang.nama}</p>
                                                <p className="mt-1 text-xs leading-5 text-gray-500">
                                                    {gelombang.tanggal_mulai} – {gelombang.tanggal_selesai}
                                                    <br />
                                                    Tahun Ajaran {gelombang.tahun_ajaran}
                                                </p>
                                            </div>
                                        ) : (
                                            <div>
                                                <p className="text-xs font-extrabold tracking-wide text-gray-500 uppercase">Informasi pendaftaran</p>
                                                <p className="mt-1 text-sm font-extrabold text-[#0A3981]">Belum ada gelombang yang dibuka</p>
                                                <p className="mt-1 text-xs leading-5 text-gray-500">
                                                    Jadwal berikutnya akan tampil setelah ditetapkan sekolah.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <span className="absolute -top-4 -right-2 flex h-12 w-12 rotate-6 items-center justify-center rounded-2xl bg-[#FFD36E] text-[#8A552A] shadow-lg sm:-right-5">
                                    <BookOpen size={22} strokeWidth={1.8} />
                                </span>
                                <span className="absolute bottom-24 -left-3 hidden h-12 w-12 -rotate-6 items-center justify-center rounded-2xl bg-[#1F509A] text-white shadow-lg sm:flex">
                                    <Backpack size={22} strokeWidth={1.8} />
                                </span>
                            </div>
                        </div>
                    </PageContainer>
                </section>

                <section className="bg-[#0A3981] py-7">
                    <PageContainer wide flush>
                        <div className="grid gap-5 sm:grid-cols-3 sm:divide-x sm:divide-white/15">
                            {kemudahan.map((item) => (
                                <div key={item.judul} className="flex items-center gap-3 px-2 sm:px-5 first:sm:pl-0 last:sm:pr-0">
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-[#FFD7A6]">
                                        {item.ikon}
                                    </span>
                                    <div>
                                        <p className="text-sm font-extrabold text-white">{item.judul}</p>
                                        <p className="mt-0.5 text-xs leading-5 text-[#D4EBF8]">{item.teks}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </PageContainer>
                </section>

                <section id="alur" className="scroll-mt-6 py-16 sm:py-20">
                    <PageContainer wide flush>
                        <div className="mx-auto max-w-2xl text-center">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-[#E38E49] uppercase">Alur pendaftaran</p>
                            <h2 style={{ fontFamily: 'Fraunces, serif' }} className="mt-3 text-3xl font-bold text-[#0A3981] sm:text-4xl">
                                Empat langkah menuju hari pertama sekolah
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-[#63758A] sm:text-base">
                                Orang tua mengurus seluruh proses secara daring, sementara sekolah memeriksa setiap tahapnya.
                            </p>
                        </div>
                        <div className="relative mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div
                                aria-hidden
                                className="absolute top-8 right-[12%] left-[12%] hidden border-t-2 border-dashed border-[#D4EBF8] lg:block"
                            />
                            {langkah.map((item, index) => (
                                <div
                                    key={item.judul}
                                    className="relative rounded-3xl border border-[#E7EEF5] bg-white p-6 shadow-[0_12px_35px_-24px_rgba(10,57,129,0.4)] transition-transform duration-200 hover:-translate-y-1"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className={`relative z-10 flex h-14 w-14 items-center justify-center rounded-2xl ${item.warna}`}>
                                            {item.ikon}
                                        </span>
                                        <span style={{ fontFamily: 'Fraunces, serif' }} className="text-4xl font-bold text-[#E2EDF5]">
                                            0{index + 1}
                                        </span>
                                    </div>
                                    <h3 className="mt-6 text-lg font-extrabold text-[#173B68]">{item.judul}</h3>
                                    <p className="mt-2 text-sm leading-6 text-[#63758A]">{item.teks}</p>
                                </div>
                            ))}
                        </div>
                    </PageContainer>
                </section>

                <section className="pb-16 sm:pb-20">
                    <PageContainer wide flush>
                        <div className="relative overflow-hidden rounded-[2rem] bg-[#DFF1FB] px-6 py-10 sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-10 lg:px-14">
                            <div aria-hidden className="absolute -top-12 -right-10 h-44 w-44 rounded-full bg-white/40" />
                            <div aria-hidden className="absolute -bottom-14 left-1/3 h-32 w-32 rounded-full border-[20px] border-[#E38E49]/10" />
                            <div className="relative max-w-2xl">
                                <span className="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-[#E38E49] shadow-sm">
                                    <Heart size={21} strokeWidth={1.9} />
                                </span>
                                <h2 style={{ fontFamily: 'Fraunces, serif' }} className="text-2xl font-bold text-[#0A3981] sm:text-3xl">
                                    Siap memulai langkah baru bersama {pengaturan.nama_sekolah}?
                                </h2>
                                <p className="mt-3 text-sm leading-6 text-[#52657B]">
                                    Buat akun wali untuk memulai pendaftaran atau hubungi sekolah jika masih ada yang ingin ditanyakan.
                                </p>
                            </div>
                            <div className="relative mt-7 flex shrink-0 flex-wrap gap-3 lg:mt-0">
                                <Button asChild size="lg" className="rounded-xl bg-[#0A3981] px-6 font-extrabold text-white hover:bg-[#1F509A]">
                                    <Link href={tujuanUtama}>
                                        {labelUtama}
                                        <ArrowRight size={17} />
                                    </Link>
                                </Button>
                                {pengaturan.whatsapp_url && (
                                    <Button
                                        asChild
                                        size="lg"
                                        variant="outline"
                                        className="rounded-xl border-white bg-white font-bold text-[#1F509A] hover:bg-white/80 hover:text-[#0A3981]"
                                    >
                                        <a href={pengaturan.whatsapp_url} target="_blank" rel="noopener noreferrer">
                                            <MessageCircle size={17} /> Tanya Sekolah
                                        </a>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </PageContainer>
                </section>
            </main>

            <footer className="border-t border-[#D4EBF8] bg-white py-8">
                <PageContainer wide flush>
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div className="flex items-start gap-3">
                            <img src={logoAlFath} alt="" className="h-11 w-11 rounded-full object-cover ring-1 ring-[#D4EBF8]" />
                            <div>
                                <p style={{ fontFamily: 'Fraunces, serif' }} className="font-semibold text-[#0A3981]">
                                    {pengaturan.nama_sekolah}
                                </p>
                                {pengaturan.tagline && <p className="mt-1 text-sm text-gray-500">{pengaturan.tagline}</p>}
                                {pengaturan.alamat && <p className="mt-1 max-w-xl text-sm text-gray-500">{pengaturan.alamat}</p>}
                            </div>
                        </div>
                        <div className="text-sm leading-6 text-gray-500 sm:text-right">
                            {pengaturan.telepon && <p>{pengaturan.telepon}</p>}
                            {pengaturan.email && <p>{pengaturan.email}</p>}
                            {pengaturan.whatsapp_kontak && <p>WhatsApp: {pengaturan.whatsapp_kontak}</p>}
                            <p className="mt-2 text-xs text-gray-400">
                                © {new Date().getFullYear()} {pengaturan.nama_sekolah}
                            </p>
                        </div>
                    </div>
                </PageContainer>
            </footer>
        </div>
    );
}
