import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronRight, Minus, Plus } from 'lucide-react';

interface Item {
    id: number;
    nama_wali: string;
    email: string;
    gelombang: string;
    nominal_transfer: number;
    tanggal_transfer: string;
    menunggu_sejak: string;
}

function rupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

const columns: ColumnDef<Item>[] = [
    {
        id: 'wali',
        header: 'Wali',
        accessorFn: (row) => `${row.nama_wali} ${row.email}`,
        cell: ({ row }) => (
            <div>
                <p className="font-medium text-gray-900">{row.original.nama_wali}</p>
                <p className="text-xs text-gray-500">{row.original.email}</p>
            </div>
        ),
    },
    { accessorKey: 'gelombang', header: 'Gelombang' },
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
                    <DataTable
                        columns={columns}
                        data={antrian}
                        rowId={(item) => String(item.id)}
                        searchPlaceholder="Cari nama atau email wali..."
                        mobileHeader={
                            <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                                <span aria-hidden />
                                <span>Wali</span>
                                <span className="text-right">Nominal</span>
                            </div>
                        }
                        renderMobileRow={(item, { expanded, toggle }) => (
                            <div className={expanded ? 'bg-[#F8FBFE]' : 'bg-white'}>
                                <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-3 px-4 py-4">
                                    <button
                                        type="button"
                                        onClick={toggle}
                                        aria-expanded={expanded}
                                        aria-label={`${expanded ? 'Tutup' : 'Buka'} detail biaya pendaftaran ${item.nama_wali}`}
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
                                        <p className="truncate text-sm font-semibold text-gray-900" title={item.nama_wali}>
                                            {item.nama_wali}
                                        </p>
                                        <p className="mt-0.5 truncate text-[11px] text-gray-500">{item.email}</p>
                                    </div>

                                    <p className="text-right text-sm font-semibold whitespace-nowrap text-gray-900">
                                        {rupiah(item.nominal_transfer)}
                                    </p>
                                </div>

                                {expanded && (
                                    <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4 pl-[3.75rem]">
                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-gray-500">Gelombang</dt>
                                                <dd className="mt-0.5 font-medium text-gray-900">{item.gelombang}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Tanggal Transfer</dt>
                                                <dd className="mt-0.5 font-medium text-gray-900">{item.tanggal_transfer}</dd>
                                            </div>
                                            <div className="col-span-2">
                                                <dt className="text-xs text-gray-500">Menunggu Sejak</dt>
                                                <dd className="mt-0.5 font-medium text-gray-900">{item.menunggu_sejak}</dd>
                                            </div>
                                        </dl>

                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="mt-4 w-full rounded-xl border-[#1F509A]/40 bg-white text-xs font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                        >
                                            <Link href={route('staf-ppdb.verifikasi-biaya-pendaftaran.show', item.id)}>
                                                Periksa Pembayaran
                                                <ChevronRight className="ml-1 h-4 w-4" aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    />
                )}
            </PageContainer>
        </AppLayout>
    );
}
