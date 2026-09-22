import ConfirmationDialog from '@/components/confirmation-dialog';
import { FieldError, Input, Kartu, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { PendingVisit, VisitOptions } from '@inertiajs/core';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock, TriangleAlert } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

interface GelombangExisting {
    id: number;
    tahun_ajaran_id: number;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    biaya_pendaftaran: number;
    batas_waktu_pembayaran: string | null;
    status_buka: boolean;
    bisa_pindah_tahun_ajaran: boolean;
}

interface BarisKebijakan {
    kategori_siswa_id: number;
    nama: string;
    /** '' = tidak dibatasi. '0' = jalur tertutup. */
    kuota: string;
    /** Wajib diisi. '0' = diterima tanpa menyetor. */
    minimal_bayar: string;
    terpakai: number;
    /** Kode berkas yang wajib diunggah pendaftar jalur ini, DI GELOMBANG INI. */
    dokumen: string[];
    /** id komponen -> nominal. '' = pos ini tidak ditagihkan ke jalur ini sama sekali. */
    tarif: Record<string, string>;
}

interface GelombangFormProps {
    pilihanTahunAjaran: { id: number; nama: string; aktif: boolean }[];
    gelombang?: GelombangExisting;
    /** Pos biaya yang masih aktif, diisi langsung per jalur. */
    komponen?: { id: number; nama: string }[];
    /** Kuota, minimal bayar, nominal, dan berkas tiap jalur untuk Tambah maupun Ubah. */
    kebijakan?: BarisKebijakan[];
    pilihanDokumen?: Record<string, string>;
    /** Kalimat sebab kalau layar ini cuma boleh dibaca. null = boleh diubah. */
    terkunci?: string | null;
}

const gayaIsian =
    'w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-600';

