import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';

interface PendaftaranItem {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    status: string;
    tanggal_daftar: string;
}

interface IndexProps {
    pendaftaranList: PendaftaranItem[];
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-green-100 text-green-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

const columns: ColumnDef<PendaftaranItem>[] = [
    {
        accessorKey: 'nomor_pendaftaran',
        header: 'Nomor Pendaftaran',
        cell: ({ row }) => <span className="font-medium text-gray-700">{row.original.nomor_pendaftaran}</span>,
    },
    {
        accessorKey: 'nama_pendaftar',
        header: 'Nama Calon Peserta Didik',
    },
    {
        accessorKey: 'kategori',
        header: 'Kategori',
    },
    {
        accessorKey: 'tanggal_daftar',
        header: 'Tanggal Daftar',
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const badge = statusBadge[row.original.status] ?? statusBadge.draft;
            return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge.className}`}>{badge.label}</span>;
        },
    },
    {
        id: 'aksi',
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
            <Link href={route('wali-murid.pendaftaran.show', row.original.id)} className="text-xs font-semibold text-[#1F509A] underline">
                Lihat Detail
            </Link>
        ),
    },
];

export default function PendaftaranIndex({ pendaftaranList }: IndexProps) {
    return (
        <AppLayout>
            <Head title="Pendaftaran" />
            <PageHeader title="Pendaftaran" subtitle="Daftar seluruh pendaftaran PPDB yang kamu ajukan" />

            <div className="px-8 pb-20">
                <div className="mb-5 flex justify-end">
                    <Button asChild className="rounded-xl px-5 py-2.5 text-sm font-bold">
                        <Link href={route('wali-murid.pendaftaran.create')}>+ Tambah Pendaftaran</Link>
                    </Button>
                </div>

                <DataTable columns={columns} data={pendaftaranList} searchPlaceholder="Cari nama atau nomor pendaftaran..." />
            </div>
        </AppLayout>
    );
}