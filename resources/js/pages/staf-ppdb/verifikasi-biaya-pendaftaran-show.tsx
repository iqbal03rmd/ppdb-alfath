import ConfirmationDialog from '@/components/confirmation-dialog';
import { Kartu } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';

interface Props {
    pembayaran: {
        id: number;
        status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak';
        nominal_tagihan: number;
        nominal_transfer: number;
        tanggal_transfer: string;
        diunggah_pada: string;
        catatan_verifikasi: string | null;
        diperiksa_oleh: string | null;
        bukti_url: string;
        bukti_gambar: boolean;
        sudah_digunakan: boolean;
    };
    wali: { nama: string; email: string; telepon: string | null };
    pendaftaran: { nama: string; nomor: string } | null;
}

const statusBadge = {
    menunggu_verifikasi: 'bg-amber-100 text-amber-700',
    terverifikasi: 'bg-green-100 text-green-700',
    ditolak: 'bg-red-100 text-red-700',
};
const statusLabel = { menunggu_verifikasi: 'Menunggu Verifikasi', terverifikasi: 'Disetujui', ditolak: 'Ditolak' };
const rupiah = (nominal: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);

function Baris({ label, nilai, tebal = false }: { label: string; nilai: string; tebal?: boolean }) {
    return (
        <div className="flex justify-between gap-4 border-b border-gray-100 py-2 last:border-0">
            <span className="text-sm text-gray-500">{label}</span>
            <span className={`text-right text-sm ${tebal ? 'font-semibold text-[#0A3981]' : 'text-gray-900'}`}>{nilai}</span>
        </div>
    );
}

export default function VerifikasiBiayaPendaftaranShow({ pembayaran, wali, pendaftaran }: Props) {
    const [konfirmasi, setKonfirmasi] = useState(false);
    const [tolakTampil, setTolakTampil] = useState(false);
    const sah = useForm({});
    const tolak = useForm({ catatan_verifikasi: '' });
    const bolehSah = pembayaran.status === 'menunggu_verifikasi';
    const bolehTolak = !pembayaran.sudah_digunakan && (pembayaran.status === 'menunggu_verifikasi' || pembayaran.status === 'terverifikasi');

    return (
        <AppLayout>
            <Head title={`Periksa Biaya Pendaftaran — ${wali.nama}`} />
            <PageHeader title={wali.nama} subtitle="Periksa biaya pendaftaran sebelum formulir anak dibuka" wide />
            <PageContainer wide>
                <Button asChild variant="outline" size="icon" className="mb-5 rounded-xl border-[#1F509A]/40 text-[#1F509A]">
                    <Link href={route('staf-ppdb.verifikasi-biaya-pendaftaran.index')} aria-label="Kembali">
                        <ArrowLeft size={18} />
                    </Link>
                </Button>
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <Kartu judul="Bukti Transfer">
                            <div className="mb-4 flex flex-wrap items-center gap-3">
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[pembayaran.status]}`}>
                                    {statusLabel[pembayaran.status]}
                                </span>
                                <span className="text-xs text-gray-500">Diunggah {pembayaran.diunggah_pada}</span>
                            </div>
                            {pembayaran.bukti_gambar ? (
                                <a href={pembayaran.bukti_url} target="_blank" rel="noreferrer">
                                    <img
                                        src={pembayaran.bukti_url}
                                        alt="Bukti transfer"
                                        className="mx-auto max-h-[430px] rounded-xl border object-contain"
                                    />
                                </a>
                            ) : (
                                <a
                                    href={pembayaran.bukti_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block rounded-xl bg-[#F5F9FD] p-8 text-center text-sm text-[#1F509A] underline"
                                >
                                    Buka bukti transfer (PDF)
                                </a>
                            )}
                        </Kartu>
                    </div>
                    <div className="space-y-6">
                        <Kartu judul="Rincian">
                            <Baris label="Wali" nilai={wali.nama} />
                            <Baris label="Email" nilai={wali.email} />
                            <Baris label="Telepon" nilai={wali.telepon ?? '-'} />
                            <Baris label="Biaya yang ditagihkan" nilai={rupiah(pembayaran.nominal_tagihan)} />
                            <Baris label="Nominal transfer" nilai={rupiah(pembayaran.nominal_transfer)} tebal />
                            <Baris label="Tanggal transfer" nilai={pembayaran.tanggal_transfer} />
                            {pendaftaran && <Baris label="Digunakan untuk" nilai={`${pendaftaran.nama} · ${pendaftaran.nomor}`} />}
                            {pembayaran.nominal_transfer !== pembayaran.nominal_tagihan && (
                                <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                    Nominal transfer berbeda dari biaya yang ditagihkan.
                                </p>
                            )}
                        </Kartu>
                        {(bolehSah || bolehTolak) && (
                            <Kartu judul="Keputusan">
                                {!tolakTampil ? (
                                    <>
                                        <p className="mb-4 text-sm text-gray-500">Sahkan hanya jika bukti dan nominal transfer sesuai.</p>
                                        {bolehSah && (
                                            <Button className="w-full rounded-xl font-bold" onClick={() => setKonfirmasi(true)}>
                                                Sahkan Pembayaran
                                            </Button>
                                        )}
                                        {bolehTolak && (
                                            <Button
                                                variant="outline"
                                                className="mt-2 w-full rounded-xl border-red-300 font-bold text-red-700"
                                                onClick={() => setTolakTampil(true)}
                                            >
                                                {pembayaran.status === 'terverifikasi' ? 'Batalkan Pengesahan' : 'Tolak Pembayaran'}
                                            </Button>
                                        )}
                                    </>
                                ) : (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            tolak.post(route('staf-ppdb.verifikasi-biaya-pendaftaran.tolak', pembayaran.id));
                                        }}
                                    >
                                        <Textarea
                                            rows={4}
                                            value={tolak.data.catatan_verifikasi}
                                            onChange={(e) => tolak.setData('catatan_verifikasi', e.target.value)}
                                            placeholder="Tuliskan alasan penolakan..."
                                        />
                                        {tolak.errors.catatan_verifikasi && (
                                            <p className="mt-1 text-xs text-red-600">{tolak.errors.catatan_verifikasi}</p>
                                        )}
                                        <Button className="mt-3 w-full rounded-xl" disabled={tolak.processing}>
                                            Kirim Penolakan
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="mt-2 w-full rounded-xl"
                                            onClick={() => setTolakTampil(false)}
                                        >
                                            Batal
                                        </Button>
                                    </form>
                                )}
                            </Kartu>
                        )}
                    </div>
                </div>
            </PageContainer>
            <ConfirmationDialog
                open={konfirmasi}
                title="Sahkan pembayaran?"
                description={`Pembayaran ${rupiah(pembayaran.nominal_transfer)} akan menjadi tiket untuk satu pendaftaran anak.`}
                confirmLabel="Ya, sahkan"
                cancelLabel="Periksa lagi"
                tone="warning"
                confirmDisabled={sah.processing}
                onConfirm={() =>
                    sah.post(route('staf-ppdb.verifikasi-biaya-pendaftaran.sahkan', pembayaran.id), { onSuccess: () => setKonfirmasi(false) })
                }
                onCancel={() => setKonfirmasi(false)}
            />
        </AppLayout>
    );
}
