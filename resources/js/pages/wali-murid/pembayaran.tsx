import AlurStepper from '@/components/alur-stepper';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface RincianItem {
    nama: string;
    keterangan: string | null;
    nominal: number;
}

interface TransferItem {
    nominal_transfer: number;
    tanggal_transfer: string;
    bukti_transfer_url: string;
    status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak';
    catatan_verifikasi: string | null;
}

interface PembayaranProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        nama_pendaftar: string;
    };
    rincianTagihan: RincianItem[];
    totalTagihan: number;
    totalTerbayar: number;
    sisaTagihan: number;
    riwayatTransfer: TransferItem[];
    tagihanTersedia: boolean;
    // Dua tenggat yang berbeda artinya: batasWaktu* itu tenggat MINIMAL bayar
    // (lewat = boleh ditolak staf), batasPelunasan* cuma menagih sisa cicilan.
    batasWaktuPembayaran: string | null;
    batasWaktuLewat: boolean;
    batasPelunasan: string | null;
    minimalBayar: number | null;
    kurangMinimal: number;
    sudahPenuhiMinimal: boolean;
    menunggak: boolean;
    statusPendaftaran: string;
    catatanVerifikasi: string | null;
    bisaBayar: boolean;
}

const statusBadge: Record<string, { label: string; className: string }> = {
    menunggu_verifikasi: { label: 'Menunggu Verifikasi', className: 'bg-amber-100 text-amber-700' },
    terverifikasi: { label: 'Terverifikasi', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

export default function Pembayaran({
    pendaftaran,
    rincianTagihan,
    totalTagihan,
    totalTerbayar,
    sisaTagihan,
    riwayatTransfer,
    tagihanTersedia,
    batasWaktuPembayaran,
    batasWaktuLewat,
    batasPelunasan,
    minimalBayar,
    kurangMinimal,
    sudahPenuhiMinimal,
    menunggak,
    statusPendaftaran,
    catatanVerifikasi,
    bisaBayar,
}: PembayaranProps) {
    const { data, setData, post, processing, errors } = useForm({
        nominal_transfer: '',
        tanggal_transfer: '',
        bukti_transfer: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('wali-murid.pembayaran.store', pendaftaran.id), { forceFormData: true });
    };

    // Kalau ada transfer yang masih diproses (menunggu_verifikasi), itu satu-satunya
    // alasan form disembunyikan padahal sisa tagihan masih > 0 - bedain dari kondisi lunas.
    const adaPending = riwayatTransfer.some((t) => t.status === 'menunggu_verifikasi');
    const transferTerakhirDitolak = riwayatTransfer[0]?.status === 'ditolak';
    // Pendaftaran ditolak: halaman tetap dibuka read-only supaya wali bisa
    // melihat uang yang sudah terlanjur disetor, tapi nggak bisa nambah transfer.
    const pendaftaranDitolak = statusPendaftaran === 'ditolak';

    // Kolom kanan cuma dipasang kalau ada isinya - kalau tidak (mis. pendaftaran
    // ditolak tanpa pernah bayar), rincian tagihan memakai lebar penuh daripada
    // menyisakan kolom kosong.
    const adaIsiKanan = totalTerbayar > 0 || bisaBayar || adaPending;

    return (
        <AppLayout>
            <Head title="Pembayaran" />
            <PageHeader title="Pembayaran" subtitle={`${pendaftaran.nomor_pendaftaran} — ${pendaftaran.nama_pendaftar}`} wide />

            <PageContainer wide>
                <AlurStepper aktif="Pembayaran" />

                {/* Peringatan tingkat halaman - lebar penuh di atas dua kolom, karena
                    menyangkut seluruh pendaftaran, bukan salah satu kolom saja. */}

                {/* Pendaftaran ditutup staf - halaman jadi arsip, bukan tempat bayar */}
                {pendaftaranDitolak && (
                    <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <span className="font-semibold text-red-800">Pendaftaran ini ditolak. </span>
                        {catatanVerifikasi ?? 'Pendaftaran ini sudah ditutup oleh Staf PPDB.'}
                        {totalTerbayar > 0 && (
                            <p className="mt-2">
                                Pembayaran yang sudah terverifikasi sebesar <b>{formatRupiah(totalTerbayar)}</b> tetap tercatat di bawah. Hubungi Staf
                                PPDB untuk menanyakan tindak lanjutnya.
                            </p>
                        )}
                    </div>
                )}

                {/* Dua tenggat, dan bedanya menentukan nasib pendaftaran:
                      - sebelum minimal bayar tercapai -> yang berlaku tenggat gelombang,
                        dan melewatinya bisa berujung penolakan oleh staf.
                      - sesudahnya -> tinggal cicilan dengan tenggat tahun ajaran, yang
                        TIDAK pernah membatalkan pendaftaran yang sudah diterima.
                    Dua-duanya informatif; keputusan menolak tetap di tangan Staf PPDB. */}
                {!pendaftaranDitolak && tagihanTersedia && sisaTagihan > 0 && !sudahPenuhiMinimal && batasWaktuPembayaran && (
                    <div
                        className={
                            'mb-6 rounded-xl border p-4 text-sm ' +
                            (batasWaktuLewat ? 'border-red-200 bg-red-50 text-red-700' : 'border-[#D4EBF8] bg-[#F5F9FD] text-[#0A3981]')
                        }
                    >
                        {batasWaktuLewat ? (
                            <>
                                <span className="font-semibold text-red-800">Batas waktu pembayaran terlewat. </span>
                                Jatuh tempo {batasWaktuPembayaran}. Segera hubungi Staf PPDB untuk menanyakan status pendaftaran ini.
                            </>
                        ) : (
                            <>
                                <span className="font-semibold">Bayar minimal {formatRupiah(kurangMinimal)} lagi </span>
                                sebelum {batasWaktuPembayaran} supaya pendaftaran diterima. Sisanya boleh dicicil
                                {batasPelunasan ? ` sampai ${batasPelunasan}` : ''}.
                            </>
                        )}
                    </div>
                )}

                {!pendaftaranDitolak && tagihanTersedia && sisaTagihan > 0 && sudahPenuhiMinimal && (
                    <div
                        className={
                            'mb-6 rounded-xl border p-4 text-sm ' +
                            (menunggak ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700')
                        }
                    >
                        {menunggak ? (
                            <>
                                <span className="font-semibold text-red-800">Batas pelunasan terlewat. </span>
                                Sisa cicilan {formatRupiah(sisaTagihan)} jatuh tempo {batasPelunasan}. Pendaftaran tetap diterima — silakan hubungi
                                Staf PPDB untuk mengatur pelunasannya.
                            </>
                        ) : (
                            <>
                                <span className="font-semibold">Minimal pembayaran sudah terpenuhi. </span>
                                Sisa {formatRupiah(sisaTagihan)} boleh dicicil
                                {batasPelunasan ? ` sampai ${batasPelunasan}` : ''}.
                            </>
                        )}
                    </div>
                )}

                {/* Admin belum menyiapkan komponen biaya - tagihan belum bisa diterbitkan */}
                {!tagihanTersedia && !pendaftaranDitolak && (
                    <div className="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                        <span className="font-semibold text-gray-700">Tagihan belum tersedia. </span>
                        Sekolah belum menetapkan rincian biaya untuk gelombang ini, jadi pembayaran belum bisa dilakukan. Silakan hubungi Staf PPDB
                        untuk informasi lebih lanjut.
                    </div>
                )}

                {/* Dua kolom di layar lebar: KIRI catatan tagihan & riwayat, KANAN
                    ringkasan + tempat bertindak. Di bawah lg menumpuk jadi satu kolom
                    dengan urutan alami: lihat tagihannya dulu, baru bayar. */}
                <div className={`grid gap-6 ${adaIsiKanan ? 'lg:grid-cols-5' : 'lg:grid-cols-1'}`}>
                    <div className={`space-y-6 ${adaIsiKanan ? 'lg:col-span-3' : ''}`}>
                        {/* Rincian Tagihan */}
                        <div className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                            <div className="border-b border-gray-100 p-6">
                                <h2 className="text-[15px] font-semibold text-gray-900">Rincian Tagihan</h2>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {rincianTagihan.length === 0 ? (
                                    <p className="p-6 text-sm text-gray-500">Rincian tagihan untuk gelombang ini belum ditetapkan sekolah.</p>
                                ) : (
                                    rincianTagihan.map((item, i) => (
                                        <div key={i} className="flex items-center justify-between gap-4 p-6">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900">{item.nama}</p>
                                                {item.keterangan && <p className="mt-0.5 text-xs text-gray-500">{item.keterangan}</p>}
                                            </div>
                                            <span className="shrink-0 text-sm font-medium text-gray-700">{formatRupiah(item.nominal)}</span>
                                        </div>
                                    ))
                                )}
                            </div>
                            <div className="flex items-center justify-between gap-4 bg-[#F5F9FD] p-6">
                                <span className="text-sm font-bold text-[#0A3981]">Total Tagihan</span>
                                <span className="text-base font-bold text-[#0A3981] underline decoration-2 underline-offset-4">
                                    {formatRupiah(totalTagihan)}
                                </span>
                            </div>
                        </div>

                        {/* Riwayat semua transfer yang pernah diajukan untuk pendaftaran ini */}
                        {riwayatTransfer.length > 0 && (
                            <div className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                                <div className="border-b border-gray-100 p-6">
                                    <h2 className="text-[15px] font-semibold text-gray-900">Riwayat Transfer</h2>
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {riwayatTransfer.map((t, i) => (
                                        <div key={i} className="flex items-center justify-between gap-4 p-6">
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{formatRupiah(t.nominal_transfer)}</p>
                                                <p className="mt-0.5 text-xs text-gray-500">{t.tanggal_transfer}</p>
                                                {t.status === 'ditolak' && (
                                                    <p className="mt-1 text-xs text-red-600">
                                                        {t.catatan_verifikasi ?? 'Bukti transfer ditolak Staf PPDB.'}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-3">
                                                <a
                                                    href={t.bukti_transfer_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-xs font-medium text-[#1F509A] underline hover:text-[#0A3981]"
                                                >
                                                    Lihat Berkas
                                                </a>
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[t.status].className}`}>
                                                    {statusBadge[t.status].label}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* KOLOM KANAN - ringkasan pelunasan + tempat bertindak */}
                    {adaIsiKanan && (
                        <div className="space-y-6 lg:col-span-2">
                            {/* Ringkasan pelunasan - selalu tampil kalau udah pernah ada transfer, biar kelihatan progres cicilan */}
                            {totalTerbayar > 0 && (
                                <div className="overflow-hidden rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                                    <h2 className="mb-4 text-[15px] font-semibold text-gray-900">Progres Pelunasan</h2>
                                    <div className="relative mb-2 h-2 overflow-hidden rounded-full bg-gray-100">
                                        <div
                                            className="h-full rounded-full bg-green-500"
                                            style={{ width: `${Math.min(100, Math.round((totalTerbayar / totalTagihan) * 100))}%` }}
                                        />
                                        {/* Ambang minimal bayar: batas antara "masih bisa ditolak"
                                dan "sudah diterima, sisanya tinggal cicilan". */}
                                        {minimalBayar !== null && minimalBayar > 0 && minimalBayar < totalTagihan && (
                                            <span
                                                aria-hidden
                                                className="absolute top-0 h-full w-0.5 bg-[#0A3981]"
                                                style={{ left: `${(minimalBayar / totalTagihan) * 100}%` }}
                                            />
                                        )}
                                    </div>
                                    {minimalBayar !== null && minimalBayar > 0 && minimalBayar < totalTagihan && (
                                        <p className="mb-3 text-xs text-gray-500">
                                            Garis biru menandai minimal {formatRupiah(minimalBayar)} supaya pendaftaran diterima.
                                        </p>
                                    )}
                                    <div className="space-y-2 text-sm">
                                        <DetailRow label="Sudah Terverifikasi" value={formatRupiah(totalTerbayar)} />
                                        {!sudahPenuhiMinimal && kurangMinimal > 0 && (
                                            <DetailRow label="Kurang agar Diterima" value={formatRupiah(kurangMinimal)} />
                                        )}
                                        <DetailRow
                                            label="Sisa Tagihan"
                                            value={sisaTagihan > 0 ? formatRupiah(sisaTagihan) : 'Lunas'}
                                            highlight={sisaTagihan <= 0}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Sisa tagihan udah lunas - nggak ada form lagi */}
                            {tagihanTersedia && sisaTagihan <= 0 && totalTerbayar > 0 && (
                                <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                                    <span className="font-semibold text-green-800">Tagihan lunas. </span>
                                    Seluruh tagihan pendaftaran ini sudah terverifikasi, tidak perlu transfer lagi.
                                </div>
                            )}

                            {/* Masih ada sisa, tapi ada transfer lain yang masih diproses staf */}
                            {!pendaftaranDitolak && sisaTagihan > 0 && adaPending && !bisaBayar && (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                    <span className="font-semibold text-amber-800">Menunggu verifikasi. </span>
                                    Ada transfer yang masih diperiksa Staf PPDB. Kamu bisa kirim transfer susulan setelah transfer ini diverifikasi
                                    atau ditolak.
                                </div>
                            )}

                            {/* Masih ada sisa dan boleh transfer (baik cicilan pertama maupun susulan) */}
                            {bisaBayar && (
                                <>
                                    {transferTerakhirDitolak && (
                                        <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                            <span className="font-semibold text-red-800">Bukti transfer ditolak: </span>
                                            {riwayatTransfer[0].catatan_verifikasi ??
                                                'Staf PPDB menolak bukti transfer sebelumnya. Silakan unggah ulang.'}
                                        </div>
                                    )}

                                    <form
                                        onSubmit={submit}
                                        className="overflow-hidden rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]"
                                    >
                                        <h2 className="mb-5 text-[15px] font-semibold text-gray-900">
                                            {totalTerbayar > 0 ? 'Kirim Transfer Susulan' : 'Unggah Bukti Transfer'}
                                        </h2>

                                        <div className="mb-4">
                                            <label className="mb-1.5 block text-sm font-medium text-gray-700">Nominal Transfer</label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={data.nominal_transfer}
                                                onChange={(e) => setData('nominal_transfer', e.target.value)}
                                                onWheel={(e) => e.currentTarget.blur()}
                                                placeholder="Jumlah yang ditransfer"
                                                className="border-gray-200 bg-[#F5F9FD]"
                                            />
                                            {errors.nominal_transfer && <p className="mt-1 text-xs text-red-600">{errors.nominal_transfer}</p>}
                                            {!errors.nominal_transfer && Number(data.nominal_transfer) > sisaTagihan && (
                                                <p className="mt-1 text-xs text-amber-600">
                                                    Nominal ini lebih besar dari sisa tagihan ({formatRupiah(sisaTagihan)}). Tetap bisa dikirim, staf
                                                    yang akan menilai saat verifikasi.
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-4">
                                            <label className="mb-1.5 block text-sm font-medium text-gray-700">Tanggal Transfer</label>
                                            <Input
                                                type="date"
                                                value={data.tanggal_transfer}
                                                onChange={(e) => setData('tanggal_transfer', e.target.value)}
                                                className="border-gray-200 bg-[#F5F9FD]"
                                            />
                                            {errors.tanggal_transfer && <p className="mt-1 text-xs text-red-600">{errors.tanggal_transfer}</p>}
                                        </div>

                                        <div className="mb-5">
                                            <label className="mb-1.5 block text-sm font-medium text-gray-700">Bukti Transfer</label>
                                            <label
                                                htmlFor="bukti_transfer"
                                                className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-[#1F509A]/40 bg-[#F5F9FD] px-4 py-6 text-center transition-colors hover:bg-[#D4EBF8]/30"
                                            >
                                                <svg
                                                    width="20"
                                                    height="20"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="#1F509A"
                                                    strokeWidth={1.8}
                                                    className="mb-1"
                                                >
                                                    <path d="M12 16V4M12 4l-4 4M12 4l4 4" strokeLinecap="round" strokeLinejoin="round" />
                                                    <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3" strokeLinecap="round" strokeLinejoin="round" />
                                                </svg>
                                                <span className="text-xs font-medium text-[#1F509A]">
                                                    {data.bukti_transfer ? data.bukti_transfer.name : 'Klik untuk unggah bukti transfer'}
                                                </span>
                                                <span className="mt-1 text-xs text-gray-500">PDF/JPG/PNG, maksimal 2 MB</span>
                                            </label>
                                            <input
                                                id="bukti_transfer"
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                className="hidden"
                                                onChange={(e) => setData('bukti_transfer', e.target.files?.[0] ?? null)}
                                            />
                                            {errors.bukti_transfer && <p className="mt-1 text-xs text-red-600">{errors.bukti_transfer}</p>}
                                        </div>

                                        <Button type="submit" disabled={processing} className="w-full rounded-xl py-3.5 text-[15px] font-bold">
                                            Kirim Bukti Transfer
                                        </Button>
                                    </form>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </PageContainer>
        </AppLayout>
    );
}

function DetailRow({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-gray-500">{label}</span>
            <span className={highlight ? 'font-bold text-green-700' : 'font-medium text-gray-900'}>{value}</span>
        </div>
    );
}
