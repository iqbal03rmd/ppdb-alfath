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

interface JalurItem {
    id: number;
    urutan: number;
    nama: string;
    deskripsi: string | null;
    pertanyaan_khusus: string | null;
    status_aktif: boolean;
    jumlah_pendaftaran: number;
    /**
     * Jalur tanpa pendaftar sama sekali. Dihitung backend — foreign key
     * pendaftaran_ppdb.kategori_siswa_id sengaja tidak cascade.
     */
    bisa_dihapus: boolean;
}

function TombolHapus({ jalur }: { jalur: JalurItem }) {
    const [tanya, setTanya] = useState(false);
    const [memproses, setMemproses] = useState(false);

    // Jalur yang sudah dipakai tidak bisa dihapus — riwayat, berkas, dan
    // pembayaran pendaftarnya menggantung padanya. Yang dipakai kalau sekolah
    // berhenti membukanya: matikan statusnya lewat Ubah.
    if (!jalur.bisa_dihapus) {
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
                    router.delete(route('super-admin.jalur.destroy', jalur.id), {
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
 * Isinya sifat jalur saja. Nominal per komponen TIDAK ada di sini: dia bersifat
 * gelombang × jalur, jadi tempatnya nanti di layar Ubah Gelombang - bareng
 * kuota, minimal bayar, dan berkas wajib yang sudah lebih dulu pindah ke sana.
 */
function FormulirJalur({ baris, urutanBerikutnya, tutup }: { baris: JalurItem | null; urutanBerikutnya: number; tutup: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        nama: baris?.nama ?? '',
        urutan: String(baris?.urutan ?? urutanBerikutnya),
        deskripsi: baris?.deskripsi ?? '',
        pertanyaan_khusus: baris?.pertanyaan_khusus ?? '',
        status_aktif: baris?.status_aktif ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const opsi = { preserveScroll: true, onSuccess: () => tutup() };

        if (baris) {
            put(route('super-admin.jalur.update', baris.id), opsi);
        } else {
            post(route('super-admin.jalur.store'), opsi);
        }
    };

    const gayaIsian =
        'w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

    return (
        <form onSubmit={submit}>
            <DialogHeader>
                <DialogTitle className="text-[17px] font-semibold text-gray-900">
                    {baris ? `Ubah Jalur — ${baris.nama}` : 'Tambah Jalur Pendaftaran'}
                </DialogTitle>
            </DialogHeader>

            <div className="mt-5 space-y-5">
                <div className="grid gap-5 sm:grid-cols-[1fr_120px]">
                    <div>
                        <Label required htmlFor="nama">
                            Nama Jalur
                        </Label>
                        <Input id="nama" value={data.nama} onChange={(v) => setData('nama', v)} placeholder="Reguler" />
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
                            Aktif
                        </label>
                    </div>
                </div>

                <div>
                    <Label required htmlFor="deskripsi">
                        Keterangan
                    </Label>
                    <textarea
                        id="deskripsi"
                        rows={2}
                        value={data.deskripsi}
                        onChange={(e) => setData('deskripsi', e.target.value)}
                        placeholder="Kalimat yang menolong wali memutuskan apakah anaknya masuk jalur ini."
                        className={gayaIsian}
                    />
                    <FieldError message={errors.deskripsi} />
                    <p className="mt-1.5 text-xs text-gray-500">Tampil tepat di bawah pilihan jalur saat wali mengisi formulir.</p>
                </div>

                <div>
                    <Label htmlFor="pertanyaan_khusus">
                        Pertanyaan Khusus <span className="text-gray-500">(opsional)</span>
                    </Label>
                    <Input
                        id="pertanyaan_khusus"
                        value={data.pertanyaan_khusus}
                        onChange={(v) => setData('pertanyaan_khusus', v)}
                        placeholder="Nama saudara yang bersekolah di sini"
                    />
                    <FieldError message={errors.pertanyaan_khusus} />
                    <p className="mt-1.5 text-xs text-gray-500">
                        Jadi satu isian wajib buat wali yang memilih jalur ini. Kosongkan kalau tidak perlu.
                    </p>
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
                    {processing ? 'Menyimpan...' : baris ? 'Simpan Perubahan' : 'Tambah Jalur'}
                </Button>
            </div>
        </form>
    );
}

function buatKolom(bukaFormulir: (baris: JalurItem) => void): ColumnDef<JalurItem>[] {
    return [
        {
            accessorKey: 'urutan',
            header: 'Urutan',
            cell: ({ row }) => <span className="text-gray-700">{row.original.urutan}</span>,
        },
        {
            accessorKey: 'nama',
            header: 'Jalur',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium text-gray-900">{row.original.nama}</p>
                    {row.original.deskripsi && <p className="text-xs text-gray-500">{row.original.deskripsi}</p>}
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
                    <TombolHapus jalur={row.original} />
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

export default function JalurIndex({ jalur, urutanBerikutnya }: { jalur: JalurItem[]; urutanBerikutnya: number }) {
    const [terbuka, setTerbuka] = useState(false);
    const [target, setTarget] = useState<JalurItem | null>(null);
    /** Naik tiap kali modal dibuka, dipakai sebagai `key` supaya isian mulai bersih. */
    const [sesi, setSesi] = useState(0);

    const buka = useCallback((baris: JalurItem | null) => {
        setTarget(baris);
        setSesi((n) => n + 1);
        setTerbuka(true);
    }, []);

    const tutup = () => setTerbuka(false);

    const columns = useMemo(() => buatKolom(buka), [buka]);

    return (
        <AppLayout>
            <Head title="Jalur Pendaftaran" />
            <PageHeader title="Jalur Pendaftaran" subtitle="Pilihan jalur yang bisa diambil pendaftar" wide />

            <PageContainer wide>
                {jalur.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <span className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[#D4EBF8]/60 text-[#1F509A]">
                            <Plus size={22} strokeWidth={1.8} />
                        </span>
                        <h2 className="text-[15px] font-semibold text-gray-900">Belum ada jalur pendaftaran</h2>
                        <p className="mx-auto mt-1.5 max-w-md text-sm text-gray-500">
                            Wali murid tidak bisa mengisi formulir tanpa setidaknya satu jalur.
                        </p>
                        <Button
                            type="button"
                            onClick={() => buka(null)}
                            className="mt-5 rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                        >
                            Tambah Jalur
                        </Button>
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={jalur}
                        searchPlaceholder="Cari jalur..."
                        searchWidth="max-w-xs"
                        emptyMessage="Tidak ada jalur yang cocok dengan pencarian."
                        toolbar={
                            <Button
                                type="button"
                                onClick={() => buka(null)}
                                className="ml-auto rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                            >
                                <Plus size={16} strokeWidth={2} />
                                Tambah Jalur
                            </Button>
                        }
                    />
                )}
            </PageContainer>

            <Dialog open={terbuka} onOpenChange={setTerbuka}>
                <DialogContent aria-describedby={undefined} className="max-h-[90vh] overflow-y-auto border-0 bg-white sm:max-w-lg sm:rounded-2xl">
                    <FormulirJalur key={sesi} baris={target} urutanBerikutnya={urutanBerikutnya} tutup={tutup} />
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
