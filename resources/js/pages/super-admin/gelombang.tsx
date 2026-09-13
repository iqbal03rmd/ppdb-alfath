import { DataTable } from '@/components/data-table';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { CalendarPlus, ChevronDown, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';

/** Satu kata dari server. Layar tinggal memetakannya, tidak menyimpulkan sendiri. */
type Keadaan = 'menerima' | 'perlu_ditutup' | 'siap' | 'belum_mulai' | 'berakhir' | 'tahun_lampau';

interface GelombangItem {
    id: number;
    nama: string;
    tahun_ajaran: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    batas_waktu_pembayaran: string | null;
    status_buka: boolean;
    keadaan: Keadaan;
    /** null = boleh dibuka. Kalimatnya datang dari server, jangan disusun ulang di sini. */
    alasan_tidak_bisa_dibuka: string | null;
    /** null = ketentuannya masih boleh diubah. Kalimatnya datang dari server. */
    alasan_tidak_bisa_diubah: string | null;
    /** 0 = tagihan gelombang ini tidak akan terbit sama sekali. */
    tarif_terisi: number;
}

interface GelombangProps {
    gelombang: GelombangItem[];
    tahunAjaran: string[];
    filterAwal: string;
    adaTahunAjaran: boolean;
}

/**
 * Nama tiap keadaan. Cuma penamaan — keputusannya sudah diambil server lewat
 * GelombangPpdb::keadaan(), layar tidak menghitung apa pun di sini.
 */
const BADGE: Record<Keadaan, { teks: string; gaya: string }> = {
    menerima: { teks: 'Dibuka', gaya: 'bg-green-100 text-green-700' },
    perlu_ditutup: { teks: 'Perlu ditutup', gaya: 'bg-amber-100 text-amber-800' },
    siap: { teks: 'Siap dibuka', gaya: 'bg-blue-100 text-[#1F509A]' },
    belum_mulai: { teks: 'Belum mulai', gaya: 'bg-gray-100 text-gray-600' },
    berakhir: { teks: 'Berakhir', gaya: 'bg-gray-100 text-gray-600' },
    tahun_lampau: { teks: 'Tahun lampau', gaya: 'bg-gray-100 text-gray-600' },
};

function TombolStatus({ gelombang }: { gelombang: GelombangItem }) {
    const [memproses, setMemproses] = useState(false);

    // Menutup tidak pernah terhalang — itu jalan keluar dari hampir semua
    // keadaan salah di layar ini.
    const terhalang = !gelombang.status_buka && gelombang.alasan_tidak_bisa_dibuka !== null;

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={memproses || terhalang}
            title={terhalang ? (gelombang.alasan_tidak_bisa_dibuka ?? undefined) : undefined}
            onClick={() => {
                setMemproses(true);
                router.post(
                    route('super-admin.gelombang.status', gelombang.id),
                    // Dibaca dari prop saat tombol ditekan, bukan dibekukan waktu
                    // komponen pertama dirender — halaman tidak dipasang ulang
                    // sesudah aksinya berhasil.
                    { status_buka: !gelombang.status_buka },
                    { preserveScroll: true, onFinish: () => setMemproses(false) },
                );
            }}
            className={
                'rounded-xl bg-white font-semibold ' +
                (terhalang
                    ? 'border-gray-200 text-gray-500'
                    : gelombang.status_buka
                      ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                      : 'border-[#E38E49] text-[#E38E49] hover:bg-[#E38E49]/10 hover:text-[#E38E49]')
            }
        >
            {memproses ? 'Memproses...' : gelombang.status_buka ? 'Tutup' : 'Buka'}
        </Button>
    );
}

