import Donut, { type IrisanDonut } from '@/components/donut';
import PageBanner from '@/components/page-banner';
import PageContainer from '@/components/page-container';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { CheckCircle2, GraduationCap, Users, XCircle } from 'lucide-react';

interface KuotaItem {
    nama: string;
    kuota: number;
    terpakai: number;
    sisa: number;
    penuh: boolean;
}

interface DashboardProps {
    ringkasan: { total: number; diterima: number; diproses: number; ditolak: number };
    statistik: { pendaftaran: Record<string, number>; pembayaran: Record<string, number> };
    keuangan: { total_tagihan: number; sudah_masuk: number; menunggu_diperiksa: number; sisa_tagihan: number };
    gelombangBerjalan: { nama: string; tanggal_selesai: string } | null;
    kuota: KuotaItem[];
}

/**
 * Warna diagram - sama persis dengan Beranda Staf supaya satu status berwarna
 * sama di seluruh aplikasi. Lima warna berhue-nya sudah lolos uji keterbedaan,
 * termasuk untuk mata yang tidak bisa membedakan merah-hijau; abu-abu dipakai
 * untuk keadaan "belum jadi apa-apa", bukan sebagai warna kategori.
 */
const WARNA_STATUS: Record<string, string> = {
    diajukan: '#1F509A',
    diverifikasi: '#0891B2',
    perlu_perbaikan: '#F59E0B',
    ditolak: '#DC2626',
    diterima: '#15803D',
};

const LABEL_STATUS: Record<string, string> = {
    diajukan: 'Menunggu diperiksa',
    diverifikasi: 'Diverifikasi',
    perlu_perbaikan: 'Perlu perbaikan',
    diterima: 'Diterima',
    ditolak: 'Ditolak',
};

const WARNA_PELUNASAN: Record<string, string> = {
    belum_bayar: '#9CA3AF',
    menunggu_verifikasi: '#F59E0B',
    ditolak: '#DC2626',
    dicicil: '#1F509A',
    lunas: '#15803D',
};

const LABEL_PELUNASAN: Record<string, string> = {
    belum_bayar: 'Belum bayar',
    menunggu_verifikasi: 'Menunggu diperiksa',
    ditolak: 'Bukti ditolak',
    dicicil: 'Dicicil',
    lunas: 'Lunas',
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

/** Urutan irisan mengikuti urutan kunci di peta warna, bukan urutan data yang
 *  masuk - supaya warna satu status tidak pernah berpindah antar kunjungan. */
function susunIrisan(jumlah: Record<string, number>, warna: Record<string, string>, label: Record<string, string>): IrisanDonut[] {
    return Object.keys(warna)
        .filter((kunci) => (jumlah[kunci] ?? 0) > 0)
        .map((kunci) => ({ label: label[kunci] ?? kunci, jumlah: jumlah[kunci], warna: warna[kunci] }));
}

function Kartu({ judul, children }: { judul: string; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <h2 className="mb-3 text-[15px] font-semibold text-gray-900">{judul}</h2>
            {children}
        </div>
    );
}

function Angka({ ikon, label, nilai, catatan }: { ikon: React.ReactNode; label: string; nilai: string; catatan?: string }) {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <div className="flex items-center gap-2.5">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#D4EBF8] text-[#0A3981]">{ikon}</span>
                <p className="text-xs text-gray-500">{label}</p>
            </div>
            <p className="mt-2 text-3xl font-bold text-[#0A3981]">{nilai}</p>
            {catatan && <p className="mt-0.5 text-xs text-gray-500">{catatan}</p>}
        </div>
    );
}

function BarisUang({ label, nominal, tebal = false }: { label: string; nominal: number; tebal?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2.5 last:border-b-0">
            <span className={tebal ? 'text-sm font-semibold text-gray-900' : 'text-sm text-gray-500'}>{label}</span>
            <span className={tebal ? 'text-sm font-bold text-gray-900' : 'text-sm text-gray-900'}>{formatRupiah(nominal)}</span>
        </div>
    );
}