export default function GelombangForm({ pilihanTahunAjaran, gelombang, komponen, kebijakan, pilihanDokumen, terkunci }: GelombangFormProps) {
    const isEdit = !!gelombang;
    // Layar yang sama merangkap dua peran: formulir Ubah, dan layar baca-saja
    // buat gelombang yang ketentuannya sudah dikunci. Yang benar-benar menjaga
    // tetap server - ini supaya tidak ada yang mengetik lalu kecewa.
    const bacaSaja = !!terkunci;

    const { data, setData, post, put, processing, errors, isDirty } = useForm<{
        tahun_ajaran_id: string;
        nama: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        biaya_pendaftaran: string;
        batas_waktu_pembayaran: string;
        kebijakan: Record<string, { kuota: string; minimal_bayar: string }>;
        dokumen: Record<string, string[]>;
        tarif: Record<string, Record<string, string>>;
    }>({
        tahun_ajaran_id: String(gelombang?.tahun_ajaran_id ?? pilihanTahunAjaran.find((t) => t.aktif)?.id ?? ''),
        nama: gelombang?.nama ?? '',
        tanggal_mulai: gelombang?.tanggal_mulai ?? '',
        tanggal_selesai: gelombang?.tanggal_selesai ?? '',
        biaya_pendaftaran: String(gelombang?.biaya_pendaftaran ?? 125000),
        batas_waktu_pembayaran: gelombang?.batas_waktu_pembayaran ?? '',
        kebijakan: Object.fromEntries(
            (kebijakan ?? []).map((b) => [String(b.kategori_siswa_id), { kuota: b.kuota, minimal_bayar: b.minimal_bayar }]),
        ),
        dokumen: Object.fromEntries((kebijakan ?? []).map((b) => [String(b.kategori_siswa_id), b.dokumen])),
        tarif: Object.fromEntries((kebijakan ?? []).map((b) => [String(b.kategori_siswa_id), b.tarif])),
    });

    const [jalurAktif, setJalurAktif] = useState(kebijakan?.[0]?.kategori_siswa_id ?? 0);
    const [sudahMencobaSimpan, setSudahMencobaSimpan] = useState(false);
    const [kunjunganTertunda, setKunjunganTertunda] = useState<PendingVisit | null>(null);
    const sedangMenyimpan = useRef(false);
    const lewatiPengamanSekali = useRef(false);

    // Satu-satunya isian wajib di dalam tab adalah minimal bayar. Kuota boleh
    // kosong (tak dibatasi), nominal boleh kosong (tidak ditagihkan), dan
    // daftar berkas boleh kosong kalau memang tidak ada yang diwajibkan.
    const jalurBelumLengkap = useMemo(
        () =>
            (kebijakan ?? [])
                .filter((b) => (data.kebijakan[String(b.kategori_siswa_id)]?.minimal_bayar ?? '').trim() === '')
                .map((b) => b.kategori_siswa_id),
        [data.kebijakan, kebijakan],
    );

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

        setSudahMencobaSimpan(true);

        if (jalurBelumLengkap.length > 0) {
            setJalurAktif(jalurBelumLengkap[0]);

            return;
        }

        const opsi = {
            // onBefore milik visit dipanggil sebelum event global Inertia, jadi
            // pengaman perubahan tidak menghalangi tombol Simpan itu sendiri.
            onBefore: () => {
                sedangMenyimpan.current = true;
            },
            onFinish: () => {
                sedangMenyimpan.current = false;
            },
        };

        if (isEdit) {
            put(route('super-admin.gelombang.update', gelombang.id), opsi);
        } else {
            post(route('super-admin.gelombang.store'), opsi);
        }
    };

    const kembali = route('super-admin.gelombang.index');
    const galat = errors as unknown as Record<string, string>;

    const ubahKebijakan = (id: number, kolom: 'kuota' | 'minimal_bayar', nilai: string) =>
        setData('kebijakan', { ...data.kebijakan, [String(id)]: { ...data.kebijakan[String(id)], [kolom]: nilai } });

    const ubahTarif = (kategoriId: number, komponenId: number, nilai: string) =>
        setData('tarif', {
            ...data.tarif,
            [String(kategoriId)]: { ...data.tarif[String(kategoriId)], [String(komponenId)]: nilai },
        });

    // Bahaya khas tab: galat validasi jatuh di jalur yang sedang tidak terlihat,
    // jadi formulir gagal disimpan tanpa satu pun pesan di layar. Jalur yang
    // bergalat ditandai di tab-nya, dan yang pertama langsung dibuka.
    const jalurBergalat = useMemo(
        () =>
            (kebijakan ?? [])
                .filter((b) =>
                    Object.keys(galat).some((kunci) =>
                        [`kebijakan.${b.kategori_siswa_id}.`, `tarif.${b.kategori_siswa_id}.`, `dokumen.${b.kategori_siswa_id}.`].some((awalan) =>
                            kunci.startsWith(awalan),
                        ),
                    ),
                )
                .map((b) => b.kategori_siswa_id),
        [kebijakan, galat],
    );

    useEffect(() => {
        if (jalurBergalat.length > 0 && !jalurBergalat.includes(jalurAktif)) {
            setJalurAktif(jalurBergalat[0]);
        }
    }, [jalurBergalat, jalurAktif]);

    useEffect(() => {
        if (bacaSaja) {
            return;
        }

        const cegahTutupBrowser = (event: BeforeUnloadEvent) => {
            if (!isDirty || sedangMenyimpan.current) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };
        const lepasPengamanInertia = router.on('before', (event) => {
            const kunjungan = event.detail.visit;

            // Prefetch cuma mengambil halaman di belakang layar dan tidak membuat
            // admin meninggalkan formulir, jadi tidak perlu ditahan.
            if (kunjungan.prefetch || !isDirty || sedangMenyimpan.current) {
                return;
            }

            if (lewatiPengamanSekali.current) {
                lewatiPengamanSekali.current = false;

                return;
            }

            setKunjunganTertunda(kunjungan);

            return false;
        });

        window.addEventListener('beforeunload', cegahTutupBrowser);

        return () => {
            lepasPengamanInertia();
            window.removeEventListener('beforeunload', cegahTutupBrowser);
        };
    }, [bacaSaja, isDirty]);

    const tinggalkanHalaman = () => {
        if (!kunjunganTertunda) {
            return;
        }

        const kunjungan = kunjunganTertunda;
        const opsi: VisitOptions = {
            method: kunjungan.method,
            data: kunjungan.data,
            replace: kunjungan.replace,
            preserveScroll: kunjungan.preserveScroll,
            preserveState: kunjungan.preserveState,
            only: kunjungan.only,
            except: kunjungan.except,
            headers: kunjungan.headers,
            errorBag: kunjungan.errorBag,
            forceFormData: kunjungan.forceFormData,
            queryStringArrayFormat: kunjungan.queryStringArrayFormat,
            async: kunjungan.async,
            showProgress: kunjungan.showProgress,
            fresh: kunjungan.fresh,
            reset: kunjungan.reset,
            preserveUrl: kunjungan.preserveUrl,
        };

        setKunjunganTertunda(null);
        lewatiPengamanSekali.current = true;
        router.visit(kunjungan.url, opsi);
    };

    return (
        <AppLayout>
            <Head title={bacaSaja ? `Detail ${gelombang?.nama}` : isEdit ? `Ubah ${gelombang.nama}` : 'Tambah Gelombang'} />
            <PageHeader
                title={bacaSaja ? `Detail ${gelombang?.nama}` : isEdit ? `Ubah ${gelombang.nama}` : 'Tambah Gelombang'}
                subtitle={
                    bacaSaja
                        ? 'Ketentuan yang berlaku di gelombang ini - tidak bisa diubah lagi'
                        : 'Jadwal, lalu kuota, minimal bayar, nominal, dan berkas tiap jalur'
                }
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

                <form onSubmit={submit}>
                    <div className="space-y-6">
                        {bacaSaja && (
                            <Kartu judul="Hanya Bisa Dibaca">
                                <p className="flex items-start gap-2 text-sm text-gray-600">
                                    <Lock size={15} strokeWidth={2} className="mt-0.5 shrink-0 text-[#1F509A]" />
                                    <span>{terkunci}</span>
                                </p>
                            </Kartu>
                        )}

                        <Kartu judul="Jadwal Pendaftaran">
                            <div className="mb-5 grid gap-5 sm:grid-cols-3">
                                <div>
                                    <Label required htmlFor="tahun_ajaran_id">
                                        Tahun Ajaran
                                    </Label>
                                    <select
                                        id="tahun_ajaran_id"
                                        value={data.tahun_ajaran_id}
                                        onChange={(e) => setData('tahun_ajaran_id', e.target.value)}
                                        disabled={bacaSaja || (isEdit && !gelombang.bisa_pindah_tahun_ajaran)}
                                        title={
                                            isEdit && !gelombang.bisa_pindah_tahun_ajaran
                                                ? 'Tahun ajaran terkunci karena gelombang ini sudah memiliki pendaftar.'
                                                : undefined
                                        }
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
                                    <Input
                                        id="nama"
                                        value={data.nama}
                                        onChange={(v) => setData('nama', v)}
                                        placeholder="Gelombang 1"
                                        disabled={bacaSaja}
                                    />
                                    <FieldError message={errors.nama} />
                                </div>
                                <div>
                                    <Label required htmlFor="biaya_pendaftaran">
                                        Biaya Pendaftaran per Anak
                                    </Label>
                                    <Input
                                        id="biaya_pendaftaran"
                                        value={data.biaya_pendaftaran}
                                        onChange={(value) => setData('biaya_pendaftaran', value.replace(/\D/g, ''))}
                                        placeholder="125000"
                                        disabled={bacaSaja}
                                    />
                                    <FieldError message={errors.biaya_pendaftaran} />
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
                                        disabled={bacaSaja}
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
                                        disabled={bacaSaja}
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
                                        disabled={bacaSaja}
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

                        {kebijakan && (
                            <Kartu judul="Ketentuan tiap Jalur">
                                <div
                                    role="tablist"
                                    aria-label="Pilih jalur pendaftaran"
                                    className="mb-5 flex flex-wrap gap-1.5 rounded-2xl bg-[#F5F9FD] p-1.5"
                                >
                                    {kebijakan.map((b) => {
                                        const aktif = b.kategori_siswa_id === jalurAktif;
                                        const bergalat = jalurBergalat.includes(b.kategori_siswa_id);
                                        const belumLengkap = jalurBelumLengkap.includes(b.kategori_siswa_id);

                                        return (
                                            <button
                                                key={b.kategori_siswa_id}
                                                type="button"
                                                role="tab"
                                                onClick={() => setJalurAktif(b.kategori_siswa_id)}
                                                aria-selected={aktif}
                                                className={
                                                    'flex min-w-[8rem] flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-xs transition-all sm:text-sm ' +
                                                    (aktif
                                                        ? 'bg-[#0A3981] font-semibold text-white shadow-sm'
                                                        : 'text-gray-600 hover:bg-white hover:text-[#0A3981]')
                                                }
                                            >
                                                {b.nama}
                                                {(bergalat || belumLengkap) && (
                                                    <span
                                                        aria-label={bergalat ? 'ada isian yang perlu dibetulkan' : 'jalur belum lengkap'}
                                                        title={bergalat ? 'Ada isian yang perlu dibetulkan' : 'Minimal bayar belum diisi'}
                                                        className={`h-1.5 w-1.5 shrink-0 rounded-full ${bergalat ? 'bg-red-600' : 'bg-amber-500'}`}
                                                    />
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>

                                <div role="tabpanel">
                                    {kebijakan
                                        .filter((b) => b.kategori_siswa_id === jalurAktif)
                                        .map((b) => {
                                            const isian = data.kebijakan[String(b.kategori_siswa_id)] ?? { kuota: '', minimal_bayar: '' };
                                            const kuota = isian.kuota === '' ? null : Number(isian.kuota);
                                            const dibawahTerpakai = kuota !== null && kuota < b.terpakai;

                                            return (
                                                <div key={b.kategori_siswa_id}>
                                                    <p className="mb-3 text-xs text-gray-500">
                                                        {b.terpakai} kursi terpakai
                                                        {kuota !== null && ` · sisa ${Math.max(0, kuota - b.terpakai)}`}
                                                    </p>

                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <Label htmlFor={`kuota-${b.kategori_siswa_id}`}>Kuota</Label>
                                                            <input
                                                                id={`kuota-${b.kategori_siswa_id}`}
                                                                type="number"
                                                                min={0}
                                                                value={isian.kuota}
                                                                onChange={(e) => ubahKebijakan(b.kategori_siswa_id, 'kuota', e.target.value)}
                                                                onWheel={(e) => e.currentTarget.blur()}
                                                                placeholder="Tidak dibatasi"
                                                                disabled={bacaSaja}
                                                                className={gayaIsian}
                                                            />
                                                            <FieldError message={galat[`kebijakan.${b.kategori_siswa_id}.kuota`]} />
                                                        </div>
                                                        <div>
                                                            <Label required htmlFor={`minimal-${b.kategori_siswa_id}`}>
                                                                Minimal Bayar (Rp)
                                                            </Label>
                                                            <input
                                                                id={`minimal-${b.kategori_siswa_id}`}
                                                                type="number"
                                                                min={0}
                                                                value={isian.minimal_bayar}
                                                                onChange={(e) => ubahKebijakan(b.kategori_siswa_id, 'minimal_bayar', e.target.value)}
                                                                onWheel={(e) => e.currentTarget.blur()}
                                                                placeholder="Wajib diisi, 0 kalau dibebaskan"
                                                                disabled={bacaSaja}
                                                                className={gayaIsian}
                                                            />
                                                            <FieldError
                                                                message={
                                                                    galat[`kebijakan.${b.kategori_siswa_id}.minimal_bayar`] ??
                                                                    (sudahMencobaSimpan && isian.minimal_bayar === ''
                                                                        ? 'Minimal bayar wajib diisi. Isi 0 kalau jalur ini dibebaskan.'
                                                                        : undefined)
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    {(komponen ?? []).length > 0 && (
                                                        <div className="mt-4">
                                                            <p className="mb-2 text-[13px] font-medium text-gray-600">Nominal tiap komponen (Rp)</p>
                                                            <div className="grid gap-4 sm:grid-cols-2">
                                                                {(komponen ?? []).map((k) => (
                                                                    <div key={k.id}>
                                                                        <Label htmlFor={`tarif-${b.kategori_siswa_id}-${k.id}`}>{k.nama}</Label>
                                                                        <input
                                                                            id={`tarif-${b.kategori_siswa_id}-${k.id}`}
                                                                            type="number"
                                                                            min={0}
                                                                            value={data.tarif[String(b.kategori_siswa_id)]?.[String(k.id)] ?? ''}
                                                                            onChange={(e) => ubahTarif(b.kategori_siswa_id, k.id, e.target.value)}
                                                                            onWheel={(e) => e.currentTarget.blur()}
                                                                            placeholder="Tidak ditagihkan"
                                                                            disabled={bacaSaja}
                                                                            className={gayaIsian}
                                                                        />
                                                                        <FieldError message={galat[`tarif.${b.kategori_siswa_id}.${k.id}`]} />
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}

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
                                                                        disabled={bacaSaja}
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
                                                                Kuota di bawah jumlah terpakai. Yang sudah masuk tetap memegang kursinya — jalur ini
                                                                cuma tidak menerima pendaftar baru lagi.
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
                            {!bacaSaja && (
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                                >
                                    {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Buat Gelombang'}
                                </Button>
                            )}
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                            >
                                <Link href={kembali}>{bacaSaja ? 'Kembali' : 'Batal'}</Link>
                            </Button>
                        </div>
                    </div>
                </form>
            </PageContainer>

            <ConfirmationDialog
                open={kunjunganTertunda !== null}
                title="Perubahan belum disimpan"
                description="Perubahan yang Anda buat pada gelombang ini akan hilang jika halaman ditinggalkan."
                confirmLabel="Tinggalkan halaman"
                cancelLabel="Tetap di sini"
                tone="warning"
                onConfirm={tinggalkanHalaman}
                onCancel={() => setKunjunganTertunda(null)}
            />
        </AppLayout>
    );
}
