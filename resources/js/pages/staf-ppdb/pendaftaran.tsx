import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';

interface PendaftaranItem {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    gelombang: string;
    tahun_ajaran: string;
    status: string;
    status_pelunasan: string | null;
    sisa_tagihan: number | null;
    tanggal_daftar: string;
}

interface PendaftaranProps {
    pendaftaran: PendaftaranItem[];
    /** Tahun ajaran aktif & gelombang yang sedang dibuka, ditentukan backend. */
    filterAwal: { tahunAjaran: string; gelombang: string };
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-teal-100 text-teal-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

const pelunasanLabel: Record<string, string> = {
    belum_bayar: 'Belum bayar',
    menunggu_verifikasi: 'Menunggu diperiksa',
    dicicil: 'Dicicil',
    lunas: 'Lunas',
    ditolak: 'Bukti ditolak',
};

// h-10 & rounded-md disamakan dengan <Input> milik DataTable - keduanya berdiri
// sebaris, jadi beda dua piksel pun langsung kelihatan tidak rapi. appearance-none
// mematikan panah bawaan sistem yang bentuknya beda-beda tiap OS; gantinya ikon
// lucide yang sama dengan seluruh aplikasi.
const gayaSelect =
    'h-10 w-full appearance-none rounded-md border border-gray-200 bg-white pr-9 pl-3 text-sm text-gray-900 shadow-sm transition-colors focus:border-[#1F509A] focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

// Pembungkus supaya ikon panah bisa ditumpuk di atas select. Namanya sendiri
// diberikan lewat aria-label di select-nya, bukan <label> tersembunyi.
function Penyaring({ lebar, children }: { lebar: string; children: React.ReactNode }) {
    return (
        <div className={`relative ${lebar}`}>
            {children}
            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
        </div>
    );
}

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

const columns: ColumnDef<PendaftaranItem>[] = [
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
        cell: ({ row }) => (
            <div>
                <p className="text-gray-700">{row.original.kategori}</p>
                <p className="text-xs text-gray-500">
                    {row.original.gelombang} · {row.original.tahun_ajaran}
                </p>
            </div>
        ),
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
        id: 'pembayaran',
        header: 'Pembayaran',
        accessorFn: (row) => (row.status_pelunasan ? pelunasanLabel[row.status_pelunasan] : ''),
        cell: ({ row }) => {
            // Belum sampai tahap pembayaran - jangan tampilkan "belum bayar",
            // nanti terbaca seolah-olah menunggak.
            if (!row.original.status_pelunasan) {
                return <span className="text-gray-500">&mdash;</span>;
            }

            return (
                <div>
                    <p className="text-gray-700">{pelunasanLabel[row.original.status_pelunasan]}</p>
                    {row.original.sisa_tagihan !== null && row.original.sisa_tagihan > 0 && (
                        <p className="text-xs text-gray-500">sisa {formatRupiah(row.original.sisa_tagihan)}</p>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'tanggal_daftar',
        header: 'Daftar',
        cell: ({ row }) => <span className="text-gray-700">{row.original.tanggal_daftar}</span>,
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
                <Link href={route('staf-ppdb.pendaftaran.show', row.original.id)}>Lihat Detail</Link>
            </Button>
        ),
    },
];

export default function Pendaftaran({ pendaftaran, filterAwal }: PendaftaranProps) {
    // Dibuka langsung pada tahun ajaran aktif dan gelombang yang sedang dibuka -
    // itu yang paling sering ditanyakan. Cuma posisi awal: tahun ajaran lain
    // tetap bisa dipilih. "" berarti semua.
    const [tahunAjaran, setTahunAjaran] = useState(filterAwal.tahunAjaran);
    const [gelombang, setGelombang] = useState(filterAwal.gelombang);

    // Dua penyaring yang berdiri sendiri, tidak saling mengunci. Menyaring
    // gelombang saja berarti "Gelombang 1 dari semua angkatan"; dipasang
    // berdua, keduanya menyempit.
    //
    // Pilihannya diambil dari baris yang ada, bukan daftar master, supaya tidak
    // pernah ada pilihan yang menghasilkan tabel kosong.
    const daftarTahunAjaran = useMemo(() => [...new Set(pendaftaran.map((p) => p.tahun_ajaran))].sort().reverse(), [pendaftaran]);

    // Yang didaftar NAMA gelombangnya, bukan tiap gelombang per tahun. Jadi
    // isinya tetap "Gelombang 1, Gelombang 2" walau arsipnya sudah bertahun-
    // tahun - tidak berulang, dan tidak perlu embel-embel tahun.
    const daftarGelombang = useMemo(() => [...new Set(pendaftaran.map((p) => p.gelombang))].sort(), [pendaftaran]);

    const barisTersaring = useMemo(
        () => pendaftaran.filter((p) => (!tahunAjaran || p.tahun_ajaran === tahunAjaran) && (!gelombang || p.gelombang === gelombang)),
        [pendaftaran, tahunAjaran, gelombang],
    );

    const adaPenyaring = tahunAjaran !== '' || gelombang !== '';

    // Halaman ini terbuka dengan penyaring sudah menyala, jadi kosongnya tabel
    // hampir selalu karena penyaring - bukan karena datanya tidak ada. Sebabnya
    // disebut supaya staf tidak menyimpulkan pendaftarannya hilang.
    const kalimatKosong = adaPenyaring
        ? 'Tidak ada pendaftaran yang cocok dengan penyaring ini. Pilih "Semua tahun ajaran" atau gelombang lain untuk melihat sisanya.'
        : 'Tidak ada pendaftaran yang cocok dengan pencarian.';

    return (
        <AppLayout>
            <Head title="Semua Pendaftaran" />
            <PageHeader title="Semua Pendaftaran" subtitle="Seluruh pendaftaran PPDB dari semua gelombang dan status" wide />

            <PageContainer wide>
                {pendaftaran.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <p className="text-sm text-gray-500">
                            Belum ada pendaftaran sama sekali. Daftar ini terisi begitu wali murid mulai mendaftarkan anaknya.
                        </p>
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={barisTersaring}
                        searchPlaceholder="Cari nama atau nomor pendaftaran..."
                        emptyMessage={kalimatKosong}
                        toolbar={
                            <>
                                <Penyaring lebar="w-48">
                                    <select
                                        className={gayaSelect}
                                        value={tahunAjaran}
                                        onChange={(e) => setTahunAjaran(e.target.value)}
                                        aria-label="Saring menurut tahun ajaran"
                                    >
                                        <option value="">Semua tahun ajaran</option>
                                        {daftarTahunAjaran.map((t) => (
                                            <option key={t} value={t}>
                                                {t}
                                            </option>
                                        ))}
                                    </select>
                                </Penyaring>

                                <Penyaring lebar="w-48">
                                    <select
                                        className={gayaSelect}
                                        value={gelombang}
                                        onChange={(e) => setGelombang(e.target.value)}
                                        aria-label="Saring menurut gelombang"
                                    >
                                        <option value="">Semua gelombang</option>
                                        {daftarGelombang.map((g) => (
                                            <option key={g} value={g}>
                                                {g}
                                            </option>
                                        ))}
                                    </select>
                                </Penyaring>

                                {adaPenyaring && (
                                    <span className="text-xs text-gray-500">
                                        {barisTersaring.length} dari {pendaftaran.length} pendaftaran
                                    </span>
                                )}
                            </>
                        }
                    />
                )}
            </PageContainer>
        </AppLayout>
    );
}
