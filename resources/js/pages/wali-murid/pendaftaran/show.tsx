import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';

interface PendaftaranDetail {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    status: string;
    catatan_verifikasi: string | null;
    jumlah_dokumen: number;
    status_pembayaran: string | null;
}

interface ShowProps {
    pendaftaran: PendaftaranDetail;
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-green-100 text-green-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

export default function PendaftaranShow({ pendaftaran }: ShowProps) {
    const badge = statusBadge[pendaftaran.status] ?? statusBadge.draft;

    return (
        <AppLayout>
            <Head title={`Detail Pendaftaran - ${pendaftaran.nama_pendaftar}`} />
            <PageHeader title={pendaftaran.nama_pendaftar} subtitle={pendaftaran.nomor_pendaftaran} />

            <div className="px-8 pb-20">
                <Link href={route('wali-murid.pendaftaran.index')} className="mb-4 inline-block text-sm text-[#1F509A] underline">
                    &larr; Kembali ke daftar pendaftaran
                </Link>

                <div className="mb-6 rounded-2xl bg-white p-8 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <div className="text-xs text-gray-400">Kategori</div>
                            <div className="text-sm font-semibold text-gray-800">{pendaftaran.kategori}</div>
                        </div>
                        <span className={`rounded-full px-3 py-1.5 text-sm font-semibold ${badge.className}`}>{badge.label}</span>
                    </div>

                    {pendaftaran.status === 'perlu_perbaikan' && (
                        <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
                            <h3 className="mb-1 text-sm font-semibold text-amber-800">Ada yang perlu diperbaiki</h3>
                            <p className="text-sm text-amber-700">
                                {pendaftaran.catatan_verifikasi ?? 'Staf PPDB meminta perbaikan data. Silakan hubungi sekolah untuk detailnya.'}
                            </p>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <div className="rounded-xl border border-[#D4EBF8] bg-[#F5F9FD] p-5">
                            <div className="mb-1 text-xs text-gray-500">Berkas Persyaratan</div>
                            <div className="text-sm font-semibold text-[#0A3981]">{pendaftaran.jumlah_dokumen} dokumen terunggah</div>
                            <Link
                                href={route('wali-murid.pendaftaran.unggah-berkas', pendaftaran.id)}
                                className="mt-2 inline-block text-xs font-semibold text-[#1F509A] underline"
                            >
                                Lihat / Kelola Berkas
                            </Link>
                        </div>

                        <div className="rounded-xl border border-[#D4EBF8] bg-[#F5F9FD] p-5">
                            <div className="mb-1 text-xs text-gray-500">Status Pembayaran</div>
                            <div className="text-sm font-semibold text-[#0A3981]">
                                {pendaftaran.status_pembayaran ?? 'Belum ada pembayaran'}
                            </div>
                            <Link href={route('wali-murid.pembayaran.index')} className="mt-2 inline-block text-xs font-semibold text-[#1F509A] underline">
                                Kelola Pembayaran
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}