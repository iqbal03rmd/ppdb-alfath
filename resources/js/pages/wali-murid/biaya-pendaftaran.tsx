import AlurStepper from '@/components/alur-stepper';
import { FieldError, Kartu, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

type Status = 'belum_bayar' | 'menunggu_pembayaran' | 'menunggu_verifikasi' | 'ditolak' | 'siap_digunakan';

interface Props {
    status: Status;
    biaya: number | null;
    gelombang: { nama: string } | null;
    bisaMengirim: boolean;
    bisaReservasi: boolean;
    kategoriSiswa: { id: number; nama: string; deskripsi: string | null; penuh: boolean }[];
    reservasi: { id: number; jalur: string | null; batas_bayar: string | null; berakhir_pada: string | null } | null;
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

const statusInfo: Record<Exclude<Status, 'belum_bayar' | 'menunggu_pembayaran'>, { judul: string; isi: string; gaya: string }> = {
    menunggu_verifikasi: {
        judul: 'Bukti sedang diperiksa',
        isi: 'Formulir anak dapat diisi setelah pembayaran disetujui.',
        gaya: 'border-amber-200 bg-amber-50 text-amber-800',
    },
    ditolak: {
        judul: 'Bukti pembayaran ditolak',
        isi: 'Perbaiki bukti sesuai catatan staf.',
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

export default function BiayaPendaftaran({
    status,
    biaya,
    gelombang,
    bisaMengirim,
    bisaReservasi,
    kategoriSiswa,
    reservasi,
    informasiPembayaran,
    catatanPenolakan,
    riwayat,
}: Props) {
    const pilihan = useForm({ kategori_siswa_id: '' });
    const jalurDipilih = kategoriSiswa.find((k) => String(k.id) === pilihan.data.kategori_siswa_id);
    const [waktuSekarang, setWaktuSekarang] = useState(Date.now);
    const kedaluwarsa = !!reservasi?.berakhir_pada && waktuSekarang >= Date.parse(reservasi.berakhir_pada);
    useEffect(() => {
        if (!reservasi?.berakhir_pada) return;
        const timer = window.setTimeout(() => setWaktuSekarang(Date.now()), Math.max(0, Date.parse(reservasi.berakhir_pada) - Date.now() + 100));
        return () => window.clearTimeout(timer);
    }, [reservasi?.berakhir_pada]);
    const info = !gelombang
        ? {
              judul: 'Pendaftaran sedang ditutup',
              isi: 'Pembayaran biaya pendaftaran dapat dilakukan setelah gelombang berikutnya dibuka.',
              gaya: 'border-gray-200 bg-gray-50 text-gray-700',
          }
        : status === 'belum_bayar' || status === 'menunggu_pembayaran'
          ? null
          : statusInfo[status];
    const form = useForm<{ reservasi_id: number | null; nominal_transfer: string; tanggal_transfer: string; bukti_transfer: File | null }>({
        reservasi_id: reservasi?.id ?? null,
        nominal_transfer: biaya === null ? '' : String(biaya),
        tanggal_transfer: new Date().toISOString().slice(0, 10),
        bukti_transfer: null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, reservasi_id: reservasi?.id ?? null }));
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
                    {status === 'menunggu_pembayaran' && reservasi && (
                        <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                            <p className="font-semibold">Jalur: {reservasi.jalur}</p>
                            {kedaluwarsa ? (
                                <div className="mt-1 space-y-2" role="status">
                                    <p>Waktu pembayaran habis. Sudah transfer? Hubungi Staf PPDB.</p>
                                    <Button variant="outline" onClick={() => router.reload()}>
                                        Periksa Ketersediaan
                                    </Button>
                                </div>
                            ) : (
                                <p className="mt-1">
                                    Bayar dan kirim bukti sebelum <strong>{reservasi.batas_bayar}</strong>.
                                </p>
                            )}
                            <FieldError message={form.errors.reservasi_id} />
                        </div>
                    )}
                    {info && (
                        <div className={`rounded-2xl border p-5 ${info.gaya}`}>
                            <p className="font-semibold">{info.judul}</p>
                            {info.isi && <p className="mt-1 text-sm">{info.isi}</p>}
                            {reservasi?.jalur && <p className="mt-3 text-sm font-semibold">Jalur: {reservasi.jalur}</p>}
                            <FieldError message={form.errors.reservasi_id} />
                            {catatanPenolakan && <p className="mt-3 rounded-lg bg-white/70 p-3 text-sm">Catatan staf: {catatanPenolakan}</p>}
                            {status === 'siap_digunakan' && (
                                <Button asChild className="mt-4 rounded-xl font-bold">
                                    <Link href={route('wali-murid.pendaftaran.create')}>Isi Formulir Anak</Link>
                                </Button>
                            )}
                        </div>
                    )}

                    {bisaReservasi && (
                        <Kartu judul="Jalur Pendaftaran">
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    pilihan.post(route('wali-murid.biaya-pendaftaran.reservasi'));
                                }}
                                className="space-y-4"
                            >
                                <div>
                                    <Label required htmlFor="jalur">
                                        Jalur anak
                                    </Label>
                                    <select
                                        id="jalur"
                                        required
                                        value={pilihan.data.kategori_siswa_id}
                                        onChange={(event) => pilihan.setData('kategori_siswa_id', event.target.value)}
                                        className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3 py-3 text-sm [&>option:disabled]:text-gray-400"
                                    >
                                        <option value="">Pilih jalur pendaftaran</option>
                                        {kategoriSiswa.map((jalur) => (
                                            <option key={jalur.id} value={jalur.id} disabled={jalur.penuh}>
                                                {jalur.nama}
                                                {jalur.penuh ? ' — Penuh' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {jalurDipilih?.deskripsi && <p className="mt-2 text-sm text-gray-500">{jalurDipilih.deskripsi}</p>}
                                    <FieldError message={pilihan.errors.kategori_siswa_id} />
                                </div>
                                <p className="text-sm text-gray-600">
                                    Biaya per anak: <strong>{biaya === null ? '-' : rupiah(biaya)}</strong>
                                </p>
                                <Button
                                    type="submit"
                                    disabled={pilihan.processing || !pilihan.data.kategori_siswa_id || jalurDipilih?.penuh}
                                    className="w-full rounded-xl sm:w-auto"
                                >
                                    {pilihan.processing ? 'Memproses...' : 'Lanjut ke Pembayaran'}
                                </Button>
                            </form>
                        </Kartu>
                    )}
                    {gelombang && !bisaReservasi && !reservasi && (
                        <p className="text-sm text-amber-700">Rekening sekolah belum tersedia. Hubungi Staf PPDB sebelum membayar.</p>
                    )}

                    <div className={`grid items-start gap-6 ${riwayat.length > 0 && informasiPembayaran && !kedaluwarsa ? 'lg:grid-cols-2' : ''}`}>
                        {informasiPembayaran && !kedaluwarsa && (
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
                        )}

                        {riwayat.length > 0 && (
                            <div
                                className={`h-[360px] ${informasiPembayaran && !kedaluwarsa ? 'lg:relative lg:h-auto lg:min-h-0 lg:self-stretch' : ''}`}
                            >
                                <Kartu
                                    judul="Riwayat Pembayaran Pendaftaran"
                                    className={`flex h-full flex-col ${informasiPembayaran && !kedaluwarsa ? 'lg:absolute lg:inset-0' : ''}`}
                                >
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

                    {bisaMengirim && !kedaluwarsa && (
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
                                {Object.keys(form.errors).length > 0 && (
                                    <p role="alert" className="text-sm font-medium text-red-600">
                                        Bukti belum terkirim: {Object.values(form.errors)[0]}
                                    </p>
                                )}
                            </form>
                        </Kartu>
                    )}
                </div>
            </PageContainer>
        </AppLayout>
    );
}
