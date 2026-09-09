import { Kartu } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarPlus, ChevronDown, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';

interface GelombangItem {
    id: number;
    nama: string;
    tahun_ajaran: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    batas_waktu_pembayaran: string | null;
    minimal_pembayaran: number | null;
    status_buka: boolean;
    jumlah_pendaftaran: number;
    /** 0 = tagihan gelombang ini tidak akan terbit sama sekali. */
    tarif_terisi: number;
}

interface GelombangProps {
    gelombang: GelombangItem[];
    tahunAjaran: string[];
    filterAwal: string;
    adaTahunAjaran: boolean;
}

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

function KartuGelombang({ gelombang }: { gelombang: GelombangItem }) {
    const [memproses, setMemproses] = useState(false);

    return (
        <div className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h3 className="text-[15px] font-semibold text-gray-900">{gelombang.nama}</h3>
                <span className="text-xs text-gray-500">{gelombang.tahun_ajaran}</span>
                {gelombang.status_buka ? (
                    <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Dibuka</span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Tertutup</span>
                )}
                <span className="text-xs text-gray-500">{gelombang.jumlah_pendaftaran} pendaftaran</span>
            </div>

            {/* Keadaan yang harus kelihatan dari daftar, bukan ditemukan setelah
                ada wali yang mengeluh tagihannya kosong. */}
            {gelombang.tarif_terisi === 0 && (
                <p className="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                    <TriangleAlert size={14} strokeWidth={2} className="mt-0.5 shrink-0" />
                    <span>
                        Belum ada nominal komponen sama sekali di gelombang ini, jadi tagihan pendaftarnya tidak akan terbit. Isi lewat Jalur
                        Pendaftaran.
                    </span>
                </p>
            )}

            <div className="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                <Baris label="Pendaftaran dibuka" nilai={gelombang.tanggal_mulai} />
                <Baris label="Pendaftaran ditutup" nilai={gelombang.tanggal_selesai} />
                {/* Disebut "jatuh tempo" HANYA di sini. Batas pelunasan cicilan
                    milik tahun ajaran tidak boleh memakai kata yang sama — cuma
                    tanggal ini yang bisa menggugurkan pendaftaran. */}
                <Baris label="Jatuh tempo minimal bayar" nilai={gelombang.batas_waktu_pembayaran ?? 'Belum diatur'} />
                <Baris
                    label="Minimal bayar bawaan"
                    nilai={gelombang.minimal_pembayaran !== null ? formatRupiah(gelombang.minimal_pembayaran) : 'Belum diatur'}
                />
            </div>

            <div className="mt-5 flex flex-wrap gap-2">
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                >
                    <Link href={route('super-admin.gelombang.edit', gelombang.id)}>Ubah</Link>
                </Button>

                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={memproses}
                    onClick={() => {
                        setMemproses(true);
                        router.post(
                            route('super-admin.gelombang.status', gelombang.id),
                            // Dibaca dari prop saat tombol ditekan, bukan dibekukan
                            // waktu komponen pertama dirender — halaman tidak
                            // dipasang ulang sesudah aksinya berhasil.
                            { status_buka: !gelombang.status_buka },
                            { preserveScroll: true, onFinish: () => setMemproses(false) },
                        );
                    }}
                    className={
                        'rounded-xl bg-white font-semibold ' +
                        (gelombang.status_buka
                            ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            : 'border-[#E38E49] text-[#E38E49] hover:bg-[#E38E49]/10 hover:text-[#E38E49]')
                    }
                >
                    {memproses ? 'Memproses...' : gelombang.status_buka ? 'Tutup Pendaftaran' : 'Buka Pendaftaran'}
                </Button>
            </div>
        </div>
    );
}

function Baris({ label, nilai }: { label: string; nilai: string }) {
    return (
        <div className="border-b border-gray-100 py-2 last:border-b-0">
            <p className="text-xs text-gray-500">{label}</p>
            <p className="text-sm text-gray-900">{nilai}</p>
        </div>
    );
}

export default function GelombangIndex({ gelombang, tahunAjaran, filterAwal, adaTahunAjaran }: GelombangProps) {
    const [saring, setSaring] = useState(filterAwal);

    const tersaring = useMemo(() => gelombang.filter((g) => !saring || g.tahun_ajaran === saring), [gelombang, saring]);

    return (
        <AppLayout>
            <Head title="Gelombang PPDB" />
            <PageHeader title="Gelombang PPDB" subtitle="Jendela pendaftaran beserta kuota dan minimal bayar tiap jalur" wide />

            <PageContainer wide>
                <div className="mb-5 flex flex-wrap items-center gap-3">
                    <div className="relative w-56">
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

                    {adaTahunAjaran && (
                        <Button asChild className="ml-auto rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                            <Link href={route('super-admin.gelombang.create')}>
                                <CalendarPlus size={16} strokeWidth={2} />
                                Tambah Gelombang
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {!adaTahunAjaran ? (
                            <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                                <p className="mx-auto max-w-md text-sm text-gray-500">
                                    Belum ada tahun ajaran, jadi gelombang belum bisa dibuat — gelombang selalu milik satu tahun ajaran.
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-4 rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                >
                                    <Link href={route('super-admin.tahun-ajaran.index')}>Buka Tahun Ajaran</Link>
                                </Button>
                            </div>
                        ) : tersaring.length === 0 ? (
                            <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                                <p className="mx-auto max-w-md text-sm text-gray-500">
                                    {saring
                                        ? `Belum ada gelombang di tahun ajaran ${saring}. Pilih "Semua tahun ajaran" untuk melihat sisanya.`
                                        : 'Belum ada gelombang sama sekali. Selama belum ada yang dibuka, wali murid tidak bisa mendaftar.'}
                                </p>
                            </div>
                        ) : (
                            tersaring.map((g) => <KartuGelombang key={g.id} gelombang={g} />)
                        )}
                    </div>

                    <div className="space-y-6">
                        <Kartu judul="Hanya Satu yang Terbuka">
                            <p className="text-sm text-gray-500">
                                Membuka sebuah gelombang otomatis menutup yang lain. Pendaftar baru selalu masuk ke gelombang yang terbuka.
                            </p>
                            <p className="mt-3 text-sm text-gray-500">
                                Menutup gelombang <b className="text-gray-700">tidak menyentuh</b> pendaftaran yang sudah masuk — tenggat mereka tetap
                                milik gelombangnya sendiri.
                            </p>
                        </Kartu>

                        <Kartu judul="Urutan Pengisian">
                            <p className="text-sm text-gray-500">
                                Gelombang baru lahir <b className="text-gray-700">tertutup</b>. Sesudah dibuat, sesuaikan nominal per jalur di Jalur
                                Pendaftaran, baru buka pendaftarannya.
                            </p>
                        </Kartu>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
