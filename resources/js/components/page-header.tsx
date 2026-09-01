import PageContainer from '@/components/page-container';

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    /**
     * Samakan dengan ragam PageContainer yang dipakai halamannya. Wajib diisi
     * kalau halamannya memakai `<PageContainer wide>` - kalau tidak, judul dan
     * isi halaman berhenti di garis tepi kiri yang berbeda.
     */
    wide?: boolean;
}

/**
 * Judul + subjudul halaman. Lebarnya SELALU mengikuti PageContainer, jadi judul
 * dan isi halaman berbagi satu garis tepi kiri.
 *
 * Dulu komponen ini cuma memakai `px-8` tanpa batas lebar, sementara isi halaman
 * ditengahkan dan dibatasi - di layar 1920 judulnya jadi menggantung 176px (ragam
 * wide) sampai 304px (ragam sempit) di kiri isinya. Dua garis tepi berbeda dalam
 * satu halaman itu kelihatan salah walau susah ditunjuk penyebabnya.
 */
export default function PageHeader({ title, subtitle, wide = false }: PageHeaderProps) {
    return (
        <div className="pt-8 pb-6">
            <PageContainer wide={wide} flush>
                <h1 className="text-2xl font-bold text-[#0A3981]">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
            </PageContainer>
        </div>
    );
}
