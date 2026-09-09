import { DataTable } from '@/components/data-table';
import { FieldError, Input, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useCallback, useMemo, useState } from 'react';

interface KomponenItem {
    id: number;
    nama: string;
    keterangan: string | null;
    urutan: number;
    status_aktif: boolean;
    /**
     * Berapa baris tarif menggantung padanya. Tidak ditampilkan sebagai kolom —
     * dipakainya cuma untuk menentukan ada tidaknya tombol Hapus.
     */
    dipakai: number;
}

/**
 * Hapus bertingkat: klik pertama memunculkan penegasan, klik kedua menjalankan.
 * Bukan window.confirm — dialog bawaan browser membekukan halaman dan tidak bisa
 * menyebut apa yang ikut terhapus.
 */
function TombolHapus({ komponen }: { komponen: KomponenItem }) {
    const [tanya, setTanya] = useState(false);
    const [memproses, setMemproses] = useState(false);

    // Yang sudah punya nominal tidak bisa dihapus — jalurnya menonaktifkan,
    // lewat centang di modal Ubah. Tombolnya sengaja tidak muncul sama sekali.
    if (komponen.dipakai > 0) {
        return null;
    }

    if (!tanya) {
        return (
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setTanya(true)}
                className="rounded-xl border-red-200 bg-white font-semibold text-red-700 hover:bg-red-50 hover:text-red-800"
            >
                <Trash2 size={14} strokeWidth={2} />
                Hapus
            </Button>
        );
    }

    return (
        <div className="flex gap-2">
            <Button
                type="button"
                size="sm"
                disabled={memproses}
                onClick={() => {
                    setMemproses(true);
                    router.delete(route('super-admin.komponen-biaya.destroy', komponen.id), {
                        preserveScroll: true,
                        onFinish: () => setMemproses(false),
                    });
                }}
                className="rounded-xl bg-red-600 font-semibold text-white hover:bg-red-700"
            >
                {memproses ? '...' : 'Ya, hapus'}
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setTanya(false)}
                className="rounded-xl border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-50"
            >
                Batal
            </Button>
        </div>
    );
}

/**
 * Isi modal tambah/ubah. `baris` null berarti tambah baru.
 *
 * Sama seperti modal Tahun Ajaran: halaman induk memberinya `key` yang berganti
 * tiap kali modal dibuka, jadi isian selalu mulai dari baris yang barusan
 * diklik — bukan sisa ketikan dari baris sebelumnya.
 */
function FormulirKomponen({
    baris,
    urutanBerikutnya,
    tutup,
}: {
    baris: KomponenItem | null;
    urutanBerikutnya: number;
    tutup: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        nama: baris?.nama ?? '',
        keterangan: baris?.keterangan ?? '',
        urutan: String(baris?.urutan ?? urutanBerikutnya),
        status_aktif: baris?.status_aktif ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const opsi = { preserveScroll: true, onSuccess: () => tutup() };

        if (baris) {
            put(route('super-admin.komponen-biaya.update', baris.id), opsi);
        } else {
            post(route('super-admin.komponen-biaya.store'), opsi);
        }
    };

    return (
        <form onSubmit={submit}>
            <DialogHeader>
                <DialogTitle className="text-[17px] font-semibold text-gray-900">
                    {baris ? `Ubah Komponen — ${baris.nama}` : 'Tambah Komponen Biaya'}
                </DialogTitle>
            </DialogHeader>

            <div className="mt-5 space-y-5">
                <div className="grid gap-5 sm:grid-cols-[1fr_120px]">
                    <div>
                        <Label required htmlFor="nama">
                            Nama Komponen
                        </Label>
                        <Input id="nama" value={data.nama} onChange={(v) => setData('nama', v)} placeholder="Pembangunan" />
                        <FieldError message={errors.nama} />
                    </div>
                    <div>
                        <Label required htmlFor="urutan">
                            Urutan
                        </Label>
                        <Input id="urutan" value={data.urutan} onChange={(v) => setData('urutan', v)} placeholder="1" />
                        <FieldError message={errors.urutan} />
                    </div>
                </div>

                <div className="rounded-xl border border-[#D4EBF8] bg-[#F5F9FD] px-4 py-3.5">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="status_aktif"
                            checked={data.status_aktif}
                            onCheckedChange={(v) => setData('status_aktif', v === true)}
                            className="mt-0.5 border-[#1F509A]/40 bg-white data-[state=checked]:border-[#1F509A] data-[state=checked]:bg-[#1F509A] data-[state=checked]:text-white"
                        />
                        <label htmlFor="status_aktif" className="text-[13px] font-medium text-gray-800">
                            Aktifkan tagihan
                        </label>
                    </div>
                </div>

                <div>
                    <Label htmlFor="keterangan">
                        Keterangan <span className="text-gray-500">(opsional)</span>
                    </Label>
                    <textarea
                        id="keterangan"
                        rows={2}
                        value={data.keterangan}
                        onChange={(e) => setData('keterangan', e.target.value)}
                        placeholder="Biaya pembangunan & fasilitas sekolah, dibayar sekali saat diterima."
                        className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
                    />
                    <FieldError message={errors.keterangan} />
                    <p className="mt-1.5 text-xs text-gray-500">Ikut tersalin ke rincian tagihan yang dibaca wali.</p>
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
                    {processing ? 'Menyimpan...' : baris ? 'Simpan Perubahan' : 'Tambah Komponen'}
                </Button>
            </div>
        </form>
    );
}

