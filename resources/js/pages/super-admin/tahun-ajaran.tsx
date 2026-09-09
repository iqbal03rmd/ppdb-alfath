import { DataTable } from '@/components/data-table';
import { FieldError, Input, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { CalendarPlus } from 'lucide-react';
import { FormEventHandler, useCallback, useMemo, useState } from 'react';

interface TahunAjaranItem {
    id: number;
    nama: string;
    tahun_mulai: number;
    status_aktif: boolean;
    batas_pelunasan: string;
    /** Bentuk Y-m-d dari tanggal yang sama, buat mengisi <input type="date">. */
    batas_pelunasan_iso: string;
    jumlah_gelombang: number;
    jumlah_pendaftaran: number;
}

/**
 * Isi modal tambah/ubah. `baris` null berarti tambah baru.
 *
 * Dipisah jadi komponennya sendiri supaya `useForm` ikut lahir dan mati bersama
 * modalnya: halaman induk memberinya `key` yang berganti tiap kali modal dibuka,
 * jadi isian selalu mulai dari data baris yang barusan diklik — bukan sisa
 * ketikan dari baris yang dibuka sebelumnya.
 */
function FormulirTahunAjaran({
    baris,
    aktifSekarang,
    tutup,
}: {
    baris: TahunAjaranItem | null;
    /** Nama tahun ajaran yang aktif sekarang — dipakai buat menyebut siapa yang bakal tergeser. */
    aktifSekarang: string | null;
    tutup: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        nama: baris?.nama ?? '',
        tahun_mulai: String(baris?.tahun_mulai ?? new Date().getFullYear()),
        batas_pelunasan: baris?.batas_pelunasan_iso ?? '',
        status_aktif: baris?.status_aktif ?? false,
    });

    // Yang sedang aktif tidak bisa dinonaktifkan dari sini, jadi centangnya
    // dikunci menyala: nol tahun ajaran aktif bikin Beranda Kepala Sekolah
    // kosong. Memindahkannya tetap bisa — centang di tahun ajaran lain.
    const terkunci = baris?.status_aktif === true;

    // Keterangan cuma muncul kalau centangnya baru dinyalakan - cuma saat itu
    // ada akibat ke tahun ajaran lain yang perlu disebut. Yang sudah terkunci
    // aktif dan yang dibiarkan non-aktif tidak menerangkan apa-apa.
    const keteranganStatus =
        terkunci || !data.status_aktif ? null : aktifSekarang ? `${aktifSekarang} jadi non-aktif.` : 'Belum ada tahun ajaran yang aktif.';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Modal hanya ditutup kalau simpanannya benar-benar masuk. Kalau
        // validasi gagal, Inertia mengembalikan errors dan mempertahankan state
        // komponen (router.post/put default preserveState), jadi modalnya tetap
        // terbuka dengan pesan kesalahan di bawah isian yang salah.
        const opsi = { preserveScroll: true, onSuccess: () => tutup() };

        if (baris) {
            put(route('super-admin.tahun-ajaran.update', baris.id), opsi);
        } else {
            post(route('super-admin.tahun-ajaran.store'), opsi);
        }
    };

    return (
        <form onSubmit={submit}>
            <DialogHeader>
                <DialogTitle className="text-[17px] font-semibold text-gray-900">
                    {baris ? `Ubah Tahun Ajaran — ${baris.nama}` : 'Tambah Tahun Ajaran'}
                </DialogTitle>
            </DialogHeader>

            <div className="mt-5 space-y-5">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label required htmlFor="nama">
                            Nama
                        </Label>
                        <Input id="nama" value={data.nama} onChange={(v) => setData('nama', v)} placeholder="2026/2027" />
                        <FieldError message={errors.nama} />
                    </div>
                    <div>
                        <Label required htmlFor="tahun_mulai">
                            Tahun Mulai
                        </Label>
                        <Input id="tahun_mulai" value={data.tahun_mulai} onChange={(v) => setData('tahun_mulai', v)} placeholder="2026" />
                        <FieldError message={errors.tahun_mulai} />
                    </div>
                </div>

                <div className="rounded-xl border border-[#D4EBF8] bg-[#F5F9FD] px-4 py-3.5">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="status_aktif"
                            checked={data.status_aktif}
                            disabled={terkunci}
                            onCheckedChange={(v) => setData('status_aktif', v === true)}
                            className="mt-0.5 border-[#1F509A]/40 bg-white data-[state=checked]:border-[#1F509A] data-[state=checked]:bg-[#1F509A] data-[state=checked]:text-white"
                        />
                        <div>
                            <label htmlFor="status_aktif" className="block text-[13px] font-medium text-gray-800">
                                Aktifkan tahun ajaran ini
                            </label>
                            {keteranganStatus && <p className="mt-1 text-xs text-gray-500">{keteranganStatus}</p>}
                        </div>
                    </div>
                </div>

                <div>
                    <Label required htmlFor="batas_pelunasan">
                        Batas Pelunasan Cicilan
                    </Label>
                    {/* type="date" tidak ada di komponen Input bersama -
                        sengaja: cuma segelintir halaman yang butuh, dan
                        menambah varian ke sana bikin komponennya melar
                        buat semua orang. */}
                    <input
                        id="batas_pelunasan"
                        type="date"
                        value={data.batas_pelunasan}
                        onChange={(e) => setData('batas_pelunasan', e.target.value)}
                        className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
                    />
                    <FieldError message={errors.batas_pelunasan} />
                    <p className="mt-1.5 text-xs text-gray-500">Batas pelunasan seluruh cicilan pada tahun ajaran ini.</p>
                </div>
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={tutup}
                    className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                >
                    Batal
                </Button>
                <Button type="submit" disabled={processing} className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                    {processing ? 'Menyimpan...' : baris ? 'Simpan Perubahan' : 'Buat Tahun Ajaran'}
                </Button>
            </div>
        </form>
    );
}

