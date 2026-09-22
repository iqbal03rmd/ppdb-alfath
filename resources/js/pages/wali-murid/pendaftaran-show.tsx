import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface WaliMuridItem {
    nama: string;
    nik: string;
    hubungan: string;
    telepon: string;
}

interface DokumenItem {
    label: string;
    nama_file: string;
    url: string;
}

interface PendaftaranDetail {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    nik: string | null;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: string;
    alamat: string;
    rt: string | null;
    rw: string | null;
    kelurahan: string | null;
    kecamatan: string | null;
    kota_kabupaten: string | null;
    provinsi: string | null;
    asal_paud: string;
    tahu_dari: string;
    pertanyaan_khusus: string | null;
    jawaban_khusus: string | null;
    kategori: string;
    status: string;
    catatan_verifikasi: string | null;
    tanggal_daftar: string;
    gelombang: string;
}

interface ShowProps {
    pendaftaran: PendaftaranDetail;
    waliMurid: WaliMuridItem[];
    dokumenList: DokumenItem[];
    statusPembayaran: string | null;
    bisaEditBerkas: boolean;
    progres: {
        wali: boolean;
        berkasTerunggah: number;
        berkasWajib: number;
    };
}

function InfoItem({ label, value, className = '' }: { label: string; value: string | null; className?: string }) {
    return (
        <div className={`min-w-0 ${className}`}>
            <div className="text-xs text-gray-500">{label}</div>
            <div className="mt-0.5 text-sm leading-relaxed font-medium break-words text-gray-800">{value || '—'}</div>
        </div>
    );
}

function SectionTitle({ children }: { children: React.ReactNode }) {
    return <h2 className="mb-4 border-b border-gray-100 pb-2 text-sm font-semibold text-[#0A3981]">{children}</h2>;
}