export default function Dashboard({ ringkasan, statistik, keuangan, gelombangBerjalan, kuota }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const namaDepan = String(auth.user?.name ?? '').split(' ')[0];

    const tanggalHariIni = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const subjudul =
        ringkasan.total === 0 ? 'Belum ada pendaftaran yang masuk.' : `${ringkasan.total} pendaftar masuk, ${ringkasan.diterima} sudah diterima.`;

    return (
        <AppLayout>
            <Head title="Beranda" />

            <PageBanner
                ikon={<GraduationCap size={118} strokeWidth={1} />}
                tanggal={tanggalHariIni}
                judul={`Assalamu'alaikum, ${namaDepan}`}
                subjudul={subjudul}
                stripVarian={gelombangBerjalan ? 'biru' : 'abu'}
                strip={
                    gelombangBerjalan ? (
                        <p className="text-sm text-[#0A3981]">
                            <b>{gelombangBerjalan.nama}</b> berjalan sampai {gelombangBerjalan.tanggal_selesai}.
                        </p>
                    ) : (
                        <p className="text-sm text-gray-600">Belum ada gelombang PPDB yang dibuka.</p>
                    )
                }
            />

            <PageContainer wide>
                <div className="space-y-6 pt-6">
                    {/* Draft tidak ikut dihitung di mana pun di halaman ini - lihat
                        LaporanController::pendaftaranMasuk(). Disebutkan di layar
                        supaya angkanya tidak dikira selisih dengan data mentah. */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Angka
                            ikon={<Users size={18} strokeWidth={1.8} />}
                            label="Pendaftar Masuk"
                            nilai={String(ringkasan.total)}
                            catatan="formulir dan berkas sudah dikirim"
                        />

                        <Angka
                            ikon={<CheckCircle2 size={18} strokeWidth={1.8} />}
                            label="Diterima"
                            nilai={String(ringkasan.diterima)}
                            catatan="sudah memenuhi minimal bayar"
                        />
                        <Angka
                            ikon={<GraduationCap size={18} strokeWidth={1.8} />}
                            label="Sedang Diproses"
                            nilai={String(ringkasan.diproses)}
                            catatan="diperiksa, diperbaiki, atau membayar"
                        />
                        <Angka
                            ikon={<XCircle size={18} strokeWidth={1.8} />}
                            label="Ditolak"
                            nilai={String(ringkasan.ditolak)}
                            catatan="kursi kuotanya sudah dilepas"
                        />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Kartu judul="Status Pendaftaran">
                            <Donut
                                irisan={susunIrisan(statistik.pendaftaran, WARNA_STATUS, LABEL_STATUS)}
                                kalimatKosong="Belum ada pendaftaran yang masuk."
                            />
                        </Kartu>

                        <Kartu judul="Status Pembayaran">
                            <Donut
                                irisan={susunIrisan(statistik.pembayaran, WARNA_PELUNASAN, LABEL_PELUNASAN)}
                                kalimatKosong="Belum ada pendaftaran yang sampai tahap pembayaran."
                            />
                        </Kartu>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Kartu judul="Ringkasan Keuangan">
                            <BarisUang label="Total tagihan terbit" nominal={keuangan.total_tagihan} />
                            <BarisUang label="Sudah masuk (terverifikasi)" nominal={keuangan.sudah_masuk} tebal />
                            <BarisUang label="Menunggu diperiksa staf" nominal={keuangan.menunggu_diperiksa} />
                            <BarisUang label="Sisa tagihan" nominal={keuangan.sisa_tagihan} />

                            {/* Dipisah karena bobotnya beda: yang sudah terverifikasi
                                itu uang yang pasti; yang menunggu diperiksa bisa saja
                                ditolak. Menjumlahkannya berarti melaporkan uang yang
                                belum tentu ada. */}
                            <p className="mt-3 text-xs text-gray-500">
                                Uang yang menunggu diperiksa sengaja tidak dijumlahkan ke pemasukan — sebagiannya bisa saja ditolak staf.
                            </p>
                        </Kartu>

                        <Kartu judul="Sisa Kuota">
                            {kuota.length === 0 ? (
                                <p className="text-sm text-gray-500">Tidak ada gelombang yang sedang dibuka, jadi belum ada kuota yang berjalan.</p>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {kuota.map((k) => (
                                        <div key={k.nama} className="rounded-xl bg-[#F5F9FD] p-4">
                                            <p className="text-sm font-medium text-gray-900">{k.nama}</p>
                                            <p className="mt-1 text-2xl font-bold text-[#0A3981]">
                                                {k.sisa}
                                                <span className="ml-1 text-sm font-normal text-gray-500">sisa</span>
                                            </p>
                                            <p className="mt-0.5 text-xs text-gray-500">
                                                {k.terpakai} dari {k.kuota} kursi terpakai
                                            </p>
                                            {k.penuh && <p className="mt-1 text-xs font-semibold text-red-700">Kuota penuh</p>}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Kartu>
                    </div>

                    <p className="text-xs text-gray-500">
                        Pendaftaran yang masih berstatus draft — belum pernah dikirim wali — tidak ikut dihitung di halaman ini.
                    </p>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
