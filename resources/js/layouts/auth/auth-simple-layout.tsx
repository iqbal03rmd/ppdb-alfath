import { Head, Link } from '@inertiajs/react';
import logoAlFath from '@/assets/logo-alfath.jpg';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh items-center justify-center bg-[#F5F9FD] p-4 md:p-10">
            <Head>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=fraunces:600" rel="stylesheet" />
            </Head>

            {/* Satu kotak besar, dua kolom di dalamnya, ngambang di atas background */}
            <div className="flex w-full max-w-6xl overflow-hidden rounded-3xl bg-white shadow-[0_8px_40px_-8px_rgba(10,57,129,0.25)]">
                {/* Kolom kiri - branding, disembunyikan di layar kecil */}
                <div className="relative hidden w-[42%] shrink-0 items-center justify-center overflow-hidden bg-[#0A3981] lg:flex">
                    <div className="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-[#E38E49]/10 blur-3xl" />
                    <div className="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-[#D4EBF8]/10 blur-3xl" />

                    <div className="relative z-10 flex flex-col items-center gap-5 px-10 text-center">
                        <div className="flex h-32 w-32 items-center justify-center rounded-full bg-white p-2 shadow-xl ring-4 ring-white/20">
                            <img src={logoAlFath} alt="Logo SDIT Al-Fath" className="h-full w-full rounded-full object-cover" />
                        </div>
                        <div>
                            <div style={{ fontFamily: 'Fraunces, serif' }} className="text-2xl font-semibold text-white">
                                SDIT Al-Fath Pekanbaru
                            </div>
                            <div className="mt-1 text-sm text-[#D4EBF8]">Sistem Informasi PPDB</div>
                        </div>
                        {/* <p className="max-w-xs text-sm leading-relaxed text-white/70">
                            Daftar, pantau, dan selesaikan proses Penerimaan Peserta Didik Baru anak Anda secara online.
                        </p> */}
                    </div>
                </div>

                {/* Kolom kanan - form */}
                <div className="flex flex-1 flex-col items-center justify-center p-8 md:p-14">
                    <div className="w-full max-w-md">
                        {/* Logo kompak, cuma muncul di layar kecil (panel kiri disembunyikan) */}
                        <div className="mb-6 flex flex-col items-center gap-2 lg:hidden">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-white p-1 shadow-md ring-2 ring-[#D4EBF8]">
                                <img src={logoAlFath} alt="Logo SDIT Al-Fath" className="h-full w-full rounded-full object-cover" />
                            </div>
                            <div style={{ fontFamily: 'Fraunces, serif' }} className="text-[15px] font-semibold text-[#0A3981]">
                                SDIT Al-Fath Pekanbaru
                            </div>
                        </div>

                        <Link href={route('home')} className="hidden">
                            {title}
                        </Link>

                        <div className="mb-8 space-y-1">
                            <h1 className="text-xl font-semibold text-[#0A3981]">{title}</h1>
                            <p className="text-sm text-gray-500">{description}</p>
                        </div>

                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}