function buatKolom(bukaFormulir: (baris: KomponenItem) => void): ColumnDef<KomponenItem>[] {
    return [
        {
            accessorKey: 'urutan',
            header: 'Urutan',
            cell: ({ row }) => <span className="text-gray-700">{row.original.urutan}</span>,
        },
        {
            accessorKey: 'nama',
            header: 'Komponen',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium text-gray-900">{row.original.nama}</p>
                    {row.original.keterangan && <p className="text-xs text-gray-500">{row.original.keterangan}</p>}
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
            id: 'aksi',
            header: 'Aksi',
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex flex-wrap items-center gap-2">
                    <TombolHapus komponen={row.original} />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => bukaFormulir(row.original)}
                        className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        Ubah
                    </Button>
                </div>
            ),
        },
    ];
}

export default function KomponenBiayaIndex({ komponen, urutanBerikutnya }: { komponen: KomponenItem[]; urutanBerikutnya: number }) {
    const [terbuka, setTerbuka] = useState(false);
    const [target, setTarget] = useState<KomponenItem | null>(null);
    /** Naik tiap kali modal dibuka, dipakai sebagai `key` supaya isian mulai bersih. */
    const [sesi, setSesi] = useState(0);

    const buka = useCallback((baris: KomponenItem | null) => {
        setTarget(baris);
        setSesi((n) => n + 1);
        setTerbuka(true);
    }, []);

    const tutup = () => setTerbuka(false);

    const columns = useMemo(() => buatKolom(buka), [buka]);

    return (
        <AppLayout>
            <Head title="Komponen Biaya" />
            <PageHeader title="Komponen Biaya" subtitle="Pos biaya PPDB — nominalnya diatur per jalur di menu Jalur Pendaftaran" wide />

            <PageContainer wide>
                {komponen.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <span className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[#D4EBF8]/60 text-[#1F509A]">
                            <Plus size={22} strokeWidth={1.8} />
                        </span>
                        <h2 className="text-[15px] font-semibold text-gray-900">Belum ada komponen biaya</h2>
                        <p className="mx-auto mt-1.5 max-w-md text-sm text-gray-500">
                            Selama daftar ini kosong, tagihan wali murid tidak akan terbit — tidak ada pos yang bisa ditagihkan.
                        </p>
                        <Button
                            type="button"
                            onClick={() => buka(null)}
                            className="mt-5 rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                        >
                            Tambah Komponen
                        </Button>
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={komponen}
                        searchPlaceholder="Cari komponen..."
                        searchWidth="max-w-xs"
                        emptyMessage="Tidak ada komponen yang cocok dengan pencarian."
                        toolbar={
                            <Button
                                type="button"
                                onClick={() => buka(null)}
                                className="ml-auto rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                            >
                                <Plus size={16} strokeWidth={2} />
                                Tambah Komponen
                            </Button>
                        }
                    />
                )}
            </PageContainer>

            <Dialog open={terbuka} onOpenChange={setTerbuka}>
                <DialogContent aria-describedby={undefined} className="max-h-[90vh] overflow-y-auto border-0 bg-white sm:max-w-lg sm:rounded-2xl">
                    <FormulirKomponen key={sesi} baris={target} urutanBerikutnya={urutanBerikutnya} tutup={tutup} />
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
