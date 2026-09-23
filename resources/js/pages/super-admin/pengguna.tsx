import ConfirmationDialog from '@/components/confirmation-dialog';
import { DataTable } from '@/components/data-table';
import { FieldError, Input, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronDown, Lock, Minus, Plus, UserPlus } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface PenggunaItem {
    id: number;
    name: string;
    email: string;
    telepon: string | null;
    role: string;
    /** Label peran untuk tabel; `role` tetap disertakan untuk nilai formulir. */
    peran: string;
    status_aktif: boolean;
    jumlah_pendaftaran: number;
    diri_sendiri: boolean;
    alasan_peran_terkunci: string | null;
    bisa_dihapus: boolean;
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

const buatKolom = (ubah: (pengguna: PenggunaItem) => void): ColumnDef<PenggunaItem>[] => [
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
                type="button"
                variant="outline"
                size="sm"
                onClick={() => ubah(row.original)}
                className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
            >
                Ubah
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
    /** null = tertutup; pengguna null di dalam objek = mode tambah. */
    const [modal, setModal] = useState<{ pengguna: PenggunaItem | null } | null>(null);

    const barisTersaring = useMemo(
        () => pengguna.filter((p) => (!saringPeran || p.peran === saringPeran) && (!saringStatus || (saringStatus === 'aktif') === p.status_aktif)),
        [pengguna, saringPeran, saringStatus],
    );

    const adaPenyaring = saringPeran !== '' || saringStatus !== '';
    const columns = useMemo(() => buatKolom((pengguna) => setModal({ pengguna })), []);
    // Sesudah status akun diubah, Inertia memperbarui props tanpa membuang state
    // halaman. Ambil versi terbaru dari daftar agar badge di modal ikut berubah.
    const penggunaModal = modal?.pengguna ? (pengguna.find((item) => item.id === modal.pengguna?.id) ?? modal.pengguna) : null;

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
                    searchWidth="max-w-none sm:max-w-xs"
                    emptyMessage={
                        adaPenyaring
                            ? 'Tidak ada akun yang cocok dengan penyaring ini. Pilih "Semua peran" atau "Semua status" untuk melihat sisanya.'
                            : 'Tidak ada akun yang cocok dengan pencarian.'
                    }
                    toolbar={
                        <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-1 sm:items-center sm:gap-3">
                            <Penyaring lebar="w-full sm:w-44">
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

                            <Penyaring lebar="w-full sm:w-40">
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
                                <span className="col-span-2 text-xs text-gray-500 sm:col-span-1">
                                    {barisTersaring.length} dari {pengguna.length} akun
                                </span>
                            )}

                            {/* ml-auto mendorongnya ke ujung kanan baris, jadi
                                jaraknya ke penyaring ikut melebar sendiri saat
                                layar besar - tidak perlu jarak yang dipatok. */}
                            <Button
                                type="button"
                                onClick={() => setModal({ pengguna: null })}
                                className="col-span-2 w-full rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90 sm:ml-auto sm:w-auto"
                            >
                                <UserPlus size={16} strokeWidth={2} />
                                Tambah Pengguna
                            </Button>
                        </div>
                    }
                    rowId={(item) => String(item.id)}
                    mobileHeader={
                        <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                            <span aria-hidden />
                            <span>Pengguna</span>
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
                                    aria-label={`${expanded ? 'Tutup' : 'Buka'} detail pengguna ${item.name}`}
                                    className={`mt-0.5 flex h-7 w-7 items-center justify-center rounded-full transition-colors ${
                                        expanded ? 'bg-[#0A3981] text-white' : 'bg-[#E8EEF7] text-[#1F509A] hover:bg-[#D4EBF8]'
                                    }`}
                                >
                                    {expanded ? <Minus className="h-4 w-4" aria-hidden="true" /> : <Plus className="h-4 w-4" aria-hidden="true" />}
                                </button>

                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-gray-900" title={item.name}>
                                        {item.name}
                                        {item.diri_sendiri && <span className="ml-1 text-[11px] font-normal text-gray-500">(Anda)</span>}
                                    </p>
                                    <p className="mt-0.5 truncate text-[11px] text-gray-500" title={item.email}>
                                        {item.email}
                                    </p>
                                </div>

                                <span
                                    className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ${
                                        item.status_aktif ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                                    }`}
                                >
                                    {item.status_aktif ? 'Aktif' : 'Nonaktif'}
                                </span>
                            </div>

                            {expanded && (
                                <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4 pl-[3.75rem]">
                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-gray-500">Peran</dt>
                                            <dd className="mt-0.5 font-medium text-gray-900">{item.peran}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-gray-500">Telepon</dt>
                                            <dd className="mt-0.5 font-medium break-words text-gray-900">{item.telepon || '—'}</dd>
                                        </div>
                                        <div className="col-span-2">
                                            <dt className="text-xs text-gray-500">Pendaftaran Terkait</dt>
                                            <dd className="mt-0.5 font-medium text-gray-900">{item.jumlah_pendaftaran} pendaftaran</dd>
                                        </div>
                                    </dl>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setModal({ pengguna: item })}
                                        className="mt-4 w-full rounded-xl border-[#1F509A]/40 bg-white text-xs font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                    >
                                        Ubah Pengguna
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                />
            </PageContainer>

            {modal && (
                <FormulirPengguna key={modal.pengguna?.id ?? 'pengguna-baru'} pengguna={penggunaModal} peran={peran} tutup={() => setModal(null)} />
            )}
        </AppLayout>
    );
}

/**
 * Isi modal tambah/ubah. Sama seperti modal Konfigurasi PPDB, komponen lokal ini
 * sengaja hidup di file halamannya dan diberi key agar state form selalu baru.
 */
function FormulirPengguna({ pengguna, peran, tutup }: { pengguna: PenggunaItem | null; peran: Record<string, string>; tutup: () => void }) {
    const isEdit = pengguna !== null;
    const peranTerkunci = !!pengguna?.alasan_peran_terkunci;
    const [konfirmasiTutup, setKonfirmasiTutup] = useState(false);
    const [konfirmasiNonaktif, setKonfirmasiNonaktif] = useState(false);
    const [konfirmasiHapus, setKonfirmasiHapus] = useState(false);
    const [memprosesStatus, setMemprosesStatus] = useState(false);
    const [memprosesHapus, setMemprosesHapus] = useState(false);
    const { data, setData, post, put, processing, errors, isDirty, reset, clearErrors } = useForm({
        name: pengguna?.name ?? '',
        email: pengguna?.email ?? '',
        telepon: pengguna?.telepon ?? '',
        role: pengguna?.role ?? '',
        password: '',
        password_confirmation: '',
    });

    const tutupLangsung = () => {
        clearErrors();
        reset();
        tutup();
    };

    const mintaTutup = () => {
        if (processing || memprosesStatus || memprosesHapus) return;
        if (isDirty) setKonfirmasiTutup(true);
        else tutupLangsung();
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: tutupLangsung };

        if (pengguna) put(route('super-admin.pengguna.update', pengguna.id), opsi);
        else post(route('super-admin.pengguna.store'), opsi);
    };

    const ubahStatus = (statusAktif: boolean) => {
        if (!pengguna) return;

        setMemprosesStatus(true);
        router.post(
            route('super-admin.pengguna.status', pengguna.id),
            { status_aktif: statusAktif },
            {
                preserveScroll: true,
                onSuccess: () => setKonfirmasiNonaktif(false),
                onFinish: () => setMemprosesStatus(false),
            },
        );
    };

    const hapusAkun = () => {
        if (!pengguna?.bisa_dihapus) return;

        setMemprosesHapus(true);
        router.delete(route('super-admin.pengguna.destroy', pengguna.id), {
            preserveScroll: true,
            onSuccess: tutupLangsung,
            onFinish: () => setMemprosesHapus(false),
        });
    };

    return (
        <>
            <Dialog open onOpenChange={(terbuka) => !terbuka && mintaTutup()}>
                <DialogContent className="flex max-h-[90vh] flex-col overflow-hidden border-0 bg-white p-0 shadow-2xl sm:max-w-2xl sm:rounded-2xl">
                    <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                        <DialogHeader className="shrink-0 border-b border-gray-100 px-6 py-5 pr-12 text-left">
                            <DialogTitle className="text-lg font-semibold text-gray-900">
                                {isEdit ? `Ubah Akun — ${pengguna.name}` : 'Tambah Pengguna'}
                            </DialogTitle>
                            <DialogDescription className="text-sm text-gray-500">
                                {isEdit ? 'Perbarui identitas, peran, atau kata sandi akun.' : 'Buat akun baru dan tentukan perannya dalam sistem.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                            <section>
                                <h3 className="mb-4 text-sm font-semibold text-gray-900">Identitas Akun</h3>
                                <div className="space-y-4">
                                    <div>
                                        <Label required htmlFor="name">
                                            Nama Lengkap
                                        </Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(nilai) => setData('name', nilai)}
                                            placeholder="Nama sesuai identitas"
                                        />
                                        <FieldError message={errors.name} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label required htmlFor="email">
                                                Email
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                autoComplete="off"
                                                value={data.email}
                                                onChange={(nilai) => setData('email', nilai)}
                                                placeholder="nama@sekolah.sch.id"
                                            />
                                            <FieldError message={errors.email} />
                                        </div>
                                        <div>
                                            <Label htmlFor="telepon">Telepon (opsional)</Label>
                                            <Input
                                                id="telepon"
                                                value={data.telepon}
                                                onChange={(nilai) => setData('telepon', nilai)}
                                                placeholder="08xxxxxxxxxx"
                                            />
                                            <FieldError message={errors.telepon} />
                                        </div>
                                    </div>

                                    <div>
                                        <Label required htmlFor="role">
                                            Peran dalam sistem
                                        </Label>
                                        <select
                                            id="role"
                                            value={data.role}
                                            disabled={peranTerkunci}
                                            onChange={(event) => setData('role', event.target.value)}
                                            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-600"
                                        >
                                            <option value="">Pilih peran</option>
                                            {Object.entries(peran).map(([nilai, label]) => (
                                                <option key={nilai} value={nilai}>
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError message={errors.role} />
                                        {pengguna?.alasan_peran_terkunci && (
                                            <p className="mt-2 flex items-start gap-2 rounded-xl bg-[#D4EBF8]/60 p-3 text-xs text-[#0A3981]">
                                                <Lock size={14} strokeWidth={2} className="mt-0.5 shrink-0" />
                                                <span>{pengguna.alasan_peran_terkunci}</span>
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="border-t border-gray-100 pt-5">
                                <h3 className="text-sm font-semibold text-gray-900">{isEdit ? 'Ganti Kata Sandi' : 'Kata Sandi'}</h3>
                                {isEdit && <p className="mt-1 mb-4 text-xs text-gray-500">Biarkan kosong jika kata sandi tidak perlu diubah.</p>}
                                <div className={`grid gap-4 sm:grid-cols-2 ${isEdit ? '' : 'mt-4'}`}>
                                    <div>
                                        <Label required={!isEdit} htmlFor="password">
                                            {isEdit ? 'Kata Sandi Baru' : 'Kata Sandi'}
                                        </Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            autoComplete="new-password"
                                            value={data.password}
                                            onChange={(nilai) => setData('password', nilai)}
                                            placeholder="Minimal 8 karakter"
                                        />
                                        <FieldError message={errors.password} />
                                    </div>
                                    <div>
                                        <Label required={!isEdit} htmlFor="password_confirmation">
                                            Ulangi Kata Sandi
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
                                            value={data.password_confirmation}
                                            onChange={(nilai) => setData('password_confirmation', nilai)}
                                            placeholder="Ketik ulang kata sandinya"
                                        />
                                        <FieldError message={errors.password_confirmation} />
                                    </div>
                                </div>
                            </section>

                            {pengguna && (
                                <section className="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-sm font-semibold text-gray-900">Hak Masuk</h3>
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                    pengguna.status_aktif ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                                                }`}
                                            >
                                                {pengguna.status_aktif ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-gray-500">
                                            {pengguna.diri_sendiri
                                                ? 'Hak masuk akun Anda sendiri tidak dapat dicabut.'
                                                : pengguna.status_aktif
                                                  ? 'Menonaktifkan akun tidak menghapus data atau pendaftarannya.'
                                                  : 'Akun ini tidak dapat masuk sampai diaktifkan kembali.'}
                                        </p>
                                    </div>

                                    {!pengguna.diri_sendiri && (
                                        <div className="flex shrink-0 flex-wrap gap-2">
                                            {pengguna.status_aktif ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={memprosesStatus || memprosesHapus}
                                                    onClick={() => setKonfirmasiNonaktif(true)}
                                                    className="rounded-xl border-red-200 bg-white font-semibold text-red-700 hover:bg-red-50 hover:text-red-800"
                                                >
                                                    Nonaktifkan Akun
                                                </Button>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    disabled={memprosesStatus || memprosesHapus}
                                                    onClick={() => ubahStatus(true)}
                                                    className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                                                >
                                                    {memprosesStatus ? 'Memproses...' : 'Aktifkan Kembali'}
                                                </Button>
                                            )}

                                            {pengguna.bisa_dihapus && (
                                                <Button
                                                    type="button"
                                                    disabled={memprosesStatus || memprosesHapus}
                                                    onClick={() => setKonfirmasiHapus(true)}
                                                    className="rounded-xl bg-red-600 font-semibold text-white hover:bg-red-700"
                                                >
                                                    Hapus Akun
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </section>
                            )}
                        </div>

                        <DialogFooter className="shrink-0 gap-2 border-t border-gray-100 bg-gray-50/80 px-6 py-4 sm:space-x-0">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing || memprosesStatus || memprosesHapus}
                                onClick={mintaTutup}
                                className="rounded-xl border-gray-300 bg-white font-semibold text-gray-700"
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || memprosesStatus || memprosesHapus}
                                className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                            >
                                {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Buat Akun'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog
                open={konfirmasiTutup}
                title="Perubahan belum disimpan"
                description="Perubahan pada akun ini akan hilang jika formulir ditutup."
                confirmLabel="Tutup formulir"
                cancelLabel="Lanjut mengisi"
                tone="warning"
                onConfirm={tutupLangsung}
                onCancel={() => setKonfirmasiTutup(false)}
            />

            <ConfirmationDialog
                open={konfirmasiNonaktif}
                title="Nonaktifkan akun?"
                description={`Akun ${pengguna?.name ?? ''} langsung kehilangan akses masuk. Data akun${
                    pengguna && pengguna.jumlah_pendaftaran > 0 ? ` dan ${pengguna.jumlah_pendaftaran} pendaftarannya` : ''
                }} tetap tersimpan.`}
                confirmLabel={memprosesStatus ? 'Memproses...' : 'Ya, nonaktifkan'}
                confirmDisabled={memprosesStatus}
                cancelLabel="Batal"
                tone="danger"
                onConfirm={() => ubahStatus(false)}
                onCancel={() => !memprosesStatus && setKonfirmasiNonaktif(false)}
            />

            <ConfirmationDialog
                open={konfirmasiHapus}
                title="Hapus akun secara permanen?"
                description={`Akun ${pengguna?.name ?? ''} akan dihapus dan tidak dapat dipulihkan. Jika sedang masuk, sesinya langsung berakhir.`}
                confirmLabel={memprosesHapus ? 'Menghapus...' : 'Ya, hapus permanen'}
                cancelLabel="Batal"
                tone="danger"
                confirmDisabled={memprosesHapus}
                onConfirm={hapusAkun}
                onCancel={() => !memprosesHapus && setKonfirmasiHapus(false)}
            />
        </>
    );
}
