import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronRight, Minus, Plus } from 'lucide-react';

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
            <Head title="Pembayaran Sekolah" />
            <PageHeader title="Pembayaran Sekolah" subtitle="Bukti pembayaran tagihan sekolah yang menunggu diperiksa" wide />

            <PageContainer wide>
                {antrian.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <p className="text-sm text-gray-500">
                            Tidak ada bukti transfer yang menunggu diperiksa. Antrian ini terisi sendiri begitu ada wali yang mengunggah bukti
                            pembayaran.
                        </p>
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={antrian}
                        rowId={(item) => String(item.id)}
                        searchPlaceholder="Cari nama atau nomor pendaftaran..."
                        mobileHeader={
                            <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                                <span aria-hidden />
                                <span>Pendaftar</span>
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
                                        <p className="truncate text-sm font-semibold text-gray-900" title={item.nama_pendaftar}>
                                            {item.nama_pendaftar}
                                        </p>
                                        <p className="mt-0.5 truncate text-[11px] text-gray-500">{item.nomor_pendaftaran}</p>
                                    </div>

                                    <p className="text-right text-sm font-semibold whitespace-nowrap text-gray-900">
                                        {formatRupiah(item.nominal_transfer)}
                                    </p>
                                </div>

                                {expanded && (
                                    <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4 pl-[3.75rem]">
                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-gray-500">Kategori</dt>
                                                <dd className="mt-0.5 font-medium text-gray-900">{item.kategori}</dd>
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
                                            <Link href={route('staf-ppdb.verifikasi-pembayaran.show', item.id)}>
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
