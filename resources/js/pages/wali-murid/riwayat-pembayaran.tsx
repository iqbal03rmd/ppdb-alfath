import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronRight, Minus, Plus } from 'lucide-react';

interface RiwayatItem {
    id: number;
    pendaftaran_id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    nominal_transfer: number;
    tanggal_transfer: string;
    status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak';
}

interface RiwayatPembayaranProps {
    riwayat: RiwayatItem[];
}

const statusBadge: Record<string, { label: string; className: string }> = {
    menunggu_verifikasi: { label: 'Menunggu Verifikasi', className: 'bg-amber-100 text-amber-700' },
    terverifikasi: { label: 'Terverifikasi', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

const columns: ColumnDef<RiwayatItem>[] = [
    {
        accessorKey: 'tanggal_transfer',
        header: 'Tanggal Transfer',
    },
    {
        id: 'pendaftaran',
        header: 'Pendaftaran Terkait',
        accessorFn: (row) => `${row.nama_pendaftar} ${row.nomor_pendaftaran}`,
        cell: ({ row }) => (
            <div>
                <p className="font-medium text-gray-900">{row.original.nama_pendaftar}</p>
                <p className="text-xs text-gray-500">{row.original.nomor_pendaftaran}</p>
            </div>
        ),
    },
    {
        accessorKey: 'nominal_transfer',
        header: 'Nominal',
        cell: ({ row }) => <span className="font-medium text-gray-700">{formatRupiah(row.original.nominal_transfer)}</span>,
    },
    {
        accessorKey: 'status',
        header: 'Status Verifikasi',
        cell: ({ row }) => {
            const badge = statusBadge[row.original.status];
            return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge.className}`}>{badge.label}</span>;
        },
    },
    {
        id: 'aksi',
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
            <Button
                asChild
                variant="outline"
                size="sm"
                className="rounded-xl border-[#1F509A]/40 bg-white text-xs font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
            >
                <Link href={route('wali-murid.pembayaran.show', row.original.pendaftaran_id)}>Lihat Detail</Link>
            </Button>
        ),
    },
];

export default function RiwayatPembayaran({ riwayat }: RiwayatPembayaranProps) {
    return (
        <AppLayout>
            <Head title="Pembayaran Sekolah" />
            <PageHeader title="Pembayaran Sekolah" subtitle="Riwayat pembayaran tagihan sekolah untuk anak yang didaftarkan" wide />

            <PageContainer wide>
                <DataTable
                    columns={columns}
                    data={riwayat}
                    searchPlaceholder="Cari nama atau nomor pendaftaran..."
                    mobileHeader={
                        <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                            <span aria-hidden />
                            <span>Pendaftaran</span>
                            <span className="text-right">Pembayaran</span>
                        </div>
                    }
                    renderMobileRow={(item, { expanded, toggle }) => {
                        const badge = statusBadge[item.status];

                        return (
                            <div className={expanded ? 'bg-[#F8FBFE]' : 'bg-white'}>
                                <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-3 px-4 py-4">
                                    <button
                                        type="button"
                                        onClick={toggle}
                                        aria-expanded={expanded}
                                        aria-label={`${expanded ? 'Tutup' : 'Buka'} detail pembayaran ${item.nama_pendaftar}`}
                                        className={`mt-0.5 flex h-7 w-7 items-center justify-center rounded-full transition-colors ${
                                            expanded ? 'bg-[#0A3981] text-white' : 'bg-[#E8EEF7] text-[#1F509A] hover:bg-[#D4EBF8]'
                                        }`}
                                    >
                                        {expanded ? (
                                            <Minus className="h-4 w-4" aria-hidden="true" />
                                        ) : (
                                            <Plus className="h-4 w-4" aria-hidden="true" />
                                        )}
                                    </button>

                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-gray-900">{item.nama_pendaftar}</p>
                                        <p className="mt-0.5 truncate text-[11px] text-gray-500">{item.nomor_pendaftaran}</p>
                                    </div>

                                    <div className="text-right">
                                        <p className="text-sm font-semibold whitespace-nowrap text-gray-900">{formatRupiah(item.nominal_transfer)}</p>
                                        <span
                                            className={`mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ${badge.className}`}
                                        >
                                            {item.status === 'menunggu_verifikasi' ? 'Menunggu' : badge.label}
                                        </span>
                                    </div>
                                </div>

                                {expanded && (
                                    <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4 pl-[3.75rem]">
                                        <dl className="space-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-gray-500">Tanggal Transfer</dt>
                                                <dd className="mt-0.5 font-medium text-gray-900">{item.tanggal_transfer}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Status Verifikasi</dt>
                                                <dd className="mt-0.5 font-medium text-gray-900">{badge.label}</dd>
                                            </div>
                                        </dl>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="mt-4 w-full rounded-xl border-[#1F509A]/40 bg-white text-xs font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                        >
                                            <Link href={route('wali-murid.pembayaran.show', item.pendaftaran_id)}>
                                                Lihat Detail Pembayaran
                                                <ChevronRight className="ml-1 h-4 w-4" aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        );
                    }}
                />
            </PageContainer>
        </AppLayout>
    );
}
