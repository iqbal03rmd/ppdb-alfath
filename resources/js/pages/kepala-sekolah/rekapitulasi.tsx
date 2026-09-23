import Donut from '@/components/donut';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { ChevronDown, Minus, Plus } from 'lucide-react';
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

interface BarisPeringkat {
    label: string;
    jumlah: number;
    /** true = baris lipatan ("Lainnya", "Tidak menjawab"), bukan satu kelompok
     *  nyata yang bisa ditindaklanjuti. Diperlakukan beda saat digambar. */
    agregat: boolean;
}

interface Temuan {
    asalPaud: BarisPeringkat[];
    /** Bentuk donat, bukan peringkat: 'tidakMenjawab' sengaja di luar irisan
     *  supaya persentasenya dihitung dari yang benar-benar menjawab. */
    sumberInformasi: { irisan: BarisPeringkat[]; menjawab: number; tidakMenjawab: number };
    jedaBayar: {
        terukur: number;
        rataHari: number | null;
        terlamaHari: number | null;
        belumTransfer: number;
        sebaran: BarisPeringkat[];
    };
    polaCicilan: { sekaligus: number; dicicil: number };
}

interface RekapitulasiProps {
    perGelombang: BarisGelombang[];
    perKategori: BarisKategori[];
    /** Sudah dihitung per tahun ajaran di server, kunci '' = semua tahun.
     *  Penyaring di layar tinggal memilih, tidak menjumlah ulang apa pun. */
    temuan: Record<string, Temuan>;
    tahunAjaran: string[];
    /** Tahun ajaran aktif - cuma posisi awal, tahun lain tetap bisa dipilih. */
    filterAwal: string;
}

