import { FieldError, Input, Kartu, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock, TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

interface GelombangExisting {
    id: number;
    tahun_ajaran_id: number;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    batas_waktu_pembayaran: string | null;
    minimal_pembayaran: number | null;
    status_buka: boolean;
    jumlah_pendaftaran: number;
    tagihan_sudah_terbit: number;
}

interface BarisKebijakan {
    kategori_siswa_id: number;
    nama: string;
    /** '' = tidak dibatasi. '0' = jalur tertutup. */
    kuota: string;
    /** '' = ikut minimal bayar bawaan gelombang. */
    minimal_bayar: string;
    terpakai: number;
    /** Kode berkas yang wajib diunggah pendaftar jalur ini, DI GELOMBANG INI. */
    dokumen: string[];
}

interface GelombangFormProps {
    pilihanTahunAjaran: { id: number; nama: string; aktif: boolean }[];
    gelombang?: GelombangExisting;
    /** Hanya saat MEMBUAT — nominal dasar yang menyebar ke semua jalur. */
    komponen?: { id: number; nama: string }[];
    /** Hanya saat MENGUBAH — kuota & minimal bayar tiap jalur. */
    kebijakan?: BarisKebijakan[];
    pilihanDokumen?: Record<string, string>;
}

const gayaIsian =
    'w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

export default function GelombangForm({ pilihanTahunAjaran, gelombang, komponen, kebijakan, pilihanDokumen }: GelombangFormProps) {
    const isEdit = !!gelombang;

    const { data, setData, post, put, processing, errors } = useForm<{
        tahun_ajaran_id: string;
        nama: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        batas_waktu_pembayaran: string;
        minimal_pembayaran: string;
        nominal_dasar: Record<string, string>;
        kebijakan: Record<string, { kuota: string; minimal_bayar: string }>;
        dokumen: Record<string, string[]>;
    }>({
        tahun_ajaran_id: String(gelombang?.tahun_ajaran_id ?? pilihanTahunAjaran.find((t) => t.aktif)?.id ?? ''),
        nama: gelombang?.nama ?? '',
        tanggal_mulai: gelombang?.tanggal_mulai ?? '',
        tanggal_selesai: gelombang?.tanggal_selesai ?? '',
        batas_waktu_pembayaran: gelombang?.batas_waktu_pembayaran ?? '',
        minimal_pembayaran:
            gelombang?.minimal_pembayaran !== null && gelombang?.minimal_pembayaran !== undefined ? String(gelombang.minimal_pembayaran) : '',
        nominal_dasar: Object.fromEntries((komponen ?? []).map((k) => [String(k.id), ''])),
        kebijakan: Object.fromEntries(
            (kebijakan ?? []).map((b) => [String(b.kategori_siswa_id), { kuota: b.kuota, minimal_bayar: b.minimal_bayar }]),
        ),
        dokumen: Object.fromEntries((kebijakan ?? []).map((b) => [String(b.kategori_siswa_id), b.dokumen])),
    });

    // Syarat berkas milik gelombang x jalur, bukan jalur saja — mengubahnya di
    // sini tidak menyentuh gelombang lain.
    const toggleDokumen = (kategoriId: number, jenis: string) => {
        const kunci = String(kategoriId);
        const sekarang = data.dokumen[kunci] ?? [];

        setData('dokumen', {
            ...data.dokumen,
            [kunci]: sekarang.includes(jenis) ? sekarang.filter((d) => d !== jenis) : [...sekarang, jenis],
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('super-admin.gelombang.update', gelombang.id));
        } else {
            post(route('super-admin.gelombang.store'));
        }
    };

    const kembali = route('super-admin.gelombang.index');
    const galat = errors as unknown as Record<string, string>;

    const ubahKebijakan = (id: number, kolom: 'kuota' | 'minimal_bayar', nilai: string) =>
        setData('kebijakan', { ...data.kebijakan, [String(id)]: { ...data.kebijakan[String(id)], [kolom]: nilai } });

    return (
        <AppLayout>
            <Head title={isEdit ? `Ubah ${gelombang.nama}` : 'Tambah Gelombang'} />
            <PageHeader
                title={isEdit ? `Ubah ${gelombang.nama}` : 'Tambah Gelombang'}
                subtitle={isEdit ? 'Jadwal, kuota, dan minimal bayar tiap jalur' : 'Jadwal pendaftaran dan nominal dasar tiap komponen'}
                wide
            />

            <PageContainer wide>
                <div className="mb-5">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        <Link href={kembali} aria-label="Kembali ke Gelombang PPDB" title="Kembali ke Gelombang PPDB">
                            <ArrowLeft size={18} strokeWidth={2} />
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Kartu judul="Jadwal Pendaftaran">
                            <div className="mb-5 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label required htmlFor="tahun_ajaran_id">
                                        Tahun Ajaran
                                    </Label>
                                    <select
                                        id="tahun_ajaran_id"
                                        value={data.tahun_ajaran_id}
                                        onChange={(e) => setData('tahun_ajaran_id', e.target.value)}
                                        className={gayaIsian}
                                    >
                                        <option value="">Pilih tahun ajaran</option>
                                        {pilihanTahunAjaran.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.nama}
                                                {t.aktif ? ' — aktif' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={errors.tahun_ajaran_id} />
                                </div>
                                <div>
                                    <Label required htmlFor="nama">
                                        Nama Gelombang
                                    </Label>
                                    <Input id="nama" value={data.nama} onChange={(v) => setData('nama', v)} placeholder="Gelombang 1" />
                                    <FieldError message={errors.nama} />
                                </div>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-3">
                                <div>
                                    <Label required htmlFor="tanggal_mulai">
                                        Dibuka
                                    </Label>
                                    <input
                                        id="tanggal_mulai"
                                        type="date"
                                        className={gayaIsian}
                                        value={data.tanggal_mulai}
                                        onChange={(e) => setData('tanggal_mulai', e.target.value)}
                                    />
                                    <FieldError message={errors.tanggal_mulai} />
                                </div>
                                <div>
                                    <Label required htmlFor="tanggal_selesai">
                                        Ditutup
                                    </Label>
                                    <input
                                        id="tanggal_selesai"
                                        type="date"
                                        className={gayaIsian}
                                        value={data.tanggal_selesai}
                                        onChange={(e) => setData('tanggal_selesai', e.target.value)}
                                    />
                                    <FieldError message={errors.tanggal_selesai} />
                                </div>
                                <div>
                                    <Label htmlFor="batas_waktu_pembayaran">Jatuh Tempo</Label>
                                    <input
                                        id="batas_waktu_pembayaran"
                                        type="date"
                                        className={gayaIsian}
                                        value={data.batas_waktu_pembayaran}
                                        onChange={(e) => setData('batas_waktu_pembayaran', e.target.value)}
                                    />
                                    <FieldError message={errors.batas_waktu_pembayaran} />
                                </div>
                            </div>
                            <p className="mt-1.5 text-xs text-gray-500">
                                Lewat jatuh tempo tanpa mencapai minimal bayar, staf boleh menutup pendaftarannya.
                            </p>
                        </Kartu>

                        <Kartu judul="Minimal Bayar Bawaan">
                            <Label htmlFor="minimal_pembayaran">
                                Nominal <span className="text-gray-500">(Rp)</span>
                            </Label>
                            <Input
                                id="minimal_pembayaran"
                                value={data.minimal_pembayaran}
                                onChange={(v) => setData('minimal_pembayaran', v)}
                                placeholder="3000000"
                            />
                            <p className="mt-1 text-xs text-gray-500">Berlaku untuk jalur yang tidak diberi angka sendiri di tabel bawah.</p>
                            <FieldError message={errors.minimal_pembayaran} />
                        </Kartu>

                        {/* Nominal dasar cuma ditawarkan saat MEMBUAT. Sesudah
                            gelombangnya ada, angkanya diubah per jalur — supaya
                            cuma ada satu tempat yang memegangnya. */}
                        {!isEdit && komponen && komponen.length > 0 && (
                            <Kartu judul="Nominal Dasar tiap Komponen">
                                <p className="mb-4 text-sm text-gray-500">
                                    Angka ini mengisi <b className="text-gray-700">semua jalur sekaligus</b> sebagai titik awal. Yang berbeda tinggal
                                    disesuaikan per jalur setelah gelombangnya dibuat. Komponen yang dikosongkan dilewati.
                                </p>

                                <div className="space-y-3">
                                    {komponen.map((k) => (
                                        <div key={k.id} className="flex flex-wrap items-center gap-4">
                                            <Label htmlFor={`nominal-${k.id}`}>
                                                <span className="sr-only">Nominal dasar</span>
                                            </Label>
                                            <span className="min-w-0 flex-1 text-sm text-gray-700">{k.nama}</span>
                                            <input
                                                id={`nominal-${k.id}`}
                                                type="number"
                                                min={0}
                                                value={data.nominal_dasar[String(k.id)] ?? ''}
                                                onChange={(e) => setData('nominal_dasar', { ...data.nominal_dasar, [String(k.id)]: e.target.value })}
                                                placeholder="Belum diatur"
                                                className={`${gayaIsian} w-44`}
                                                aria-label={`Nominal dasar ${k.nama}`}
                                            />
                                        </div>
                                    ))}
                                </div>
                                <FieldError message={galat['nominal_dasar']} />
                            </Kartu>
                        )}

                        {isEdit && kebijakan && (
                            <Kartu judul="Kuota, Minimal Bayar & Berkas tiap Jalur">
                                <div className="space-y-4">
                                    {kebijakan.map((b) => {
                                        const isian = data.kebijakan[String(b.kategori_siswa_id)] ?? { kuota: '', minimal_bayar: '' };
                                        const kuota = isian.kuota === '' ? null : Number(isian.kuota);
                                        const dibawahTerpakai = kuota !== null && kuota < b.terpakai;

                                        return (
                                            <div key={b.kategori_siswa_id} className="border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                                                <div className="mb-2 flex flex-wrap items-baseline gap-2">
                                                    <h3 className="text-sm font-semibold text-gray-900">{b.nama}</h3>
                                                    <span className="text-xs text-gray-500">
                                                        {b.terpakai} kursi terpakai
                                                        {kuota !== null && ` · sisa ${Math.max(0, kuota - b.terpakai)}`}
                                                    </span>
                                                </div>

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <Label htmlFor={`kuota-${b.kategori_siswa_id}`}>Kuota</Label>
                                                        <input
                                                            id={`kuota-${b.kategori_siswa_id}`}
                                                            type="number"
                                                            min={0}
                                                            value={isian.kuota}
                                                            onChange={(e) => ubahKebijakan(b.kategori_siswa_id, 'kuota', e.target.value)}
                                                            placeholder="Tidak dibatasi"
                                                            className={gayaIsian}
                                                        />
                                                        <FieldError message={galat[`kebijakan.${b.kategori_siswa_id}.kuota`]} />
                                                    </div>
                                                    <div>
                                                        <Label htmlFor={`minimal-${b.kategori_siswa_id}`}>Minimal Bayar (Rp)</Label>
                                                        <input
                                                            id={`minimal-${b.kategori_siswa_id}`}
                                                            type="number"
                                                            min={0}
                                                            value={isian.minimal_bayar}
                                                            onChange={(e) => ubahKebijakan(b.kategori_siswa_id, 'minimal_bayar', e.target.value)}
                                                            placeholder="Ikut bawaan"
                                                            className={gayaIsian}
                                                        />
                                                        <FieldError message={galat[`kebijakan.${b.kategori_siswa_id}.minimal_bayar`]} />
                                                    </div>
                                                </div>

                                                <div className="mt-4">
                                                    <p className="mb-2 text-[13px] font-medium text-gray-600">Berkas wajib</p>
                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        {Object.entries<string>(pilihanDokumen ?? {}).map(([jenis, label]) => (
                                                            <label
                                                                key={jenis}
                                                                htmlFor={`dok-${b.kategori_siswa_id}-${jenis}`}
                                                                className="flex items-start gap-2.5 text-sm text-gray-700"
                                                            >
                                                                <input
                                                                    id={`dok-${b.kategori_siswa_id}-${jenis}`}
                                                                    type="checkbox"
                                                                    checked={(data.dokumen[String(b.kategori_siswa_id)] ?? []).includes(jenis)}
                                                                    onChange={() => toggleDokumen(b.kategori_siswa_id, jenis)}
                                                                    className="mt-0.5 h-4 w-4 accent-[#1F509A]"
                                                                />
                                                                {label}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>

                                                {dibawahTerpakai && (
                                                    <p className="mt-2 flex items-start gap-2 rounded-xl bg-amber-50 p-2.5 text-xs text-amber-800">
                                                        <TriangleAlert size={14} strokeWidth={2} className="mt-0.5 shrink-0" />
                                                        <span>
                                                            Kuota di bawah jumlah terpakai. Yang sudah masuk tetap memegang kursinya — jalur ini cuma
                                                            tidak menerima pendaftar baru lagi.
                                                        </span>
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </Kartu>
                        )}

                        <div className="flex items-center gap-3">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                            >
                                {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Buat Gelombang'}
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                            >
                                <Link href={kembali}>Batal</Link>
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <Kartu judul="Arti Kolom Kosong">
                            <p className="text-sm text-gray-500">
                                <b className="text-gray-700">Kuota kosong</b> = tidak dibatasi. Diisi <b className="text-gray-700">0</b> justru
                                sebaliknya: jalurnya tertutup.
                            </p>
                            <p className="mt-3 text-sm text-gray-500">
                                Kuota sengaja bukan nol saat kosong — kalau terbalik, seluruh pendaftaran ikut tertutup gara-gara data yang belum
                                sempat diisi.
                            </p>
                        </Kartu>

                        {!isEdit && (
                            <Kartu judul="Lahir Tertutup">
                                <p className="text-sm text-gray-500">
                                    Gelombang baru selalu dibuat dalam keadaan tertutup. Membukanya tindakan tersendiri — karena membuka berarti
                                    menutup gelombang lain yang sedang berjalan.
                                </p>
                            </Kartu>
                        )}

                        {isEdit && gelombang.tagihan_sudah_terbit > 0 && (
                            <Kartu judul="Yang Tidak Ikut Berubah">
                                <p className="flex items-start gap-2 text-sm text-gray-500">
                                    <Lock size={15} strokeWidth={2} className="mt-0.5 shrink-0 text-[#1F509A]" />
                                    <span>
                                        <b className="text-gray-700">{gelombang.tagihan_sudah_terbit} pendaftaran</b> di gelombang ini tagihannya
                                        sudah terbit. Minimal bayar mereka sudah dibekukan dan tidak ikut berubah.
                                    </span>
                                </p>
                                <p className="mt-3 text-sm text-gray-500">
                                    Itu disengaja: kalau ikut berubah, menaikkan angka hari ini bisa membatalkan status diterima orang yang sudah
                                    membayar kemarin.
                                </p>
                            </Kartu>
                        )}
                    </div>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
