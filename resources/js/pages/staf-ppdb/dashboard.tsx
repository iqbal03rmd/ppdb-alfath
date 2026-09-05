import Donut, { type IrisanDonut } from '@/components/donut';
import PageBanner from '@/components/page-banner';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck, Wallet } from 'lucide-react';

interface AntrianItem {
    jumlah: number;
}

interface KuotaItem {
    nama: string;
    kuota: number;
    terpakai: number;
    sisa: number;
    penuh: boolean;
}

interface DashboardProps {
    antrian: {
        pendaftaran: AntrianItem;
        transfer: AntrianItem;
    };
    statistik: { pendaftaran: Record<string, number>; pembayaran: Record<string, number> };
    kuota: {
        gelombang: string | null;
        tanggal_selesai: string | null;
        kategori: KuotaItem[];
    };
}

/**
 * Warna diagram. Bukan warna badge (itu kelas Tailwind untuk latar pucat), tapi
 * versi pekatnya supaya terbaca sebagai irisan di atas putih - hue-nya sengaja
 * dijaga sedekat mungkin dengan badge statusnya masing-masing.
 *
 * Lima warna berhue ini sudah lolos uji keterbedaan, termasuk untuk mata yang
 * tidak bisa membedakan merah-hijau. Jangan menambah atau menukar sembarangan;
 * kalau ada status baru, ujinya diulang. Abu-abu dipakai untuk keadaan "belum
 * jadi apa-apa" (draft, belum bayar), bukan sebagai warna kategori.
 */
const WARNA_STATUS: Record<string, string> = {
    draft: '#9CA3AF',
    diajukan: '#1F509A',
    diverifikasi: '#0891B2',
    perlu_perbaikan: '#F59E0B',
    ditolak: '#DC2626',
    diterima: '#15803D',
};