const TEMUAN_KOSONG: Temuan = {
    asalPaud: [],
    sumberInformasi: { irisan: [], menjawab: 0, tidakMenjawab: 0 },
    jedaBayar: { terukur: 0, rataHari: null, terlamaHari: null, belumTransfer: 0, sebaran: [] },
    polaCicilan: { sekaligus: 0, dicicil: 0 },
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

/** Pemisah desimal Indonesia memakai KOMA. Tanpa ini, "6.6 hari" terbaca aneh
 *  di halaman yang seluruh angka uangnya sudah berformat "Rp 125.775.000". */
function formatAngka(nilai: number) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(nilai);
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

/**
 * Peringkat batang mendatar, satu warna.
 *
 * Satu deret data tidak perlu legenda - judul kartunya sudah menyebutkan apa
 * yang dihitung, dan warna di sini tidak membedakan apa pun. Tiap batang diberi
 * angkanya langsung supaya nilainya tidak perlu ditaksir dari panjang batang,
 * dan itu sekaligus yang membuat diagram ini tetap terbaca kalau dicetak
 * hitam-putih.
 *
 * Panjangnya relatif terhadap batang TERPANJANG, bukan terhadap total. Yang
 * ditanyakan "siapa yang terbanyak", bukan "berapa bagiannya dari keseluruhan".
 */
function Peringkat({ baris, kalimatKosong }: { baris: BarisPeringkat[]; kalimatKosong: string }) {
    if (baris.length === 0) {
        return <p className="px-6 py-8 text-center text-sm text-gray-500">{kalimatKosong}</p>;
    }

    // Skala diambil dari kelompok NYATA saja. Baris lipatan sering menang telak
    // melawan juara sebenarnya - "Lainnya (8 kelompok)" bernilai 10 sementara
    // sekolah terbanyak cuma 6 - dan kalau ikut menentukan skala, seluruh batang
    // yang berarti jadi pendek dan mata tertarik ke kelompok yang justru tidak
    // bisa ditindaklanjuti.
    const nyata = baris.filter((b) => !b.agregat);
    const tertinggi = Math.max(...(nyata.length ? nyata : baris).map((b) => b.jumlah), 1);

    const lipatan = baris.filter((b) => b.agregat);

    return (
        <div className="px-6 pt-4 pb-6">
            <ul className="space-y-2.5">
                {nyata.map((b) => (
                    <li key={b.label} title={`${b.label}: ${b.jumlah} pendaftar`}>
                        <div className="mb-1 flex items-baseline justify-between gap-3">
                            <span className="min-w-0 truncate text-sm text-gray-700">{b.label}</span>
                            <span className="shrink-0 text-sm font-semibold text-gray-900 tabular-nums">{b.jumlah}</span>
                        </div>
                        <div className="h-2 w-full rounded-full bg-[#F5F9FD]">
                            <div className="h-2 rounded-full bg-[#1F509A]" style={{ width: `${Math.max((b.jumlah / tertinggi) * 100, 2)}%` }} />
                        </div>
                    </li>
                ))}
            </ul>

            {/* Baris lipatan ditulis sebagai catatan, TANPA batang.
                Sempat digambar berbatang abu, dan itu masih keliru: ekornya
                sering lebih besar daripada juaranya (13 kelompok berisi 20 anak
                melawan sekolah teratas yang cuma 6), jadi batangnya selalu jadi
                yang terpanjang dan mengundang perbandingan yang justru tidak ada
                artinya - "Lainnya" bukan satu sekolah yang bisa didatangi. */}
            {lipatan.length > 0 && (
                <p className="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
                    {lipatan.map((b) => `${b.label}: ${b.jumlah} pendaftar`).join(' · ')}
                </p>
            )}
        </div>
    );
}

/**
 * Sebaran jeda pembayaran, digambar TEGAK.
 *
 * Bentuknya beda dari peringkat batang di sebelahnya karena datanya beda
 * jenisnya: ini sebaran atas ember yang BERURUTAN (0-3 → lebih dari 14 hari),
 * bukan peringkat yang boleh diurutkan ulang. Sumbu mendatar yang berjalan dari
 * cepat ke lambat itulah yang menyampaikan "kebanyakan orang membayar di awal",
 * dan itu hilang kalau embernya ditumpuk ke bawah seperti daftar.
 *
 * Satu warna untuk semua kolom: yang mengukur jumlah adalah TINGGI kolom, jadi
 * warna tidak mengkodekan apa pun dan tidak perlu dibeda-bedakan.
 */
function Histogram({ ember, kalimatKosong }: { ember: BarisPeringkat[]; kalimatKosong: string }) {
    const total = ember.reduce((n, e) => n + e.jumlah, 0);

    if (total === 0) {
        return <p className="px-6 py-8 text-center text-sm text-gray-500">{kalimatKosong}</p>;
    }

    const tertinggi = Math.max(...ember.map((e) => e.jumlah), 1);

    return (
        <div className="px-6 pt-2 pb-6">
            <div className="flex items-end gap-2" style={{ height: 108 }}>
                {ember.map((e) => (
                    <div key={e.label} className="flex flex-1 flex-col items-center justify-end gap-1" title={`${e.label}: ${e.jumlah} pendaftar`}>
                        <span className="text-sm font-semibold text-gray-900 tabular-nums">{e.jumlah}</span>
                        {/* Tinggi minimal 4px supaya ember yang bernilai nol tetap
                            punya jejak - kolom yang benar-benar hilang terbaca
                            seperti embernya tidak ada, padahal ada dan isinya nol. */}
                        <div className="w-full rounded-t-md bg-[#1F509A]" style={{ height: Math.max((e.jumlah / tertinggi) * 76, 4) }} />
                    </div>
                ))}
            </div>
            <div className="mt-2 flex gap-2 border-t border-gray-100 pt-2">
                {ember.map((e) => (
                    <span key={e.label} className="flex-1 text-center text-[11px] leading-tight text-gray-500">
                        {e.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

/** Empat warna yang LOLOS uji keterbedaan buta warna dalam palet proyek ini
 *  (ΔE terburuk 11,7 protan). Jumlah irisan donat dibatasi empat karena ini -
 *  bukan karena selera. Irisan kelima berarti mengarang warna yang belum diuji. */
const WARNA_SALURAN = ['#1F509A', '#0891B2', '#15803D', '#E38E49'];

/** Abu untuk irisan/baris lipatan: warnanya sendiri yang memberitahu bahwa dia
 *  bukan satu kelompok nyata yang bisa ditindaklanjuti. */
const WARNA_AGREGAT = '#9CA3AF';

// Disamakan dengan penyaring di halaman staf supaya satu bahasa visual.
const gayaSelect =
    'h-10 w-full appearance-none rounded-md border border-gray-200 bg-white pr-9 pl-3 text-sm text-gray-900 shadow-sm transition-colors focus:border-[#1F509A] focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

/**
 * Batang bertumpuk per jalur: berapa yang sudah mencapai minimal bayar, berapa
 * yang belum. Dua deret, jadi legendanya wajib ada - dan tiap potongan diberi
 * angkanya sendiri supaya identitasnya tidak bergantung pada warna saja.
 *
 * Warnanya sengaja warna KEADAAN (hijau tercapai, kuning belum), bukan dua warna
 * kategori. Yang dibandingkan memang baik-buruk, bukan dua hal setara.
 */
export default function Rekapitulasi({ perGelombang, perKategori, temuan, tahunAjaran, filterAwal }: RekapitulasiProps) {
    // Dibuka pada tahun ajaran aktif. Cuma posisi awal - "Semua tahun ajaran"
    // tetap tersedia, dan justru itu gunanya halaman laporan: membandingkan
    // angkatan tahun ini dengan tahun sebelumnya.
    const gelombangKategoriAwal =
        perGelombang.find((g) => (!filterAwal || g.tahun_ajaran === filterAwal) && g.status_buka) ??
        perGelombang.find((g) => !filterAwal || g.tahun_ajaran === filterAwal);

    const [tahun, setTahun] = useState(filterAwal);
    const [gelombangKategori, setGelombangKategori] = useState(gelombangKategoriAwal ? String(gelombangKategoriAwal.id) : 'semua');
    const [gelombangTerbuka, setGelombangTerbuka] = useState<number | null>(null);
    const [kategoriTerbuka, setKategoriTerbuka] = useState<string | null>(null);

    const gelombang = useMemo(() => perGelombang.filter((g) => !tahun || g.tahun_ajaran === tahun), [perGelombang, tahun]);
    const kategoriTahun = useMemo(() => perKategori.filter((k) => !tahun || k.tahun_ajaran === tahun), [perKategori, tahun]);
    const kategori = useMemo(
        () => kategoriTahun.filter((k) => gelombangKategori === 'semua' || String(k.gelombang_id) === gelombangKategori),
        [kategoriTahun, gelombangKategori],
    );

    // Cuma memilih, tidak menghitung - seluruh agregasinya sudah selesai di
    // server. Tahun ajaran yang belum punya pendaftaran tidak punya kunci di
    // sini, jadi jatuh ke bentuk kosong.
    const t = temuan[tahun] ?? TEMUAN_KOSONG;

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
                                onChange={(e) => {
                                    const tahunBaru = e.target.value;
                                    const gelombangTahunBaru = perGelombang.filter((g) => !tahunBaru || g.tahun_ajaran === tahunBaru);
                                    const pilihanAwal = gelombangTahunBaru.find((g) => g.status_buka) ?? gelombangTahunBaru[0];

                                    setTahun(tahunBaru);
                                    setGelombangKategori(pilihanAwal ? String(pilihanAwal.id) : 'semua');
                                    setGelombangTerbuka(null);
                                    setKategoriTerbuka(null);
                                }}
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
                            <>
                                {/* Mobile: satu gelombang diringkas menjadi satu
                                    baris. Angka status dan keuangan baru muncul
                                    saat baris dibuka, jadi tidak perlu menggeser
                                    tabel lebar ke kanan dan kiri. */}
                                <div className="mt-4 overflow-hidden md:hidden">
                                    <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                                        <span aria-hidden />
                                        <span>Gelombang</span>
                                        <span className="text-right">Pendaftar</span>
                                    </div>

                                    <div className="divide-y divide-gray-100">
                                        {gelombang.map((g) => {
                                            const terbuka = gelombangTerbuka === g.id;

                                            return (
                                                <div key={g.id} className={terbuka ? 'bg-[#F8FBFE]' : 'bg-white'}>
                                                    <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-3 px-4 py-4">
                                                        <button
                                                            type="button"
                                                            onClick={() => setGelombangTerbuka(terbuka ? null : g.id)}
                                                            aria-expanded={terbuka}
                                                            aria-label={`${terbuka ? 'Tutup' : 'Buka'} detail ${g.nama} ${g.tahun_ajaran}`}
                                                            className={`mt-0.5 flex h-7 w-7 items-center justify-center rounded-full transition-colors ${
                                                                terbuka ? 'bg-[#0A3981] text-white' : 'bg-[#E8EEF7] text-[#1F509A] hover:bg-[#D4EBF8]'
                                                            }`}
                                                        >
                                                            {terbuka ? (
                                                                <Minus className="h-4 w-4" aria-hidden="true" />
                                                            ) : (
                                                                <Plus className="h-4 w-4" aria-hidden="true" />
                                                            )}
                                                        </button>

                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-semibold text-gray-900">{g.nama}</p>
                                                            <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-gray-500">
                                                                <span>{g.tahun_ajaran}</span>
                                                                {g.status_buka && (
                                                                    <span className="rounded-full bg-green-100 px-2 py-0.5 font-semibold text-green-700">
                                                                        Dibuka
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>

                                                        <span className="text-sm font-semibold text-gray-900 tabular-nums">{g.total}</span>
                                                    </div>

                                                    {terbuka && (
                                                        <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4 pl-[3.75rem]">
                                                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                                                <div>
                                                                    <dt className="text-xs text-gray-500">Diproses</dt>
                                                                    <dd className="mt-0.5 font-semibold text-gray-900 tabular-nums">{g.diproses}</dd>
                                                                </div>
                                                                <div>
                                                                    <dt className="text-xs text-gray-500">Diterima</dt>
                                                                    <dd className="mt-0.5 font-semibold text-gray-900 tabular-nums">{g.diterima}</dd>
                                                                </div>
                                                                <div>
                                                                    <dt className="text-xs text-gray-500">Ditolak</dt>
                                                                    <dd className="mt-0.5 font-semibold text-gray-900 tabular-nums">{g.ditolak}</dd>
                                                                </div>
                                                                <div>
                                                                    <dt className="text-xs text-gray-500">Total Tagihan</dt>
                                                                    <dd className="mt-0.5 font-semibold text-gray-900">
                                                                        {formatRupiah(g.total_tagihan)}
                                                                    </dd>
                                                                </div>
                                                                <div>
                                                                    <dt className="text-xs text-gray-500">Sudah Masuk</dt>
                                                                    <dd className="mt-0.5 font-semibold text-green-700">
                                                                        {formatRupiah(g.sudah_masuk)}
                                                                    </dd>
                                                                </div>
                                                                <div>
                                                                    <dt className="text-xs text-gray-500">Sisa Tagihan</dt>
                                                                    <dd className="mt-0.5 font-semibold text-gray-900">
                                                                        {formatRupiah(g.sisa_tagihan)}
                                                                    </dd>
                                                                </div>
                                                            </dl>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Desktop tetap tabel penuh untuk perbandingan
                                    banyak kolom sekaligus. */}
                                <div className="mt-4 hidden overflow-x-auto md:block">
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
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </Kartu>

                    <Kartu judul="Rekap per Kategori" keterangan="Pendaftaran yang masih draft tidak ikut dihitung.">
                        {kategoriTahun.length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">Kuota per kategori belum ditetapkan untuk tahun ajaran ini.</p>
                        ) : (
                            <>
                                <div className="relative mx-6 mt-4 sm:w-56">
                                    <select
                                        className={gayaSelect}
                                        value={gelombangKategori}
                                        onChange={(e) => {
                                            setGelombangKategori(e.target.value);
                                            setKategoriTerbuka(null);
                                        }}
                                        aria-label="Saring rekap kategori menurut gelombang"
                                    >
                                        <option value="semua">Semua gelombang</option>
                                        {gelombang.map((g) => (
                                            <option key={g.id} value={String(g.id)}>
                                                {tahun ? g.nama : `${g.nama} · ${g.tahun_ajaran}`}
                                            </option>
                                        ))}
                                    </select>
                                    <ChevronDown
                                        className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-500"
                                        aria-hidden="true"
                                    />
                                </div>

                                {kategori.length === 0 ? (
                                    <p className="px-6 py-8 text-center text-sm text-gray-500">
                                        Belum ada kategori yang dikonfigurasi pada gelombang ini.
                                    </p>
                                ) : (
                                    <>
                                        <div className="mt-4 overflow-hidden md:hidden">
                                            <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] gap-3 bg-[#0A3981] px-4 py-3 text-[11px] font-bold tracking-wide text-white uppercase">
                                                <span aria-hidden />
                                                <span>Kategori</span>
                                                <span className="text-right">Pendaftar</span>
                                            </div>

                                            <div className="divide-y divide-gray-100">
                                                {kategori.map((k) => {
                                                    const kunci = `${k.gelombang_id}-${k.kategori}`;
                                                    const terbuka = kategoriTerbuka === kunci;

                                                    return (
                                                        <div key={kunci} className={terbuka ? 'bg-[#F8FBFE]' : 'bg-white'}>
                                                            <div className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-3 px-4 py-4">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setKategoriTerbuka(terbuka ? null : kunci)}
                                                                    aria-expanded={terbuka}
                                                                    aria-label={`${terbuka ? 'Tutup' : 'Buka'} detail kategori ${k.kategori}`}
                                                                    className={`mt-0.5 flex h-7 w-7 items-center justify-center rounded-full transition-colors ${
                                                                        terbuka
                                                                            ? 'bg-[#0A3981] text-white'
                                                                            : 'bg-[#E8EEF7] text-[#1F509A] hover:bg-[#D4EBF8]'
                                                                    }`}
                                                                >
                                                                    {terbuka ? (
                                                                        <Minus className="h-4 w-4" aria-hidden="true" />
                                                                    ) : (
                                                                        <Plus className="h-4 w-4" aria-hidden="true" />
                                                                    )}
                                                                </button>

                                                                <div className="min-w-0">
                                                                    <p className="truncate text-sm font-semibold text-gray-900" title={k.kategori}>
                                                                        {k.kategori}
                                                                    </p>
                                                                    <p className="mt-0.5 truncate text-[11px] text-gray-500">
                                                                        {k.gelombang} · {k.tahun_ajaran}
                                                                    </p>
                                                                </div>

                                                                <span className="text-sm font-semibold text-gray-900 tabular-nums">{k.total}</span>
                                                            </div>

                                                            {terbuka && (
                                                                <div className="border-t border-dashed border-[#D4EBF8] px-4 py-4 pl-[3.75rem]">
                                                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                                                        <div>
                                                                            <dt className="text-xs text-gray-500">Diterima</dt>
                                                                            <dd className="mt-0.5 font-semibold text-gray-900 tabular-nums">
                                                                                {k.diterima}
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="text-xs text-gray-500">Ditolak</dt>
                                                                            <dd className="mt-0.5 font-semibold text-gray-900 tabular-nums">
                                                                                {k.ditolak}
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="text-xs text-gray-500">Kuota</dt>
                                                                            <dd className="mt-0.5 font-semibold text-gray-900 tabular-nums">
                                                                                {k.kuota}
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="text-xs text-gray-500">Sisa Kuota</dt>
                                                                            <dd
                                                                                className={`mt-0.5 font-semibold ${k.penuh ? 'text-red-700' : 'text-gray-900'}`}
                                                                            >
                                                                                {k.penuh ? 'Penuh' : k.sisa}
                                                                            </dd>
                                                                        </div>
                                                                    </dl>
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div className="mt-4 hidden overflow-x-auto md:block">
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
                                    </>
                                )}
                            </>
                        )}
                    </Kartu>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Kartu judul="Asal TK/RA/PAUD" keterangan="Sekolah asal yang paling banyak menyumbang pendaftar.">
                            <Peringkat baris={t.asalPaud} kalimatKosong="Belum ada pendaftar yang mengisi asal sekolah pada tahun ajaran ini." />
                        </Kartu>

                        <Kartu judul="Tahu PPDB dari Mana" keterangan="Bagian tiap saluran, dihitung dari pendaftar yang menjawab.">
                            <div className="px-6 pt-4 pb-6">
                                <Donut
                                    irisan={t.sumberInformasi.irisan.map((i, urutan) => ({
                                        label: i.label,
                                        jumlah: i.jumlah,
                                        warna: i.agregat ? WARNA_AGREGAT : (WARNA_SALURAN[urutan] ?? WARNA_AGREGAT),
                                    }))}
                                    kalimatKosong="Belum ada yang menjawab pada tahun ajaran ini."
                                />
                                {t.sumberInformasi.tidakMenjawab > 0 && (
                                    // Di luar donat dengan sengaja: dia bukan saluran
                                    // promosi, jadi kalau ikut jadi irisan, persentase
                                    // tiap saluran mengecil oleh sesuatu yang bukan
                                    // saluran.
                                    <p className="mt-4 text-xs text-gray-500">
                                        {t.sumberInformasi.tidakMenjawab} pendaftar tidak menjawab — isian ini memang opsional, jadi tidak ikut
                                        dihitung di atas.
                                    </p>
                                )}
                            </div>
                        </Kartu>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Kartu
                            judul="Lama Menunggu Transfer Pertama"
                            keterangan="Dihitung sejak berkas dinyatakan lolos sampai transfer pertama masuk."
                        >
                            <div className="px-6 pt-4 pb-2">
                                {t.jedaBayar.rataHari === null ? (
                                    <p className="text-sm text-gray-500">Belum ada transfer yang bisa diukur pada tahun ajaran ini.</p>
                                ) : (
                                    <>
                                        <p className="text-3xl font-bold text-[#0A3981]">{formatAngka(t.jedaBayar.rataHari)} hari</p>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            Rata-rata dari <span className="font-semibold">{t.jedaBayar.terukur}</span> pendaftar. Terlama{' '}
                                            <span className="font-semibold">{t.jedaBayar.terlamaHari}</span> hari.
                                        </p>
                                    </>
                                )}
                                {t.jedaBayar.belumTransfer > 0 && (
                                    // Sengaja tidak ikut ke rata-rata: jedanya belum
                                    // selesai berjalan, jadi angka berapa pun akan
                                    // menggeser rata-ratanya ke arah yang keliru.
                                    <p className="mt-3 rounded-xl bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-700">
                                        <span className="font-semibold text-gray-900">{t.jedaBayar.belumTransfer} pendaftar</span> sudah boleh
                                        membayar tapi belum menyetor sama sekali.
                                    </p>
                                )}
                            </div>
                            <Histogram ember={t.jedaBayar.sebaran} kalimatKosong="Belum ada transfer yang bisa dikelompokkan." />
                        </Kartu>

                        <Kartu judul="Cara Membayar" keterangan="Dari pendaftar yang sudah mencapai minimal bayar.">
                            <div className="px-6 pt-4 pb-6">
                                <Donut
                                    irisan={[
                                        { label: 'Sekali transfer', jumlah: t.polaCicilan.sekaligus, warna: '#15803D' },
                                        { label: 'Dicicil', jumlah: t.polaCicilan.dicicil, warna: '#1F509A' },
                                    ]}
                                    kalimatKosong="Belum ada yang mencapai minimal bayar pada tahun ajaran ini."
                                />
                            </div>
                        </Kartu>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
