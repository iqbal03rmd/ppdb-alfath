import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';

interface AntrianItem {
    id: number;
    pendaftaran_id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    nominal_transfer: number;
    tanggal_transfer: string;
    menunggu_sejak: string;
}

interface VerifikasiPembayaranProps {
    antrian: AntrianItem[];
}

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

const columns: ColumnDef<AntrianItem>[] = [
    {
        id: 'pendaftar',
        header: 'Pendaftar',
        accessorFn: (row) => `${row.nama_pendaftar} ${row.nomor_pendaftaran}`,
        cell: ({ row }) => (
            <div>
                <p className="font-medium text-gray-900">{row.original.nama_pendaftar}</p>
                <p className="text-xs text-gray-500">{row.original.nomor_pendaftaran}</p>
            </div>
        ),
    },
    {
        accessorKey: 'kategori',
        header: 'Kategori',
        cell: ({ row }) => <span className="text-gray-700">{row.original.kategori}</span>,
    },
    {
        accessorKey: 'nominal_transfer',
        header: 'Nominal',
        cell: ({ row }) => <span className="font-medium text-gray-900">{formatRupiah(row.original.nominal_transfer)}</span>,
    },
    {
        accessorKey: 'tanggal_transfer',
        header: 'Tanggal Transfer',
        cell: ({ row }) => <span className="text-gray-700">{row.original.tanggal_transfer}</span>,
    },
    {
        accessorKey: 'menunggu_sejak',
        header: 'Menunggu Sejak',
        cell: ({ row }) => <span className="text-gray-700">{row.original.menunggu_sejak}</span>,
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
                <Link href={route('staf-ppdb.verifikasi-pembayaran.show', row.original.id)}>Periksa</Link>
            </Button>
        ),
    },
];

export default function VerifikasiPembayaran({ antrian }: VerifikasiPembayaranProps) {
    return (
        <AppLayout>
            <Head title="Verifikasi Pembayaran" />
            <PageHeader title="Verifikasi Pembayaran" subtitle="Bukti transfer yang menunggu diperiksa" wide />

            <PageContainer wide>
                {antrian.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <p className="text-sm text-gray-500">
                            Tidak ada bukti transfer yang menunggu diperiksa. Antrian ini terisi sendiri begitu ada wali yang mengunggah bukti
                            pembayaran.
                        </p>
                    </div>
                ) : (
                    <DataTable columns={columns} data={antrian} searchPlaceholder="Cari nama atau nomor pendaftaran..." />
                )}
            </PageContainer>
        </AppLayout>
    );
}