export default function PendaftaranShow({ pendaftaran, waliMurid }: ShowProps) {
    return (
        <AppLayout>
            <Head title={`Detail Pendaftaran - ${pendaftaran.nama_pendaftar}`} />
            <PageHeader title={pendaftaran.nama_pendaftar} subtitle={pendaftaran.nomor_pendaftaran} wide />

            <PageContainer wide>
                <Button
                    asChild
                    variant="outline"
                    size="icon"
                    className="mb-5 rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                >
                    {/* Ikon saja - posisinya di pojok kiri atas sudah menjelaskan
                        maksudnya. aria-label & title diisi supaya pembaca layar
                        dan tooltip tetap menyebutkan tujuannya. */}
                    <Link
                        href={route('wali-murid.pendaftaran.index')}
                        aria-label="Kembali ke daftar pendaftaran"
                        title="Kembali ke daftar pendaftaran"
                    >
                        <ArrowLeft size={18} strokeWidth={2} />
                    </Link>
                </Button>

                {/* Alasan penutupan - hak wali untuk tahu, apalagi kalau dia
                    terlanjur menyetor uang. */}
                {pendaftaran.status === 'ditolak' && (
                    <div className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5">
                        <h3 className="mb-1 text-sm font-semibold text-red-800">Pendaftaran ini ditutup sekolah</h3>
                        <p className="text-sm text-red-700">
                            {pendaftaran.catatan_verifikasi ?? 'Silakan hubungi Staf PPDB untuk penjelasan lebih lanjut.'}
                        </p>
                    </div>
                )}

                {pendaftaran.status === 'perlu_perbaikan' && (
                    <div className="mb-6 flex flex-col items-stretch gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div className="min-w-0">
                            <h3 className="mb-1 text-sm font-semibold text-amber-800">Ada yang perlu diperbaiki</h3>
                            <p className="text-sm text-amber-700">
                                {pendaftaran.catatan_verifikasi ?? 'Staf PPDB meminta perbaikan data. Silakan hubungi sekolah untuk detailnya.'}
                            </p>
                        </div>
                        <Button asChild size="sm" className="w-full shrink-0 rounded-xl bg-amber-600 hover:bg-amber-700 sm:w-auto">
                            <Link href={route('wali-murid.pendaftaran.edit', pendaftaran.id)}>Perbaiki Data Sekarang</Link>
                        </Button>
                    </div>
                )}

                {pendaftaran.status === 'draft' && (
                    <div className="mb-6 flex flex-col items-stretch gap-4 rounded-2xl border border-gray-200 bg-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div className="min-w-0">
                            <h3 className="mb-1 text-sm font-semibold text-gray-700">Pendaftaran ini masih berupa draft</h3>
                            <p className="text-sm text-gray-500">
                                Data biodata dan wali di bawah ini masih bisa diubah sebelum berkas dikirim untuk diverifikasi.
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="w-full shrink-0 rounded-xl border-gray-300 text-gray-700 hover:bg-white hover:text-gray-900 sm:w-auto"
                        >
                            <Link href={route('wali-murid.pendaftaran.edit', pendaftaran.id)}>Edit Data Pendaftaran</Link>
                        </Button>
                    </div>
                )}

                <div>
                    <Tabs
                        defaultValue="calon"
                        className="rounded-2xl bg-white p-4 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] sm:p-6 lg:p-7"
                    >
                        <TabsList className="mb-6 grid h-auto w-full grid-cols-2 bg-[#F5F9FD] p-1">
                            <TabsTrigger
                                value="calon"
                                className="min-w-0 px-2 py-2 text-xs whitespace-normal text-gray-600 data-[state=active]:bg-[#0A3981] data-[state=active]:text-white sm:text-sm"
                            >
                                <span className="sm:hidden">Calon Peserta</span>
                                <span className="hidden sm:inline">Data Calon Peserta Didik</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="wali"
                                className="min-w-0 px-2 py-2 text-xs whitespace-normal text-gray-600 data-[state=active]:bg-[#0A3981] data-[state=active]:text-white sm:text-sm"
                            >
                                <span className="sm:hidden">Orang Tua / Wali</span>
                                <span className="hidden sm:inline">Data Orang Tua / Wali</span>
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="calon">
                            <div className="space-y-7">
                                <section>
                                    <SectionTitle>Identitas Calon Peserta Didik</SectionTitle>
                                    <div className="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <InfoItem label="Nama Lengkap" value={pendaftaran.nama_pendaftar} />
                                        <InfoItem label="NIK" value={pendaftaran.nik} />
                                        <InfoItem label="Tempat, Tanggal Lahir" value={`${pendaftaran.tempat_lahir}, ${pendaftaran.tanggal_lahir}`} />
                                        <InfoItem
                                            label="Jenis Kelamin"
                                            value={pendaftaran.jenis_kelamin === 'laki-laki' ? 'Laki-laki' : 'Perempuan'}
                                        />
                                        <InfoItem label="Jalur Pendaftaran" value={pendaftaran.kategori} />
                                        <InfoItem label="Gelombang" value={pendaftaran.gelombang} />
                                    </div>
                                </section>

                                <section>
                                    <SectionTitle>Alamat Domisili</SectionTitle>
                                    <div className="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <InfoItem label="Alamat" value={pendaftaran.alamat} className="sm:col-span-2 lg:col-span-4" />
                                        <InfoItem label="RT" value={pendaftaran.rt} />
                                        <InfoItem label="RW" value={pendaftaran.rw} />
                                        <InfoItem label="Kelurahan/Desa" value={pendaftaran.kelurahan} />
                                        <InfoItem label="Kecamatan" value={pendaftaran.kecamatan} />
                                        <InfoItem label="Kota/Kabupaten" value={pendaftaran.kota_kabupaten} />
                                        <InfoItem label="Provinsi" value={pendaftaran.provinsi} />
                                    </div>
                                </section>

                                <section>
                                    <SectionTitle>Informasi Tambahan</SectionTitle>
                                    <div className="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                                        <InfoItem label="Asal TK/RA/PAUD" value={pendaftaran.asal_paud} />
                                        <InfoItem label="Mengetahui PPDB dari" value={pendaftaran.tahu_dari} />
                                    </div>
                                </section>

                                {pendaftaran.pertanyaan_khusus && (
                                    <section>
                                        <SectionTitle>Data Pendukung Jalur {pendaftaran.kategori}</SectionTitle>
                                        <InfoItem label={pendaftaran.pertanyaan_khusus} value={pendaftaran.jawaban_khusus} />
                                    </section>
                                )}
                            </div>
                        </TabsContent>

                        <TabsContent value="wali">
                            <div className="space-y-4">
                                {waliMurid.map((w, i) => (
                                    <div key={i} className="rounded-xl border border-[#D4EBF8] bg-[#F5F9FD] p-4 sm:p-5">
                                        <div className="mb-3 text-xs font-bold tracking-wide text-[#1F509A] uppercase">
                                            Wali {i + 1} · {w.hubungan}
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <InfoItem label="Nama" value={w.nama} />
                                            <InfoItem label="NIK" value={w.nik} />
                                            <InfoItem label="No. WhatsApp" value={w.telepon} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
