import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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
    agama: string | null;
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
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-teal-100 text-teal-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

// Status PELUNASAN gabungan (bisa dari beberapa transfer kalau dicicil),
// bukan status satu baris transfer - lihat PendaftaranPpdb::statusPelunasan().
const pembayaranBadge: Record<string, { label: string; className: string }> = {
    menunggu_verifikasi: { label: 'Menunggu Verifikasi', className: 'bg-amber-100 text-amber-700' },
    dicicil: { label: 'Dicicil', className: 'bg-amber-100 text-amber-700' },
    lunas: { label: 'Lunas', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

export default function PendaftaranIndex({ pendaftaranList, expandId, gelombangDibuka }: IndexProps) {
    const [search, setSearch] = useState('');

    const filtered = pendaftaranList.filter(
        (item) =>
            item.pendaftaran.nama_pendaftar.toLowerCase().includes(search.toLowerCase()) ||
            item.pendaftaran.nomor_pendaftaran.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <AppLayout>
            <Head title="Pendaftaran" />
            <PageHeader title="Pendaftaran" subtitle="Daftar seluruh pendaftaran PPDB yang kamu ajukan" />

            <PageContainer wide>
                <div className="mb-5 flex items-center justify-between gap-4">
                    <Input
                        placeholder="Cari nama atau nomor pendaftaran..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm border-gray-200 bg-white shadow-sm"
                    />
                    {/* Tombol cuma muncul kalau server memang menerima pendaftaran
                        baru - gerbangnya sama persis dengan yang dipakai store().
                        Menampilkan tombol yang pasti ditolak berarti membiarkan
                        wali mengisi 16 kolom cuma untuk kena error di akhir. */}
                    {gelombangDibuka ? (
                        <Button asChild className="shrink-0 rounded-xl px-5 py-2.5 text-sm font-bold">
                            <Link href={route('wali-murid.pendaftaran.create')}>+ Tambah Pendaftaran</Link>
                        </Button>
                    ) : (
                        <p className="shrink-0 text-sm text-gray-500">Pendaftaran sedang ditutup</p>
                    )}
                </div>

                {filtered.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center text-sm text-gray-500 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        {pendaftaranList.length === 0 ? 'Belum ada pendaftaran.' : 'Tidak ada hasil yang cocok.'}
                    </div>
                ) : (
                    <>
                        {/* Header label kolom - visual doang, bukan bagian dari Accordion */}
                        <div className="hidden h-12 grid-cols-6 items-center gap-4 rounded-t-2xl bg-[#0A3981] px-6 pr-10 text-xs font-bold tracking-wide text-white uppercase lg:grid">
                            <span>Nomor Pendaftaran</span>
                            <span>Nama Anak</span>
                            <span>Kategori</span>
                            <span>Gelombang</span>
                            <span>Tanggal Daftar</span>
                            <span>Status</span>
                        </div>

                        <Accordion
                            type="single"
                            collapsible
                            defaultValue={expandId ? String(expandId) : undefined}
                            className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] lg:rounded-t-none"
                        >
                            {filtered.map((item, i) => {
                                const badge = statusBadge[item.pendaftaran.status] ?? statusBadge.draft;
                                return (
                                    <AccordionItem
                                        key={item.pendaftaran.id}
                                        value={String(item.pendaftaran.id)}
                                        className={i !== filtered.length - 1 ? 'border-b border-gray-100' : 'border-b-0'}
                                    >
                                        <AccordionTrigger className="px-6 py-4 hover:bg-[#F5F9FD]/50 hover:no-underline">
                                            <div className="grid flex-1 grid-cols-1 gap-1 text-left text-sm font-normal lg:grid-cols-6 lg:items-center lg:gap-4">
                                                <span className="font-medium text-gray-700">{item.pendaftaran.nomor_pendaftaran}</span>
                                                <span className="text-gray-900">{item.pendaftaran.nama_pendaftar}</span>
                                                <span className="text-gray-600">{item.pendaftaran.kategori}</span>
                                                <span className="text-gray-600">{item.pendaftaran.gelombang}</span>
                                                <span className="text-gray-600">{item.pendaftaran.tanggal_daftar}</span>
                                                <span
                                                    className={`inline-block w-fit rounded-full px-2.5 py-1 text-xs font-semibold ${badge.className}`}
                                                >
                                                    {badge.label}
                                                </span>
                                            </div>
                                        </AccordionTrigger>
                                        <AccordionContent className="bg-[#F5F9FD]/30 px-6 pt-5 pb-6">
                                            <PendaftaranDetailPanel item={item} />
                                        </AccordionContent>
                                    </AccordionItem>
                                );
                            })}
                        </Accordion>
                    </>
                )}
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

    return (
        <div className="space-y-5">
            {pendaftaran.status === 'perlu_perbaikan' && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                    <span className="font-semibold text-amber-800">Perlu diperbaiki: </span>
                    {pendaftaran.catatan_verifikasi ?? 'Staf PPDB meminta perbaikan data. Silakan hubungi sekolah untuk detailnya.'}
                </div>
            )}

            {/* Checklist progres per-tahap, masing-masing dengan tombol aksinya sendiri */}
            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100">
                <div className="flex items-center justify-between gap-4 bg-[#F5F9FD] p-4">
                    <ProgresBadge label="Data Formulir (Calon Peserta + Wali)" selesai={formulirLengkap} />
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="shrink-0 border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
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
                    <div className="flex items-center justify-between gap-4">
                        <ProgresBadge label={`Berkas Persyaratan (${progres.berkasTerunggah}/${progres.berkasWajib})`} selesai={berkasLengkap} />
                        {bisaEditBerkas && (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="shrink-0 border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                            >
                                <Link href={route('wali-murid.pendaftaran.unggah-berkas', pendaftaran.id)}>{labelBerkas}</Link>
                            </Button>
                        )}
                    </div>
                    {/* Terkunci: file langsung diklik di sini, nggak perlu pindah halaman cuma buat lihat */}
                    {!bisaEditBerkas && item.dokumenList.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {item.dokumenList.map((d, i) => (
                                <a
                                    key={i}
                                    href={d.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="rounded-full bg-white px-3 py-1 text-xs font-medium text-[#1F509A] underline hover:bg-[#D4EBF8]/40"
                                >
                                    {d.label}
                                </a>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-between gap-4 bg-[#F5F9FD] p-4">
                    <div className="flex items-center gap-2">
                        <ProgresBadge label="Pembayaran" selesai={item.statusPembayaran === 'lunas'} />
                        {item.statusPembayaran && (
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${pembayaranBadge[item.statusPembayaran].className}`}>
                                {pembayaranBadge[item.statusPembayaran].label}
                            </span>
                        )}
                    </div>
                    {sudahBolehBayar ? (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="shrink-0 border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                        >
                            <Link href={route('wali-murid.pembayaran.show', pendaftaran.id)}>
                                {item.statusPembayaran ? 'Lihat Status Pembayaran' : 'Bayar Sekarang'}
                            </Link>
                        </Button>
                    ) : pendaftaran.status !== 'ditolak' ? (
                        <span className="shrink-0 text-xs text-gray-500">Menunggu berkas diverifikasi</span>
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

function ProgresBadge({ label, selesai }: { label: string; selesai: boolean }) {
    return (
        <div className="flex items-center gap-2 text-sm">
            <span
                className={
                    'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ' +
                    (selesai ? 'bg-green-500 text-white' : 'border border-gray-300 text-gray-300')
                }
            >
                {selesai ? '✓' : ''}
            </span>
            <span className={selesai ? 'font-medium text-gray-700' : 'text-gray-500'}>{label}</span>
        </div>
    );
}
