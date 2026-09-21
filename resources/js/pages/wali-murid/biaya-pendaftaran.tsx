import AlurStepper from '@/components/alur-stepper';
import { FieldError, Kartu, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Status = 'belum_bayar' | 'menunggu_verifikasi' | 'ditolak' | 'siap_digunakan';

interface Props {
    status: Status;
    biaya: number | null;
    gelombang: { nama: string } | null;
    bisaMengirim: boolean;
    informasiPembayaran: {
        nama_bank: string;
        nomor_rekening: string;
        nama_pemilik_rekening: string;
        instruksi: string | null;
    } | null;
    catatanPenolakan: string | null;
    riwayat: {
        id: number;
        nominal_transfer: number;
        tanggal_transfer: string;
        status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak';
        gelombang: string;
        catatan_verifikasi: string | null;
        digunakan_untuk: { nama: string; nomor: string } | null;
    }[];
}

const statusInfo: Record<Status, { judul: string; isi: string; gaya: string }> = {
    belum_bayar: {
        judul: 'Selesaikan pembayaran untuk mendaftarkan anak',
        isi: 'Transfer biaya pendaftaran, lalu unggah bukti di halaman ini. Formulir anak dapat diisi setelah pembayaran disetujui staf.',
        gaya: 'border-blue-200 bg-blue-50 text-blue-800',
    },
    menunggu_verifikasi: {
        judul: 'Bukti sedang diperiksa',
        isi: 'Bukti pembayaran sudah diterima dan sedang diperiksa staf. Formulir anak akan terbuka setelah pembayaran disetujui.',
        gaya: 'border-amber-200 bg-amber-50 text-amber-800',
    },
    ditolak: {
        judul: 'Bukti pembayaran ditolak',
        isi: '',
        gaya: 'border-red-200 bg-red-50 text-red-800',
    },
    siap_digunakan: {
        judul: 'Pembayaran disetujui',
        isi: 'Kamu sudah dapat mengisi satu formulir pendaftaran anak.',
        gaya: 'border-green-200 bg-green-50 text-green-800',
    },
};

const badge = {
    menunggu_verifikasi: 'bg-amber-100 text-amber-700',
    terverifikasi: 'bg-green-100 text-green-700',
    ditolak: 'bg-red-100 text-red-700',
};

const labelStatus = { menunggu_verifikasi: 'Menunggu', terverifikasi: 'Disetujui', ditolak: 'Ditolak' };

function rupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

export default function BiayaPendaftaran({ status, biaya, gelombang, bisaMengirim, informasiPembayaran, catatanPenolakan, riwayat }: Props) {
    const info = gelombang
        ? statusInfo[status]
        : {
              judul: 'Pendaftaran sedang ditutup',
              isi: 'Pembayaran biaya pendaftaran dapat dilakukan setelah gelombang berikutnya dibuka.',
              gaya: 'border-gray-200 bg-gray-50 text-gray-700',
          };
    const form = useForm<{ nominal_transfer: string; tanggal_transfer: string; bukti_transfer: File | null }>({
        nominal_transfer: biaya === null ? '' : String(biaya),
        tanggal_transfer: new Date().toISOString().slice(0, 10),
        bukti_transfer: null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('wali-murid.biaya-pendaftaran.store'), { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Biaya Pendaftaran" />
            <PageHeader
                title="Pembayaran Sebelum Mendaftarkan Anak"
                subtitle={gelombang ? `${gelombang.nama} · Satu pembayaran membuka satu formulir anak` : 'Menunggu gelombang pendaftaran dibuka'}
                wide
            />

            <PageContainer wide>
                <AlurStepper aktif="Biaya Pendaftaran" />
                <div className="space-y-6">
                    <div className={`rounded-2xl border p-5 ${info.gaya}`}>
                        <p className="font-semibold">{info.judul}</p>
                        {info.isi && <p className="mt-1 text-sm">{info.isi}</p>}
                        {catatanPenolakan && <p className="mt-3 rounded-lg bg-white/70 p-3 text-sm">Catatan staf: {catatanPenolakan}</p>}
                        {status === 'siap_digunakan' && (
                            <Button asChild className="mt-4 rounded-xl font-bold">
                                <Link href={route('wali-murid.pendaftaran.create')}>Isi Formulir Anak</Link>
                            </Button>
                        )}
                    </div>

                    <div className={`grid items-start gap-6 ${riwayat.length > 0 ? 'lg:grid-cols-2' : ''}`}>
                        <Kartu judul="Tujuan Pembayaran">
                            {informasiPembayaran ? (
                                <div className="space-y-3 text-sm">
                                    <div>
                                        <p className="text-gray-500">Biaya per anak</p>
                                        <p className="text-xl font-bold text-[#0A3981]">{biaya === null ? '-' : rupiah(biaya)}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500">Bank</p>
                                        <p className="font-semibold text-gray-900">{informasiPembayaran.nama_bank}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500">Nomor rekening</p>
                                        <p className="font-semibold text-gray-900">{informasiPembayaran.nomor_rekening}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500">Atas nama</p>
                                        <p className="font-semibold text-gray-900">{informasiPembayaran.nama_pemilik_rekening}</p>
                                    </div>
                                    {informasiPembayaran.instruksi && (
                                        <p className="rounded-lg bg-[#F5F9FD] p-3 text-gray-600">{informasiPembayaran.instruksi}</p>
                                    )}
                                </div>
                            ) : (
                                <p className="text-sm text-amber-700">Rekening sekolah belum tersedia. Hubungi Staf PPDB.</p>
                            )}
                        </Kartu>

                        {riwayat.length > 0 && (
                            <div className="h-[360px] lg:relative lg:h-auto lg:min-h-0 lg:self-stretch">
                                <Kartu judul="Riwayat Pembayaran Pendaftaran" className="flex h-full flex-col lg:absolute lg:inset-0">
                                    <div className="min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto pr-2">
                                        {riwayat.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                            >
                                                <div>
                                                    <p className="font-medium text-gray-900">{rupiah(item.nominal_transfer)}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {item.gelombang} · {item.tanggal_transfer}
                                                        {item.digunakan_untuk ? ` · Digunakan untuk ${item.digunakan_untuk.nama}` : ''}
                                                    </p>
                                                </div>
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge[item.status]}`}>
                                                    {labelStatus[item.status]}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </Kartu>
                            </div>
                        )}
                    </div>

                    {bisaMengirim && (
                        <Kartu judul={status === 'ditolak' ? 'Unggah Ulang Bukti' : 'Kirim Bukti Pembayaran'}>
                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <Label required htmlFor="nominal_transfer">
                                        Nominal Transfer
                                    </Label>
                                    <Input
                                        id="nominal_transfer"
                                        type="number"
                                        min={1}
                                        value={form.data.nominal_transfer}
                                        onChange={(e) => form.setData('nominal_transfer', e.target.value)}
                                        className="bg-[#F5F9FD]"
                                    />
                                    <FieldError message={form.errors.nominal_transfer} />
                                </div>
                                <div>
                                    <Label required htmlFor="tanggal_transfer">
                                        Tanggal Transfer
                                    </Label>
                                    <Input
                                        id="tanggal_transfer"
                                        type="date"
                                        max={new Date().toISOString().slice(0, 10)}
                                        value={form.data.tanggal_transfer}
                                        onChange={(e) => form.setData('tanggal_transfer', e.target.value)}
                                        className="bg-[#F5F9FD]"
                                    />
                                    <FieldError message={form.errors.tanggal_transfer} />
                                </div>
                                <div>
                                    <Label required htmlFor="bukti_transfer">
                                        Bukti Transfer
                                    </Label>
                                    <Input
                                        id="bukti_transfer"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(e) => form.setData('bukti_transfer', e.target.files?.[0] ?? null)}
                                        className="bg-[#F5F9FD]"
                                    />
                                    <p className="mt-1 text-xs font-medium text-[#1F509A]">PDF/JPG/PNG · Maks. 2 MB</p>
                                    <FieldError message={form.errors.bukti_transfer} />
                                </div>
                                <Button type="submit" disabled={form.processing} className="w-full rounded-xl font-bold">
                                    {form.processing ? 'Mengirim...' : 'Kirim Bukti'}
                                </Button>
                            </form>
                        </Kartu>
                    )}
                </div>
            </PageContainer>
        </AppLayout>
    );
}
