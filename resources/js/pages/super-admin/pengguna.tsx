import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronDown, UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

interface PenggunaItem {
    id: number;
    name: string;
    email: string;
    telepon: string | null;
    peran: string;
    status_aktif: boolean;
    jumlah_pendaftaran: number;
    diri_sendiri: boolean;
}

interface PenggunaProps {
    pengguna: PenggunaItem[];
    peran: Record<string, string>;
}

// Disamakan dengan <Input> milik DataTable karena berdiri sebaris dengannya.
// appearance-none mematikan panah bawaan sistem yang bentuknya beda tiap OS.
const gayaSelect =
    'h-10 w-full appearance-none rounded-md border border-gray-200 bg-white pr-9 pl-3 text-sm text-gray-900 shadow-sm transition-colors focus:border-[#1F509A] focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

function Penyaring({ lebar, children }: { lebar: string; children: React.ReactNode }) {
    return (
        <div className={`relative ${lebar}`}>
            {children}
            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
        </div>
    );
}

const columns: ColumnDef<PenggunaItem>[] = [
    {
        id: 'pengguna',
        header: 'Pengguna',
        accessorFn: (row) => `${row.name} ${row.email}`,
        cell: ({ row }) => (
            <div>
                <p className="font-medium text-gray-900">
                    {row.original.name}
                    {row.original.diri_sendiri && <span className="ml-2 text-xs font-normal text-gray-500">(Anda)</span>}
                </p>
                <p className="text-xs text-gray-500">{row.original.email}</p>
            </div>
        ),
    },
    {
        accessorKey: 'peran',
        header: 'Peran',
        cell: ({ row }) => (
            <div>
                <p className="text-gray-700">{row.original.peran}</p>
                {/* Cuma tampil kalau ada isinya - angka 0 pendaftaran tidak
                    menerangkan apa pun dan bikin kolom penuh baris kedua kosong. */}
                {row.original.jumlah_pendaftaran > 0 && <p className="text-xs text-gray-500">{row.original.jumlah_pendaftaran} pendaftaran</p>}
            </div>
        ),
    },
    {
        accessorKey: 'telepon',
        header: 'Telepon',
        cell: ({ row }) => <span className="text-gray-700">{row.original.telepon || <span className="text-gray-500">&mdash;</span>}</span>,
    },
    {
        id: 'status',
        header: 'Status',
        accessorFn: (row) => (row.status_aktif ? 'Aktif' : 'Nonaktif'),
        cell: ({ row }) =>
            row.original.status_aktif ? (
                <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Aktif</span>
            ) : (
                <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Nonaktif</span>
            ),
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
                className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
            >
                <Link href={route('super-admin.pengguna.edit', row.original.id)}>Ubah</Link>
            </Button>
        ),
    },
];

export default function Pengguna({ pengguna, peran }: PenggunaProps) {
    // "" berarti semua. Halaman ini dibuka tanpa penyaring apa pun: daftar akun
    // sekolah pendek, dan admin biasanya datang untuk mencari satu orang -
    // menyaring lebih dulu justru bisa menyembunyikan orang yang dicari.
    const [saringPeran, setSaringPeran] = useState('');
    const [saringStatus, setSaringStatus] = useState('');

    const barisTersaring = useMemo(
        () => pengguna.filter((p) => (!saringPeran || p.peran === saringPeran) && (!saringStatus || (saringStatus === 'aktif') === p.status_aktif)),
        [pengguna, saringPeran, saringStatus],
    );

    const adaPenyaring = saringPeran !== '' || saringStatus !== '';

    return (
        <AppLayout>
            <Head title="Kelola Pengguna" />
            <PageHeader title="Kelola Pengguna" subtitle="Akun yang boleh masuk ke sistem PPDB, beserta perannya masing-masing" wide />

            <PageContainer wide>
                <DataTable
                    columns={columns}
                    data={barisTersaring}
                    searchPlaceholder="Cari nama atau email..."
                    // Dipendekkan dari bawaannya karena baris ini sekarang juga
                    // memuat tombol "Tambah Pengguna" di ujung kanan - dengan
                    // lebar penuh, pencarian dan dua penyaring mepet ke tombol.
                    searchWidth="max-w-xs"
                    emptyMessage={
                        adaPenyaring
                            ? 'Tidak ada akun yang cocok dengan penyaring ini. Pilih "Semua peran" atau "Semua status" untuk melihat sisanya.'
                            : 'Tidak ada akun yang cocok dengan pencarian.'
                    }
                    toolbar={
                        <>
                            <Penyaring lebar="w-44">
                                <select
                                    className={gayaSelect}
                                    value={saringPeran}
                                    onChange={(e) => setSaringPeran(e.target.value)}
                                    aria-label="Saring menurut peran"
                                >
                                    <option value="">Semua peran</option>
                                    {Object.values(peran).map((label) => (
                                        <option key={label} value={label}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </Penyaring>

                            <Penyaring lebar="w-40">
                                <select
                                    className={gayaSelect}
                                    value={saringStatus}
                                    onChange={(e) => setSaringStatus(e.target.value)}
                                    aria-label="Saring menurut status akun"
                                >
                                    <option value="">Semua status</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </Penyaring>

                            {adaPenyaring && (
                                <span className="text-xs text-gray-500">
                                    {barisTersaring.length} dari {pengguna.length} akun
                                </span>
                            )}

                            {/* ml-auto mendorongnya ke ujung kanan baris, jadi
                                jaraknya ke penyaring ikut melebar sendiri saat
                                layar besar - tidak perlu jarak yang dipatok. */}
                            <Button asChild className="ml-auto rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                                <Link href={route('super-admin.pengguna.create')}>
                                    <UserPlus size={16} strokeWidth={2} />
                                    Tambah Pengguna
                                </Link>
                            </Button>
                        </>
                    }
                />
            </PageContainer>
        </AppLayout>
    );
}
