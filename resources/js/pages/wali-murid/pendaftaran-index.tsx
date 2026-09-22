import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Minus, Plus } from 'lucide-react';
import { type ReactNode } from 'react';

interface WaliMuridItem {
    nama: string;
    nik: string;
    hubungan: string;
    telepon: string;
}

interface DokumenItem {
    label: string;
    nama_file: string;
    url: string;
}

interface PendaftaranDetail {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    nik: string | null;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: string;
    alamat: string;
    kategori: string;
    status: string;
    catatan_verifikasi: string | null;
    tanggal_daftar: string;
    gelombang: string;
}

interface PendaftaranItem {
    pendaftaran: PendaftaranDetail;
    waliMurid: WaliMuridItem[];
    dokumenList: DokumenItem[];
    statusPembayaran: string | null;
    bisaEditBerkas: boolean;
    bolehBayar: boolean;
    progres: {
        wali: boolean;
        berkasTerunggah: number;
        berkasWajib: number;
    };
}

interface IndexProps {
    pendaftaranList: PendaftaranItem[];
    expandId: string | number | null;
    gelombangDibuka: boolean;
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    draft_kedaluwarsa: { label: 'Draft Berakhir', className: 'bg-gray-200 text-gray-700' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    pembayaran: { label: 'Pembayaran', className: 'bg-teal-100 text-teal-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

// Status PELUNASAN gabungan (bisa dari beberapa transfer kalau dicicil),
// bukan status satu baris transfer - lihat PendaftaranPpdb::statusPelunasan().
const pembayaranBadge: Record<string, { label: string; className: string }> = {
    menunggu_verifikasi: { label: 'Menunggu Verifikasi', className: 'bg-amber-100 text-amber-700' },
    dicicil: { label: 'Belum Lunas', className: 'bg-amber-100 text-amber-700' },
    lunas: { label: 'Lunas', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

function ringkasNamaMobile(nama: string) {
    const batasKarakter = 18;

    return nama.length > batasKarakter ? `${nama.slice(0, batasKarakter).trimEnd()}…` : nama;
}

function StatusPendaftaran({ item, mobile = false }: { item: PendaftaranItem; mobile?: boolean }) {
    const badge = statusBadge[item.pendaftaran.status] ?? statusBadge.draft;

    return (
        <span className={mobile ? 'flex shrink-0 flex-col items-end gap-1' : 'flex flex-wrap items-center gap-1.5'}>
            <span className={`w-fit rounded-full px-2.5 py-1 text-xs font-semibold ${mobile ? 'whitespace-nowrap' : ''} ${badge.className}`}>
                {badge.label}
            </span>
            {item.pendaftaran.status === 'diterima' && item.statusPembayaran && (
                <span className={`w-fit rounded-full px-2.5 py-1 text-xs font-semibold ${pembayaranBadge[item.statusPembayaran].className}`}>
                    {pembayaranBadge[item.statusPembayaran].label}
                </span>
            )}
        </span>
    );
}

const columns: ColumnDef<PendaftaranItem>[] = [
    {
        id: 'nomor_pendaftaran',
        accessorFn: (item) => item.pendaftaran.nomor_pendaftaran,
        header: 'Nomor Pendaftaran',
        cell: ({ row }) => <span className="font-medium text-gray-700">{row.original.pendaftaran.nomor_pendaftaran}</span>,
    },
    {
        id: 'nama_pendaftar',
        accessorFn: (item) => item.pendaftaran.nama_pendaftar,
        header: 'Nama Anak',
        cell: ({ row }) => <span className="text-gray-900">{row.original.pendaftaran.nama_pendaftar}</span>,
    },
    {
        id: 'kategori',
        accessorFn: (item) => item.pendaftaran.kategori,
        header: 'Kategori',
        cell: ({ row }) => <span className="text-gray-600">{row.original.pendaftaran.kategori}</span>,
    },
    {
        id: 'gelombang',
        accessorFn: (item) => item.pendaftaran.gelombang,
        header: 'Gelombang',
        cell: ({ row }) => <span className="text-gray-600">{row.original.pendaftaran.gelombang}</span>,
    },
    {
        id: 'tanggal_daftar',
        accessorFn: (item) => item.pendaftaran.tanggal_daftar,
        header: 'Tanggal Daftar',
        cell: ({ row }) => <span className="text-gray-600">{row.original.pendaftaran.tanggal_daftar}</span>,
    },
    {
        id: 'status',
        accessorFn: (item) => `${statusBadge[item.pendaftaran.status]?.label ?? 'Draft'} ${item.statusPembayaran ?? ''}`,
        header: 'Status',
        cell: ({ row }) => <StatusPendaftaran item={row.original} />,
    },
];

export default function PendaftaranIndex({ pendaftaranList, expandId, gelombangDibuka }: IndexProps) {
    return (
        <AppLayout>
            <Head title="Pendaftaran" />
            <PageHeader title="Pendaftaran" subtitle="Daftar seluruh pendaftaran PPDB yang kamu ajukan" wide />

            <PageContainer wide>
                <DataTable
                    columns={columns}
                    data={pendaftaranList}
                    rowId={(item) => String(item.pendaftaran.id)}
                    initialExpandedRowId={expandId ? String(expandId) : null}
                    desktopBreakpoint="lg"
                    searchPlaceholder="Cari nama atau nomor pendaftaran..."
                    emptyMessage={pendaftaranList.length === 0 ? 'Belum ada pendaftaran.' : 'Tidak ada hasil yang cocok.'}
                    toolbar={!gelombangDibuka ? <p className="ml-auto shrink-0 text-sm text-gray-500">Pendaftaran sedang ditutup</p> : undefined}
                    mobileHeader={
                        <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                            <span aria-hidden />
                            <span>Pendaftaran</span>
                            <span className="text-right">Status</span>
                        </div>
                    }
                    renderMobileRow={(item, { expanded, toggle }) => (
                        <div className={expanded ? 'bg-[#F8FBFE]' : 'bg-white'}>
                            <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-3 px-4 py-4">
                                <button
                                    type="button"
                                    onClick={toggle}
                                    aria-expanded={expanded}
                                    aria-label={`${expanded ? 'Tutup' : 'Buka'} detail pendaftaran ${item.pendaftaran.nama_pendaftar}`}
                                    className={`mt-0.5 flex h-7 w-7 items-center justify-center rounded-full transition-colors ${
                                        expanded ? 'bg-[#0A3981] text-white' : 'bg-[#E8EEF7] text-[#1F509A] hover:bg-[#D4EBF8]'
                                    }`}
                                >
                                    {expanded ? <Minus className="h-4 w-4" aria-hidden="true" /> : <Plus className="h-4 w-4" aria-hidden="true" />}
                                </button>

                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-gray-900" title={item.pendaftaran.nama_pendaftar}>
                                        {ringkasNamaMobile(item.pendaftaran.nama_pendaftar)}
                                    </p>
                                    <p className="mt-0.5 truncate text-[11px] text-gray-500">
                                        {item.pendaftaran.nomor_pendaftaran} · {item.pendaftaran.kategori}
                                    </p>
                                    <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-gray-500">
                                        <span>{item.pendaftaran.gelombang}</span>
                                        <span aria-hidden="true" className="h-1 w-1 rounded-full bg-gray-300" />
                                        <span>{item.pendaftaran.tanggal_daftar}</span>
                                    </p>
                                </div>

                                <StatusPendaftaran item={item} mobile />
                            </div>

                            {expanded && (
                                <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4">
                                    <PendaftaranDetailPanel item={item} />
                                </div>
                            )}
                        </div>
                    )}
                    renderExpandedRow={(item) => <PendaftaranDetailPanel item={item} />}
                />
            </PageContainer>
        </AppLayout>
    );
}

function PendaftaranDetailPanel({ item }: { item: PendaftaranItem }) {
    const { pendaftaran, progres, bisaEditBerkas } = item;
    const berkasLengkap = progres.berkasTerunggah >= progres.berkasWajib;
    const formulirLengkap = progres.wali; // biodata anak selalu ada (constraint DB), tinggal cek wali

    // Formulir & Berkas statusnya SATU PAKET (opsi A) - kalau perlu_perbaikan,
    // dua-duanya sama-sama dikasih framing "Perbaiki", bukan dibedain per-bagian.
    const labelFormulir = pendaftaran.status === 'perlu_perbaikan' ? 'Perbaiki Formulir' : bisaEditBerkas ? 'Edit Formulir' : 'Lihat Detail Formulir';

    const labelBerkas = pendaftaran.status === 'perlu_perbaikan' ? 'Perbaiki Berkas' : !berkasLengkap ? 'Upload Berkas' : 'Lihat / Kelola Berkas';

    // Aturan "boleh bayar" dihitung di backend (PendaftaranPpdb::bolehBayar())
    // dan dikirim sebagai prop - jangan dihitung ulang di sini, biar UI dan
    // server nggak pernah beda pendapat.
    const sudahBolehBayar = item.bolehBayar;
    const labelTombolPembayaran =
        item.statusPembayaran === 'lunas'
            ? 'Lihat Pembayaran'
            : pendaftaran.status === 'diterima' && item.statusPembayaran === 'dicicil'
              ? 'Bayar Sisa Tagihan'
              : item.statusPembayaran
                ? 'Lihat Status Pembayaran'
                : 'Bayar Sekarang';

    return (
        <div className="space-y-5">
            {pendaftaran.status === 'perlu_perbaikan' && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                    <span className="font-semibold text-amber-800">Perlu diperbaiki: </span>
                    {pendaftaran.catatan_verifikasi ?? 'Staf PPDB meminta perbaikan data. Silakan hubungi sekolah untuk detailnya.'}
                </div>
            )}

            {pendaftaran.status === 'ditolak' && (
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <span className="font-semibold text-red-800">Pendaftaran ditutup: </span>
                    {pendaftaran.catatan_verifikasi ?? 'Silakan hubungi Staf PPDB untuk penjelasannya.'}
                </div>
            )}

            {pendaftaran.status === 'draft_kedaluwarsa' && (
                <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    Gelombang pendaftaran sudah ditutup. Draft ini tidak dapat dilanjutkan; silakan mendaftar kembali pada gelombang berikutnya.
                </div>
            )}

            {/* Checklist progres per-tahap, masing-masing dengan tombol aksinya sendiri */}
            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100">
                <div className="flex flex-col items-stretch gap-3 bg-[#F5F9FD] p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <ProgresBadge
                        label={
                            <>
                                <span className="sm:hidden">Formulir</span>
                                <span className="hidden sm:inline">Data Formulir (Calon Peserta + Wali)</span>
                            </>
                        }
                        keterangan="Data calon peserta dan wali"
                        selesai={formulirLengkap}
                    />
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="w-full shrink-0 rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981] sm:w-auto"
                    >
                        <Link
                            href={
                                bisaEditBerkas
                                    ? route('wali-murid.pendaftaran.edit', pendaftaran.id)
                                    : route('wali-murid.pendaftaran.show', pendaftaran.id)
                            }
                        >
                            {labelFormulir}
                        </Link>
                    </Button>
                </div>
                <div className="bg-[#F5F9FD] p-4">
                    <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <ProgresBadge
                            label={
                                <>
                                    <span className="sm:hidden">Berkas Persyaratan</span>
                                    <span className="hidden sm:inline">
                                        Berkas Persyaratan ({progres.berkasTerunggah}/{progres.berkasWajib})
                                    </span>
                                </>
                            }
                            keterangan={`${progres.berkasTerunggah}/${progres.berkasWajib} berkas terunggah`}
                            selesai={berkasLengkap}
                        />
                        {bisaEditBerkas && (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full shrink-0 rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981] sm:w-auto"
                            >
                                <Link href={route('wali-murid.pendaftaran.unggah-berkas', pendaftaran.id)}>{labelBerkas}</Link>
                            </Button>
                        )}
                    </div>
                    {/* Terkunci: file langsung diklik di sini, nggak perlu pindah halaman cuma buat lihat */}
                    {!bisaEditBerkas && item.dokumenList.length > 0 && (
                        <div className="mt-3 grid gap-2 sm:flex sm:flex-wrap">
                            {item.dokumenList.map((d, i) => (
                                <a
                                    key={i}
                                    href={d.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-[#1F509A] underline hover:bg-[#D4EBF8]/40 sm:w-auto sm:justify-start sm:rounded-full sm:py-1"
                                >
                                    <span className="truncate">{d.label}</span>
                                    <span aria-hidden="true" className="shrink-0 no-underline">
                                        ↗
                                    </span>
                                </a>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-stretch gap-3 bg-[#F5F9FD] p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <div className="min-w-0">
                        <ProgresBadge
                            label="Pembayaran"
                            keterangan={
                                !sudahBolehBayar && !['ditolak', 'draft_kedaluwarsa'].includes(pendaftaran.status)
                                    ? 'Menunggu berkas diverifikasi'
                                    : undefined
                            }
                            selesai={item.statusPembayaran === 'lunas'}
                        />
                        {item.statusPembayaran && (
                            <span
                                className={`mt-2 ml-7 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${pembayaranBadge[item.statusPembayaran].className}`}
                            >
                                {pembayaranBadge[item.statusPembayaran].label}
                            </span>
                        )}
                    </div>
                    {sudahBolehBayar ? (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="w-full shrink-0 rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981] sm:w-auto"
                        >
                            <Link href={route('wali-murid.pembayaran.show', pendaftaran.id)}>{labelTombolPembayaran}</Link>
                        </Button>
                    ) : !['ditolak', 'draft_kedaluwarsa'].includes(pendaftaran.status) ? (
                        <span className="hidden text-xs leading-relaxed text-gray-500 sm:block sm:shrink-0 sm:text-right">
                            Menunggu berkas diverifikasi
                        </span>
                    ) : null}
                </div>
            </div>

            {pendaftaran.status === 'perlu_perbaikan' && (
                <div>
                    <Button
                        onClick={() => router.post(route('wali-murid.pendaftaran.kirim-perbaikan', pendaftaran.id))}
                        disabled={!formulirLengkap || !berkasLengkap}
                        className="w-full rounded-xl py-3 text-sm font-bold"
                    >
                        Kirim Perbaikan
                    </Button>
                    {(!formulirLengkap || !berkasLengkap) && (
                        <p className="mt-2 text-center text-xs text-gray-500">
                            Lengkapi Formulir dan Berkas di atas dulu sebelum bisa mengirim perbaikan.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function ProgresBadge({ label, keterangan, selesai }: { label: ReactNode; keterangan?: string; selesai: boolean }) {
    return (
        <div className="flex min-w-0 items-start gap-2 text-sm">
            <span
                className={
                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ' +
                    (selesai ? 'bg-green-500 text-white' : 'border border-gray-300 text-gray-300')
                }
            >
                {selesai ? '✓' : ''}
            </span>
            <span className="min-w-0">
                <span className={`block ${selesai ? 'font-medium text-gray-700' : 'text-gray-500'}`}>{label}</span>
                {keterangan && <span className="mt-0.5 block text-xs leading-relaxed font-normal text-gray-500 sm:hidden">{keterangan}</span>}
            </span>
        </div>
    );
}