function buatKolom(bukaFormulir: (baris: TahunAjaranItem) => void): ColumnDef<TahunAjaranItem>[] {
    return [
        {
            accessorKey: 'nama',
            header: 'Tahun Ajaran',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium text-gray-900">{row.original.nama}</p>
                    <p className="text-xs text-gray-500">Mulai {row.original.tahun_mulai}</p>
                </div>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            accessorFn: (row) => (row.status_aktif ? 'Aktif' : 'Non-aktif'),
            cell: ({ row }) =>
                row.original.status_aktif ? (
                    <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Aktif</span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Non-aktif</span>
                ),
        },
        {
            accessorKey: 'jumlah_gelombang',
            header: 'Gelombang',
            cell: ({ row }) => <span className="text-gray-700">{row.original.jumlah_gelombang}</span>,
        },
        {
            accessorKey: 'jumlah_pendaftaran',
            header: 'Pendaftar',
            cell: ({ row }) => <span className="text-gray-700">{row.original.jumlah_pendaftaran}</span>,
        },
        {
            accessorKey: 'batas_pelunasan',
            header: 'Pelunasan',
            cell: ({ row }) => <span className="text-gray-700">{row.original.batas_pelunasan}</span>,
        },
        {
            id: 'aksi',
            header: 'Aksi',
            enableSorting: false,
            cell: ({ row }) => (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => bukaFormulir(row.original)}
                    className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                >
                    Ubah
                </Button>
            ),
        },
    ];
}

export default function TahunAjaranIndex({ tahunAjaran }: { tahunAjaran: TahunAjaranItem[] }) {
    const [terbuka, setTerbuka] = useState(false);
    /** Baris yang sedang diubah; null berarti modalnya dipakai untuk tambah baru. */
    const [target, setTarget] = useState<TahunAjaranItem | null>(null);
    /**
     * Naik tiap kali modal dibuka, dan dipakai sebagai `key` formulirnya. Tanpa
     * ini, membuka "Tambah" dua kali berturut-turut memperlihatkan sisa ketikan
     * yang batal disimpan tadi — React menganggapnya komponen yang sama.
     */
    const [sesi, setSesi] = useState(0);

    // useCallback supaya kolom tabel di bawah tidak perlu dibangun ulang tiap
    // render; isinya cuma setter useState, yang memang tetap.
    const buka = useCallback((baris: TahunAjaranItem | null) => {
        setTarget(baris);
        setSesi((n) => n + 1);
        setTerbuka(true);
    }, []);

    // `target` sengaja TIDAK dikosongkan saat menutup: modal masih dirender
    // sepersekian detik selama animasi keluar, dan mengosongkannya di sini bikin
    // judul beserta isinya berkedip hilang duluan.
    const tutup = () => setTerbuka(false);

    const columns = useMemo(() => buatKolom(buka), [buka]);

    const aktifSekarang = tahunAjaran.find((t) => t.status_aktif)?.nama ?? null;

    return (
        <AppLayout>
            <Head title="Tahun Ajaran" />
            <PageHeader title="Tahun Ajaran" subtitle="Periode PPDB yang menaungi seluruh gelombang, biaya, dan kuota" wide />

            <PageContainer wide>
                {tahunAjaran.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <span className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[#D4EBF8]/60 text-[#1F509A]">
                            <CalendarPlus size={22} strokeWidth={1.8} />
                        </span>
                        <h2 className="text-[15px] font-semibold text-gray-900">Belum ada tahun ajaran</h2>
                        <p className="mx-auto mt-1.5 max-w-md text-sm text-gray-500">
                            Gelombang pendaftaran tidak bisa dibuat sebelum tahun ajarannya ada.
                        </p>
                        <Button
                            type="button"
                            onClick={() => buka(null)}
                            className="mt-5 rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                        >
                            Tambah Tahun Ajaran
                        </Button>
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={tahunAjaran}
                        searchPlaceholder="Cari tahun ajaran..."
                        searchWidth="max-w-xs"
                        emptyMessage="Tidak ada tahun ajaran yang cocok dengan pencarian."
                        toolbar={
                            <Button
                                type="button"
                                onClick={() => buka(null)}
                                className="ml-auto rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                            >
                                <CalendarPlus size={16} strokeWidth={2} />
                                Tambah Tahun Ajaran
                            </Button>
                        }
                    />
                )}
            </PageContainer>

            <Dialog open={terbuka} onOpenChange={setTerbuka}>
                <DialogContent aria-describedby={undefined} className="max-h-[90vh] overflow-y-auto border-0 bg-white sm:max-w-lg sm:rounded-2xl">
                    <FormulirTahunAjaran key={sesi} baris={target} aktifSekarang={aktifSekarang} tutup={tutup} />
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
