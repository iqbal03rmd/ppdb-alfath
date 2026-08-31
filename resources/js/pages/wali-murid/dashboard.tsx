import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

interface RingkasanPendaftaran {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    status: string;
    catatan_verifikasi: string | null;
    sisa_tagihan: number | null;
    status_pelunasan: string;
    tindakan: string;
    tombol: string | null;
    rute: 'pendaftaran' | 'unggah-berkas' | 'pembayaran' | null;
    perlu_tindakan: boolean;
}

interface DashboardProps {
    daftarPendaftaran: RingkasanPendaftaran[];
    gelombangDibuka: { nama: string; tanggal_selesai: string } | null;
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-teal-100 text-teal-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

function tautan(item: RingkasanPendaftaran) {
    if (item.rute === 'pembayaran') return route('wali-murid.pembayaran.show', item.id);
    if (item.rute === 'unggah-berkas') return route('wali-murid.pendaftaran.unggah-berkas', item.id);

    return route('wali-murid.pendaftaran.index', { expand: item.id });
}

export default function Dashboard({ daftarPendaftaran, gelombangDibuka }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const namaDepan = String(auth.user?.name ?? '').split(' ')[0];
    const jumlahPerluTindakan = daftarPendaftaran.filter((d) => d.perlu_tindakan).length;

    return (
        <AppLayout>
            <Head title="Beranda" />
            <PageHeader
                title={`Assalamu'alaikum, ${namaDepan}`}
                subtitle={
                    daftarPendaftaran.length === 0
                        ? 'Belum ada pendaftaran yang kamu ajukan.'
                        : jumlahPerluTindakan > 0
                          ? `${jumlahPerluTindakan} pendaftaran menunggu tindakan kamu.`
                          : 'Semua pendaftaran sedang diproses sekolah — tidak ada yang perlu kamu lakukan.'
                }
            />

            <PageContainer wide>
                {daftarPendaftaran.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <p className="text-sm text-gray-500">
                            {gelombangDibuka
                                ? `Pendaftaran ${gelombangDibuka.nama} sedang dibuka sampai ${gelombangDibuka.tanggal_selesai}.`
                                : 'Saat ini belum ada gelombang PPDB yang dibuka.'}
                        </p>
                        {gelombangDibuka && (
                            <Button asChild className="mt-5 rounded-xl px-5 py-2.5 text-sm font-bold">
                                <Link href={route('wali-murid.pendaftaran.create')}>+ Daftarkan Anak</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {daftarPendaftaran.map((item) => {
                            const badge = statusBadge[item.status] ?? statusBadge.draft;

                            return (
                                <div
                                    key={item.id}
                                    className={
                                        'flex flex-col rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ' +
                                        (item.perlu_tindakan ? 'ring-1 ring-[#E38E49]/40' : '')
                                    }
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2 className="truncate text-[15px] font-semibold text-gray-900">{item.nama_pendaftar}</h2>
                                            <p className="mt-0.5 text-xs text-gray-500">
                                                {item.nomor_pendaftaran} · {item.kategori}
                                            </p>
                                        </div>
                                        <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${badge.className}`}>
                                            {badge.label}
                                        </span>
                                    </div>

                                    <p className="mt-4 flex-1 text-sm text-gray-700">{item.tindakan}</p>

                                    {item.catatan_verifikasi && item.status === 'perlu_perbaikan' && (
                                        <p className="mt-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-700">{item.catatan_verifikasi}</p>
                                    )}

                                    {item.sisa_tagihan !== null && item.sisa_tagihan > 0 && (
                                        <p className="mt-2 text-sm text-gray-500">
                                            Sisa tagihan <b className="text-[#0A3981]">{formatRupiah(item.sisa_tagihan)}</b>
                                        </p>
                                    )}

                                    {item.tombol && (
                                        <Button
                                            asChild
                                            variant={item.perlu_tindakan ? 'default' : 'outline'}
                                            size="sm"
                                            className={
                                                'mt-4 w-full rounded-xl font-bold ' +
                                                (item.perlu_tindakan
                                                    ? ''
                                                    : 'border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]')
                                            }
                                        >
                                            <Link href={tautan(item)}>{item.tombol}</Link>
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {daftarPendaftaran.length > 0 && gelombangDibuka && (
                    <div className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#D4EBF8] bg-[#F5F9FD] p-5">
                        <p className="text-sm text-[#0A3981]">
                            <b>{gelombangDibuka.nama}</b> masih dibuka sampai {gelombangDibuka.tanggal_selesai}.
                        </p>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-white hover:text-[#0A3981]"
                        >
                            <Link href={route('wali-murid.pendaftaran.create')}>+ Daftarkan Anak Lain</Link>
                        </Button>
                    </div>
                )}
            </PageContainer>
        </AppLayout>
    );
}
