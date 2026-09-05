import PageContainer from '@/components/page-container';
import { type ReactNode } from 'react';

/**
 * Kepala halaman Beranda - ciri khas visual aplikasi ini, dipakai semua peran.
 *
 * SATU blok dua lapis, sengaja di luar PageContainer supaya melebar penuh
 * sampai tepi area konten tanpa sisa putih di kiri-kanan. Lapis navy membawa
 * sapaan, lapis biru langit membawa keterangan konteks - dempet tanpa jarak,
 * dan cuma sudut bawah blok gabungannya yang dibulatkan, jadi sambungannya
 * rata tanpa lengkung ganda.
 *
 * Ini satu-satunya elemen halaman yang melebar penuh; sisanya tetap lewat
 * PageContainer. Isinya sendiri tetap dibungkus PageContainer `flush` supaya
 * sebaris dengan kartu-kartu di bawahnya - kalau tidak, ada dua garis tepi kiri
 * yang berbeda dalam satu halaman dan itu kelihatan salah walau susah ditunjuk.
 */
export default function PageBanner({
    ikon,
    tanggal,
    judul,
    subjudul,
    strip,
    stripVarian = 'biru',
}: {
    /** Ikon besar yang dipotong di pojok kanan bawah - penanda peran. */
    ikon: ReactNode;
    tanggal: string;
    judul: string;
    subjudul: string;
    /** Isi lapis kedua. Kosongkan kalau halamannya tidak punya keterangan konteks. */
    strip?: ReactNode;
    /** `abu` dipakai waktu stripnya membawa kabar kosong, bukan konteks aktif. */
    stripVarian?: 'biru' | 'abu';
}) {
    return (
        /* `shrink-0` WAJIB, jangan dihapus. AppLayout membungkus isi halaman
           dalam flex-col setinggi layar, jadi blok ini anak langsungnya. CSS
           cuma memberlakukan min-height:auto pada flex item yang overflow-nya
           `visible` - begitu diberi overflow-hidden (dipakai buat memotong ikon
           di garis sambung), batas minimumnya jadi 0 dan flex memerasnya sampai
           setinggi nol begitu isi halaman panjang. Banner-nya nggak hilang dari
           DOM, cuma tergencet habis sampai nggak kelihatan. */
        <div className="shrink-0 overflow-hidden rounded-b-2xl">
            <div className="relative overflow-hidden bg-gradient-to-br from-[#0A3981] to-[#1F509A] py-7 sm:py-14">
                <div aria-hidden className="pointer-events-none absolute -top-14 -right-10 h-44 w-44 rounded-full bg-white/10" />
                <div aria-hidden className="pointer-events-none absolute right-24 -bottom-16 h-28 w-28 rounded-full bg-white/5" />
                <div aria-hidden className="pointer-events-none absolute -right-5 -bottom-8 hidden text-white/10 sm:block">
                    {ikon}
                </div>

                <PageContainer wide flush>
                    <div className="relative max-w-lg">
                        <p className="text-xs font-semibold tracking-wide text-[#D4EBF8]/80 uppercase">{tanggal}</p>
                        <h1 className="mt-1 text-2xl font-bold text-white">{judul}</h1>
                        <p className="mt-1.5 text-sm text-[#D4EBF8]">{subjudul}</p>
                    </div>
                </PageContainer>
            </div>

            {strip && (
                <div className={stripVarian === 'biru' ? 'bg-[#D4EBF8] py-2.5' : 'bg-[#F5F9FD] py-2.5'}>
                    <PageContainer wide flush>{strip}</PageContainer>
                </div>
            )}
        </div>
    );
}