function buatKolom(): ColumnDef<GelombangItem>[] {
    return [
        {
            accessorKey: 'nama',
            header: 'Gelombang',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium text-gray-900">{row.original.nama}</p>
                    <p className="text-xs text-gray-500">{row.original.tahun_ajaran}</p>
                </div>
            ),
        },
        {
            accessorKey: 'tanggal_mulai',
            header: 'Jadwal',
            cell: ({ row }) => (
                <div className="text-xs text-gray-700">
                    <p>{row.original.tanggal_mulai}</p>
                    <p className="text-gray-500">s/d {row.original.tanggal_selesai}</p>
                </div>
            ),
        },
        {
            // Disebut "jatuh tempo" HANYA di sini. Batas pelunasan cicilan milik
            // tahun ajaran tidak boleh memakai kata yang sama — cuma tanggal ini
            // yang bisa menggugurkan pendaftaran, yang itu tidak berakibat apa-apa.
            accessorKey: 'batas_waktu_pembayaran',
            header: 'Jatuh Tempo',
            cell: ({ row }) =>
                row.original.batas_waktu_pembayaran ? (
                    <span className="text-xs text-gray-700">{row.original.batas_waktu_pembayaran}</span>
                ) : (
                    // Bukan sekadar strip: gelombang tanpa jatuh tempo bikin
                    // walinya tidak pernah melihat tenggat apa pun di halaman
                    // Pembayaran, dan staf kehilangan sandaran tanggal buat
                    // menutup yang tak capai minimal bayar.
                    <span className="text-xs text-amber-800">Belum diatur</span>
                ),
        },
        {
            accessorKey: 'keadaan',
            header: 'Status',
            cell: ({ row }) => {
                const badge = BADGE[row.original.keadaan];

                return (
                    <div className="space-y-1.5">
                        <span className={`inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${badge.gaya}`}>{badge.teks}</span>

                        {/* Keadaan yang harus kelihatan dari daftar, bukan
                            ditemukan setelah ada wali yang mengeluh tagihannya
                            kosong. */}
                        {row.original.tarif_terisi === 0 && (
                            <span className="flex items-center gap-1 text-xs text-amber-800">
                                <TriangleAlert size={12} strokeWidth={2} className="shrink-0" />
                                Nominal kosong
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'aksi',
            header: 'Aksi',
            enableSorting: false,
            cell: ({ row }) => {
                // Satu tombol, dua nama. Kalau ketentuannya terkunci, layarnya
                // tetap dibuka sebagai halaman baca-saja — jadi tombolnya tidak
                // pernah mati, cuma berubah janjinya.
                const bisaDiubah = row.original.alasan_tidak_bisa_diubah === null;

                return (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className={
                                'rounded-xl bg-white font-semibold ' +
                                (bisaDiubah
                                    ? 'border-[#1F509A]/40 text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50')
                            }
                        >
                            <Link href={route('super-admin.gelombang.edit', row.original.id)}>{bisaDiubah ? 'Ubah' : 'Detail'}</Link>
                        </Button>

                        <TombolStatus gelombang={row.original} />
                    </div>
                );
            },
        },
    ];
}

export default function GelombangIndex({ gelombang, tahunAjaran, filterAwal, adaTahunAjaran }: GelombangProps) {
    const [saring, setSaring] = useState(filterAwal);

    const tersaring = useMemo(() => gelombang.filter((g) => !saring || g.tahun_ajaran === saring), [gelombang, saring]);
    const columns = useMemo(() => buatKolom(), []);
    const perluDitutup = tersaring.filter((g) => g.keadaan === 'perlu_ditutup');

    return (
        <AppLayout>
            <Head title="Gelombang PPDB" />
            <PageHeader title="Gelombang PPDB" subtitle="Jendela pendaftaran beserta kuota dan minimal bayar tiap jalur" wide />

            <PageContainer wide>
                {!adaTahunAjaran ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                        <h2 className="text-[15px] font-semibold text-gray-900">Belum ada tahun ajaran</h2>
                        <p className="mx-auto mt-1.5 max-w-md text-sm text-gray-500">
                            Gelombang belum bisa dibuat &mdash; gelombang selalu milik satu tahun ajaran.
                        </p>
                        <Button
                            asChild
                            variant="outline"
                            className="mt-5 rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                        >
                            <Link href={route('super-admin.tahun-ajaran.index')}>Buka Tahun Ajaran</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        {/* Satu-satunya kalimat panjang yang tetap tinggal di
                            daftar: keadaan ini menyesatkan kalau cuma jadi badge
                            — tandanya bilang "Dibuka" padahal tidak ada yang bisa
                            mendaftar, dan Admin perlu tahu tindakannya apa. */}
                        {perluDitutup.length > 0 && (
                            <div className="mb-5 flex items-start gap-2 rounded-2xl bg-amber-50 p-4 text-xs text-amber-800">
                                <TriangleAlert size={15} strokeWidth={2} className="mt-0.5 shrink-0" />
                                <p>
                                    <b>{perluDitutup.map((g) => g.nama).join(', ')}</b> tandanya masih &quot;Dibuka&quot;, tapi jendela
                                    pendaftarannya sudah lewat &mdash; jadi tidak ada yang bisa mendaftar lagi di sana. Tekan <b>Tutup</b> supaya
                                    keadaannya cocok. Ketentuannya sendiri sudah terkunci permanen; kalau sekolah mau menerima pendaftar lagi, buat
                                    gelombang baru.
                                </p>
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            data={tersaring}
                            searchPlaceholder="Cari gelombang..."
                            searchWidth="max-w-xs"
                            emptyMessage={
                                saring
                                    ? `Belum ada gelombang di tahun ajaran ${saring}. Pilih "Semua tahun ajaran" untuk melihat sisanya.`
                                    : 'Belum ada gelombang sama sekali. Selama belum ada yang dibuka, wali murid tidak bisa mendaftar.'
                            }
                            toolbar={
                                <>
                                    <div className="relative w-52">
                                        <select
                                            value={saring}
                                            onChange={(e) => setSaring(e.target.value)}
                                            aria-label="Saring menurut tahun ajaran"
                                            className="h-10 w-full appearance-none rounded-md border border-gray-200 bg-white pr-9 pl-3 text-sm text-gray-900 shadow-sm transition-colors focus:border-[#1F509A] focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
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

                                    <Button asChild className="ml-auto rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                                        <Link href={route('super-admin.gelombang.create')}>
                                            <CalendarPlus size={16} strokeWidth={2} />
                                            Tambah Gelombang
                                        </Link>
                                    </Button>
                                </>
                            }
                        />

                    </>
                )}
            </PageContainer>
        </AppLayout>
    );
}
