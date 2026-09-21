import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';

interface Item {
    id: number;
    nama_wali: string;
    email: string;
    nominal_transfer: number;
    tanggal_transfer: string;
    menunggu_sejak: string;
}

function rupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

const columns: ColumnDef<Item>[] = [
    {
        accessorKey: 'nama_wali',
        header: 'Wali',
        cell: ({ row }) => (
            <div>
                <p className="font-medium text-gray-900">{row.original.nama_wali}</p>
                <p className="text-xs text-gray-500">{row.original.email}</p>
            </div>
        ),
    },
    {
        accessorKey: 'nominal_transfer',
        header: 'Nominal',
        cell: ({ row }) => <span className="font-medium">{rupiah(row.original.nominal_transfer)}</span>,
    },
    { accessorKey: 'tanggal_transfer', header: 'Tanggal Transfer' },
    { accessorKey: 'menunggu_sejak', header: 'Menunggu Sejak' },
    {
        id: 'aksi',
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
            <Button asChild variant="outline" size="sm" className="rounded-xl border-[#1F509A]/40 text-xs font-bold text-[#1F509A]">
                <Link href={route('staf-ppdb.verifikasi-biaya-pendaftaran.show', row.original.id)}>Periksa</Link>
            </Button>
        ),
    },
];

export default function VerifikasiBiayaPendaftaran({ antrian }: { antrian: Item[] }) {
    return (
        <AppLayout>
            <Head title="Biaya Pendaftaran" />
            <PageHeader title="Biaya Pendaftaran" subtitle="Pembayaran awal yang harus diperiksa sebelum wali mengisi formulir anak" wide />
            <PageContainer wide>
                {antrian.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-sm">
                        <p className="text-sm text-gray-500">Tidak ada pembayaran biaya pendaftaran yang menunggu diperiksa.</p>
                    </div>
                ) : (
                    <DataTable columns={columns} data={antrian} searchPlaceholder="Cari nama atau email wali..." />
                )}
            </PageContainer>
        </AppLayout>
    );
}
