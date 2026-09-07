import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';

interface BarisGelombang {
    id: number;
    nama: string;
    tahun_ajaran: string;
    status_buka: boolean;
    total: number;
    diproses: number;
    diterima: number;
    ditolak: number;
    total_tagihan: number;
    sudah_masuk: number;
    sisa_tagihan: number;
}

interface BarisKategori {
    gelombang_id: number;
    gelombang: string;
    tahun_ajaran: string;
    kategori: string;
    total: number;
    diterima: number;
    ditolak: number;
    kuota: number;
    sisa: number;
    penuh: boolean;
}

interface RekapitulasiProps {
    perGelombang: BarisGelombang[];
    perKategori: BarisKategori[];
    tahunAjaran: string[];
    /** Tahun ajaran aktif - cuma posisi awal, tahun lain tetap bisa dipilih. */
    filterAwal: string;
}

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

function Kartu({ judul, keterangan, children }: { judul: string; keterangan?: string; children: React.ReactNode }) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <div className="px-6 pt-6">
                <h2 className="text-[15px] font-semibold text-gray-900">{judul}</h2>
                {keterangan && <p className="mt-1 text-sm text-gray-500">{keterangan}</p>}
            </div>
            {children}
        </div>
    );
}

/** Kepala kolom tabel: 11px uppercase, satu-satunya pengecualian ukuran teks
 *  terkecil di aplikasi ini. */
function Th({ children, angka = false }: { children: React.ReactNode; angka?: boolean }) {
    return <th className={`px-4 py-3 text-xs font-bold tracking-wide text-white uppercase ${angka ? 'text-right' : 'text-left'}`}>{children}</th>;
}