const LABEL_STATUS: Record<string, string> = {
    draft: 'Draft',
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

/** Kartu antrian: satu angka besar dan satu tautan ke antriannya. */
function KartuAntrian({
    judul,
    ikon,
    jumlah,
    satuan,
    kalimatKosong,
    tautan,
    labelTautan,
}: {
    judul: string;
    ikon: React.ReactNode;
    jumlah: number;
    satuan: string;
    kalimatKosong: string;
    tautan: string;
    labelTautan: string;
}) {
    return (
        <div className="flex flex-col rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <div className="mb-3 flex items-center gap-2.5">
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#D4EBF8] text-[#0A3981]">{ikon}</span>
                <h2 className="text-[15px] font-semibold text-gray-900">{judul}</h2>
            </div>

            {jumlah === 0 ? (
                <p className="flex-1 text-sm text-gray-500">{kalimatKosong}</p>
            ) : (
                <p className="flex flex-1 items-baseline gap-2">
                    <span className="text-4xl font-bold text-[#0A3981]">{jumlah}</span>
                    <span className="text-sm text-gray-500">{satuan}</span>
                </p>
            )}

            <Button
                asChild
                variant="outline"
                className="mt-4 w-full rounded-xl border-[#1F509A]/40 bg-white font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
            >
                <Link href={tautan}>{labelTautan}</Link>
            </Button>
        </div>
    );
}

export default function Dashboard({ antrian, statistik, kuota }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const namaDepan = String(auth.user?.name ?? '').split(' ')[0];

    // Pendaftaran dan bukti transfer TIDAK dijumlahkan jadi satu angka: satuannya
    // beda, dan tidak ada satu kata yang benar untuk mewakili keduanya - "berkas"
    // di aplikasi ini sudah punya arti tetap, yaitu dokumen persyaratan.
    const menunggu = [
        antrian.pendaftaran.jumlah > 0 ? `${antrian.pendaftaran.jumlah} pendaftaran` : null,
        antrian.transfer.jumlah > 0 ? `${antrian.transfer.jumlah} bukti transfer` : null,
    ].filter(Boolean);

    const subtitleText =
        menunggu.length === 0
            ? 'Semua antrian kosong — tidak ada yang menunggu dikerjakan.'
            : `${menunggu.join(' dan ')} menunggu diperiksa.`;

    const irisanPendaftaran = susunIrisan(statistik.pendaftaran, WARNA_STATUS, LABEL_STATUS);
    const irisanPembayaran = susunIrisan(statistik.pembayaran, WARNA_PELUNASAN, LABEL_PELUNASAN);

    const tanggalHariIni = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <AppLayout>
            <Head title="Beranda" />

            {/* Banner ciri khas yang sama dengan Beranda wali - lihat PageBanner. */}
            <PageBanner
                ikon={<ClipboardCheck size={118} strokeWidth={1} />}
                tanggal={tanggalHariIni}
                judul={`Assalamu'alaikum, ${namaDepan}`}
                subjudul={subtitleText}
                stripVarian={kuota.gelombang ? 'biru' : 'abu'}
                strip={
                    kuota.gelombang ? (
                        <p className="text-sm text-[#0A3981]">
                            <b>{kuota.gelombang}</b> berjalan sampai {kuota.tanggal_selesai}.
                        </p>
                    ) : (
                        <p className="text-sm text-gray-600">
                            Belum ada gelombang PPDB yang dibuka. Arsip pendaftaran lama tetap bisa dibuka lewat menu Semua Pendaftaran.
                        </p>
                    )
                }
            />

            <PageContainer wide>
                <div className="space-y-6 pt-6">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <KartuAntrian
                            judul="Pendaftaran Menunggu Diperiksa"
                            ikon={<ClipboardCheck size={18} strokeWidth={2} />}
                            jumlah={antrian.pendaftaran.jumlah}
                            satuan="pendaftaran"
                            kalimatKosong="Tidak ada pendaftaran yang menunggu diperiksa. Antrian ini terisi lagi begitu ada wali yang mengirim berkas."
                            tautan={route('staf-ppdb.verifikasi-pendaftaran.index')}
                            labelTautan="Buka Antrian Pendaftaran"
                        />

                        <KartuAntrian
                            judul="Bukti Transfer Menunggu Diperiksa"
                            ikon={<Wallet size={18} strokeWidth={2} />}
                            jumlah={antrian.transfer.jumlah}
                            satuan="transfer"
                            kalimatKosong="Tidak ada bukti transfer yang menunggu diperiksa."
                            tautan={route('staf-ppdb.verifikasi-pembayaran.index')}
                            labelTautan="Buka Antrian Pembayaran"
                        />
                    </div>

                    {/* Dua sudut pandang yang sengaja dipisah: sampai mana proses
                        pendaftarannya, dan sampai mana uangnya. Satu anak bisa sudah
                        diterima tapi pembayarannya baru dicicil - digabung jadi satu
                        diagram, dua kabar itu saling menutupi. */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Kartu judul="Status Pendaftaran">
                            <Donut irisan={irisanPendaftaran} kalimatKosong="Belum ada pendaftaran sama sekali." />
                        </Kartu>

                        <Kartu judul="Status Pembayaran">
                            <Donut
                                irisan={irisanPembayaran}
                                kalimatKosong="Belum ada pendaftaran yang sampai tahap pembayaran."
                            />
                        </Kartu>
                    </div>

                    <Kartu judul="Sisa Kuota per Kategori">
                        {kuota.gelombang === null ? (
                            <p className="text-sm text-gray-500">Tidak ada gelombang yang sedang dibuka, jadi belum ada kuota yang berjalan.</p>
                        ) : kuota.kategori.length === 0 ? (
                            <p className="text-sm text-gray-500">
                                Kuota untuk {kuota.gelombang} belum ditetapkan, jadi tidak ada kategori yang dibatasi.
                            </p>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {kuota.kategori.map((k) => (
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
            </PageContainer>
        </AppLayout>
    );
}
