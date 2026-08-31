import { DataTable } from '@/components/data-table';
import PageHeader from '@/components/page-header';
import PageContainer from '@/components/page-container';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';

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
            <Link href={route('wali-murid.pembayaran.show', row.original.pendaftaran_id)} className="text-xs font-semibold text-[#1F509A] underline">
                Lihat Detail
            </Link>
        ),
    },
];

export default function RiwayatPembayaran({ riwayat }: RiwayatPembayaranProps) {
    return (
        <AppLayout>
            <Head title="Riwayat Pembayaran" />
            <PageHeader title="Riwayat Pembayaran" subtitle="Daftar seluruh transfer yang pernah kamu ajukan" />

            <PageContainer wide>
                <DataTable columns={columns} data={riwayat} searchPlaceholder="Cari nama atau nomor pendaftaran..." />
            </PageContainer>
        </AppLayout>
    );
}