function Td({ children, angka = false, tebal = false }: { children: React.ReactNode; angka?: boolean; tebal?: boolean }) {
    return (
        <td className={`px-4 py-3 text-sm ${angka ? 'text-right tabular-nums' : ''} ${tebal ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>
            {children}
        </td>
    );
}

// Disamakan dengan penyaring di halaman staf supaya satu bahasa visual.
const gayaSelect =
    'h-10 w-full appearance-none rounded-md border border-gray-200 bg-white pr-9 pl-3 text-sm text-gray-900 shadow-sm transition-colors focus:border-[#1F509A] focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

export default function Rekapitulasi({ perGelombang, perKategori, tahunAjaran, filterAwal }: RekapitulasiProps) {
    // Dibuka pada tahun ajaran aktif. Cuma posisi awal - "Semua tahun ajaran"
    // tetap tersedia, dan justru itu gunanya halaman laporan: membandingkan
    // angkatan tahun ini dengan tahun sebelumnya.
    const [tahun, setTahun] = useState(filterAwal);

    const gelombang = useMemo(() => perGelombang.filter((g) => !tahun || g.tahun_ajaran === tahun), [perGelombang, tahun]);
    const kategori = useMemo(() => perKategori.filter((k) => !tahun || k.tahun_ajaran === tahun), [perKategori, tahun]);

    // Baris total dihitung dari baris yang sedang tampil, supaya tidak pernah
    // bertentangan dengan isi tabel di atasnya.
    const total = useMemo(
        () =>
            gelombang.reduce(
                (a, g) => ({
                    total: a.total + g.total,
                    diproses: a.diproses + g.diproses,
                    diterima: a.diterima + g.diterima,
                    ditolak: a.ditolak + g.ditolak,
                    total_tagihan: a.total_tagihan + g.total_tagihan,
                    sudah_masuk: a.sudah_masuk + g.sudah_masuk,
                    sisa_tagihan: a.sisa_tagihan + g.sisa_tagihan,
                }),
                { total: 0, diproses: 0, diterima: 0, ditolak: 0, total_tagihan: 0, sudah_masuk: 0, sisa_tagihan: 0 },
            ),
        [gelombang],
    );

    return (
        <AppLayout>
            <Head title="Rekapitulasi PPDB" />
            <PageHeader title="Rekapitulasi PPDB" subtitle="Rekap pendaftaran dan penerimaan per gelombang dan per kategori" wide />

            <PageContainer wide>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative w-56">
                            <select
                                className={gayaSelect}
                                value={tahun}
                                onChange={(e) => setTahun(e.target.value)}
                                aria-label="Saring menurut tahun ajaran"
                            >
                                <option value="">Semua tahun ajaran</option>
                                {tahunAjaran.map((t) => (
                                    <option key={t} value={t}>
                                        {t}
                                    </option>
                                ))}
                            </select>
                            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                        </div>

                        {tahun !== '' && <span className="text-xs text-gray-500">Menampilkan tahun ajaran {tahun} saja.</span>}
                    </div>

                    <Kartu judul="Rekap per Gelombang" keterangan="Pendaftaran yang masih draft tidak ikut dihitung.">
                        {gelombang.length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">
                                Belum ada gelombang pada tahun ajaran ini. Pilih tahun ajaran lain untuk melihat angkatan sebelumnya.
                            </p>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full min-w-[860px]">
                                    <thead>
                                        <tr className="bg-[#0A3981]">
                                            <Th>Gelombang</Th>
                                            <Th angka>Pendaftar</Th>
                                            <Th angka>Diproses</Th>
                                            <Th angka>Diterima</Th>
                                            <Th angka>Ditolak</Th>
                                            <Th angka>Tagihan</Th>
                                            <Th angka>Sudah Masuk</Th>
                                            <Th angka>Sisa</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {gelombang.map((g) => (
                                            <tr key={g.id} className="border-b border-gray-100 last:border-b-0 hover:bg-[#F5F9FD]/50">
                                                <Td>
                                                    <span className="font-medium text-gray-900">{g.nama}</span>
                                                    <span className="text-gray-500"> · {g.tahun_ajaran}</span>
                                                    {g.status_buka && (
                                                        <span className="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                                                            Dibuka
                                                        </span>
                                                    )}
                                                </Td>
                                                <Td angka tebal>
                                                    {g.total}
                                                </Td>
                                                <Td angka>{g.diproses}</Td>
                                                <Td angka>{g.diterima}</Td>
                                                <Td angka>{g.ditolak}</Td>
                                                <Td angka>{formatRupiah(g.total_tagihan)}</Td>
                                                <Td angka>{formatRupiah(g.sudah_masuk)}</Td>
                                                <Td angka>{formatRupiah(g.sisa_tagihan)}</Td>
                                            </tr>
                                        ))}
                                        <tr className="bg-[#F5F9FD]">
                                            <Td tebal>Total</Td>
                                            <Td angka tebal>
                                                {total.total}
                                            </Td>
                                            <Td angka tebal>
                                                {total.diproses}
                                            </Td>
                                            <Td angka tebal>
                                                {total.diterima}
                                            </Td>
                                            <Td angka tebal>
                                                {total.ditolak}
                                            </Td>
                                            <Td angka tebal>
                                                {formatRupiah(total.total_tagihan)}
                                            </Td>
                                            <Td angka tebal>
                                                {formatRupiah(total.sudah_masuk)}
                                            </Td>
                                            <Td angka tebal>
                                                {formatRupiah(total.sisa_tagihan)}
                                            </Td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Kartu>

                    <Kartu judul="Rekap per Kategori" keterangan="Pendaftaran yang masih draft tidak ikut dihitung.">
                        {kategori.length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">Kuota per kategori belum ditetapkan untuk tahun ajaran ini.</p>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full min-w-[720px]">
                                    <thead>
                                        <tr className="bg-[#0A3981]">
                                            <Th>Gelombang</Th>
                                            <Th>Kategori</Th>
                                            <Th angka>Pendaftar</Th>
                                            <Th angka>Diterima</Th>
                                            <Th angka>Ditolak</Th>
                                            <Th angka>Kuota</Th>
                                            <Th angka>Sisa</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {kategori.map((k) => (
                                            <tr
                                                key={`${k.gelombang_id}-${k.kategori}`}
                                                className="border-b border-gray-100 last:border-b-0 hover:bg-[#F5F9FD]/50"
                                            >
                                                <Td>
                                                    <span className="text-gray-700">{k.gelombang}</span>
                                                    <span className="text-gray-500"> · {k.tahun_ajaran}</span>
                                                </Td>
                                                <Td tebal>{k.kategori}</Td>
                                                <Td angka>{k.total}</Td>
                                                <Td angka>{k.diterima}</Td>
                                                {/* Kolom ini yang menerangkan kenapa Sisa tidak sama
                                                    dengan Kuota - Pendaftar: kursi dipegang sejak
                                                    formulir dikirim, dan cuma Ditolak yang melepasnya. */}
                                                <Td angka>{k.ditolak}</Td>
                                                <Td angka>{k.kuota}</Td>
                                                <Td angka tebal>
                                                    {k.penuh ? <span className="text-red-700">Penuh</span> : k.sisa}
                                                </Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Kartu>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
