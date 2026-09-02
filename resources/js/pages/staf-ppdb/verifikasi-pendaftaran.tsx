import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';

interface AntrianItem {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    gelombang: string;
    berkas_terunggah: number;
    berkas_wajib: number;
    menunggu_sejak: string;
}

interface VerifikasiPendaftaranProps {
    antrian: AntrianItem[];
}

const columns: ColumnDef<AntrianItem>[] = [
    {
        id: 'pendaftar',
        header: 'Pendaftar',
        // accessorFn dipakai supaya kotak pencarian di atas tabel ikut mencari
        // nama DAN nomor, walau yang ditampilkan di sel dua baris terpisah.
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
        accessorKey: 'gelombang',
        header: 'Gelombang',
        cell: ({ row }) => <span className="text-gray-700">{row.original.gelombang}</span>,
    },
    {
        id: 'berkas',
        header: 'Berkas',
        accessorFn: (row) => row.berkas_terunggah,
        cell: ({ row }) => (
            <span className="text-gray-700">
                {row.original.berkas_terunggah} dari {row.original.berkas_wajib}
            </span>
        ),
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
            <Link href={route('staf-ppdb.verifikasi-pendaftaran.show', row.original.id)} className="text-xs font-semibold text-[#1F509A] underline">
                Periksa
            </Link>
        ),
    },
];

export default function VerifikasiPendaftaran({ antrian }: VerifikasiPendaftaranProps) {
    return (
        <AppLayout>
            <Head title="Verifikasi Pendaftaran" />
            <PageHeader title="Verifikasi Pendaftaran" subtitle="Formulir dan berkas yang menunggu diperiksa" wide />

            <PageContainer wide>
                {antrian.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <p className="text-sm text-gray-500">
                            Tidak ada pendaftaran yang menunggu diperiksa. Antrian ini terisi sendiri begitu ada wali yang mengirim berkasnya.
                        </p>
                    </div>
                ) : (
                    <DataTable columns={columns} data={antrian} searchPlaceholder="Cari nama atau nomor pendaftaran..." />
                )}
            </PageContainer>
        </AppLayout>
    );
}
