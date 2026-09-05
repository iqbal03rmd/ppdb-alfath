import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';

interface TransferLain {
    nominal_transfer: number;
    tanggal_transfer: string;
    status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak';
}

interface VerifikasiPembayaranShowProps {
    pembayaran: {
        id: number;
        status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak';
        nominal_transfer: number;
        tanggal_transfer: string;
        diunggah_pada: string;
        catatan_verifikasi: string | null;
        diperiksa_oleh: string | null;
        bukti_url: string;
        bukti_gambar: boolean;
    };
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        nama_pendaftar: string;
        kategori: string;
        status: string;
    };
    ringkasan: {
        total_tagihan: number;
        minimal_bayar: number;
        sudah_terverifikasi: number;
        setelah_disahkan: number;
        sisa_setelah_disahkan: number;
        capai_minimal_setelah_disahkan: boolean;
        sudah_capai_minimal_sebelumnya: boolean;
        masih_menunggu: boolean;
        sudah_disahkan: boolean;
        akan_menurunkan_status: boolean;
        transfer_menunggu_lain: number;
        nominal_menunggu_lain: number;
    };
    transferLain: TransferLain[];
}

const statusBadge: Record<string, { label: string; className: string }> = {
    menunggu_verifikasi: { label: 'Menunggu Verifikasi', className: 'bg-amber-100 text-amber-700' },
    terverifikasi: { label: 'Terverifikasi', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

function Kartu({ judul, children }: { judul: string; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <h2 className="mb-3 text-[15px] font-semibold text-gray-900">{judul}</h2>
            {children}
        </div>
    );
}

function Baris({ label, nilai, tebal = false }: { label: string; nilai: string; tebal?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2 last:border-b-0">
            <span className="text-sm text-gray-500">{label}</span>
            <span className={'text-sm ' + (tebal ? 'font-semibold text-[#0A3981]' : 'text-gray-900')}>{nilai}</span>
        </div>
    );
}

export default function VerifikasiPembayaranShow({ pembayaran, pendaftaran, ringkasan, transferLain }: VerifikasiPembayaranShowProps) {
    const badge = statusBadge[pembayaran.status];

    // Transfer ini yang akan membuat pendaftarannya diterima: masih menunggu,
    // sebelumnya belum menyentuh minimal, sesudah disahkan menyentuh.
    const akanMembuatDiterima = ringkasan.masih_menunggu && !ringkasan.sudah_capai_minimal_sebelumnya && ringkasan.capai_minimal_setelah_disahkan;

    // Transfer yang terlanjur disahkan masih boleh dicabut - itu jalur pembatalan
    // verifikasi. Yang sudah ditolak tidak punya aksi lagi.
    const bolehDisahkan = pembayaran.status === 'menunggu_verifikasi';
    const bolehDitolak = pembayaran.status === 'menunggu_verifikasi' || pembayaran.status === 'terverifikasi';
    const membatalkanPengesahan = pembayaran.status === 'terverifikasi';

    const [formTolakTampil, setFormTolakTampil] = useState(false);

    const sah = useForm({});
    const tolak = useForm({ catatan_verifikasi: '' });

    return (
        <AppLayout>
            <Head title={`Periksa Transfer — ${pendaftaran.nama_pendaftar}`} />
            <PageHeader title={pendaftaran.nama_pendaftar} subtitle={`${pendaftaran.nomor_pendaftaran} · ${pendaftaran.kategori}`} wide />

            <PageContainer wide>
                <Button
                    asChild
                    variant="outline"
                    size="icon"
                    className="mb-5 rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                >
                    {/* Ikon saja - tujuannya sudah jelas dari posisinya di pojok
                        kiri atas. aria-label & title tetap diisi supaya pembaca
                        layar dan tooltip tetap menyebutkan tujuannya. */}
                    <Link href={route('staf-ppdb.verifikasi-pembayaran.index')} aria-label="Kembali ke antrian" title="Kembali ke antrian">
                        <ArrowLeft size={18} strokeWidth={2} />
                    </Link>
                </Button>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Bukti transfer - yang paling utama diperiksa, jadi paling lebar */}
                    <div className="lg:col-span-2">
                        <Kartu judul="Bukti Transfer">
                            <div className="mb-4 flex flex-wrap items-center gap-3">
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge.className}`}>{badge.label}</span>
                                <span className="text-xs text-gray-500">Diunggah {pembayaran.diunggah_pada}</span>
                                {pembayaran.diperiksa_oleh && <span className="text-xs text-gray-500">· Diperiksa {pembayaran.diperiksa_oleh}</span>}
                            </div>

                            {pembayaran.bukti_gambar ? (
                                <a href={pembayaran.bukti_url} target="_blank" rel="noopener noreferrer" className="block">
                                    {/* Tingginya dibatasi, bukan lebarnya: bukti transfer
                                        biasanya tangkapan layar HP yang menjulang tinggi,
                                        dan kalau dibiarkan selebar kolom hasilnya kebesaran.
                                        Ubah angka max-h di bawah kalau mau lain. */}
                                    <img
                                        src={pembayaran.bukti_url}
                                        alt="Bukti transfer"
                                        className="mx-auto max-h-[420px] w-auto rounded-xl border border-gray-200 bg-[#F5F9FD] object-contain"
                                    />
                                    <p className="mt-2 text-center text-xs text-gray-500">Klik gambar untuk membuka ukuran penuh</p>
                                </a>
                            ) : (
                                <a
                                    href={pembayaran.bukti_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block rounded-xl border border-gray-200 bg-[#F5F9FD] p-8 text-center text-sm text-[#1F509A] underline"
                                >
                                    Buka bukti transfer (PDF)
                                </a>
                            )}

                            <div className="mt-4">
                                <Baris label="Nominal yang ditulis wali" nilai={formatRupiah(pembayaran.nominal_transfer)} tebal />
                                <Baris label="Tanggal transfer" nilai={pembayaran.tanggal_transfer} />
                            </div>

                            {pembayaran.catatan_verifikasi && (
                                <p className="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700">
                                    Catatan penolakan sebelumnya: {pembayaran.catatan_verifikasi}
                                </p>
                            )}
                        </Kartu>
                    </div>

                    {/* Konteks angka - supaya staf tahu transfer ini membawa ke mana */}
                    <div className="space-y-6">
                        <Kartu judul="Posisi Pembayaran">
                            <Baris label="Total tagihan" nilai={formatRupiah(ringkasan.total_tagihan)} />
                            <Baris label="Minimal bayar" nilai={formatRupiah(ringkasan.minimal_bayar)} />
                            <Baris label="Sudah terverifikasi" nilai={formatRupiah(ringkasan.sudah_terverifikasi)} />

                            {/* Proyeksi hanya berlaku selama transfernya belum diputuskan.
                                Kalau sudah, angkanya bukan ramalan lagi - itu keadaan
                                sekarang, dan menampilkannya sebagai "kalau disahkan"
                                cuma membingungkan. */}
                            {ringkasan.masih_menunggu && (
                                <Baris label="Kalau transfer ini disahkan" nilai={formatRupiah(ringkasan.setelah_disahkan)} tebal />
                            )}

                            <Baris
                                label={ringkasan.masih_menunggu ? 'Sisa setelah disahkan' : 'Sisa tagihan'}
                                nilai={ringkasan.sisa_setelah_disahkan > 0 ? formatRupiah(ringkasan.sisa_setelah_disahkan) : 'Lunas'}
                            />
                        </Kartu>

                        {/* Peringatan paling penting di halaman ini: staf harus tahu
                            akibat pengesahan SEBELUM menekan tombolnya. */}
                        {akanMembuatDiterima && (
                            <div className="rounded-2xl border border-green-200 bg-green-50 p-5">
                                <p className="text-sm text-green-800">
                                    <b>Mengesahkan transfer ini membuat pendaftaran otomatis diterima.</b> Pembayaran akan menyentuh minimal{' '}
                                    {formatRupiah(ringkasan.minimal_bayar)}
                                    {/* Wali yang membayar penuh sekaligus tidak punya sisa untuk
                                        dicicil. Menjanjikannya bikin staf salah menjelaskan ke
                                        wali yang justru sudah tidak berutang apa-apa. */}
                                    {ringkasan.sisa_setelah_disahkan > 0 ? ', dan sisanya boleh dicicil.' : ', sekaligus melunasi seluruh tagihan.'}
                                </p>
                            </div>
                        )}

                        {(bolehDisahkan || bolehDitolak) && (
                            <Kartu judul={membatalkanPengesahan ? 'Batalkan Pengesahan' : 'Keputusan'}>
                                {!formTolakTampil ? (
                                    <>
                                        {membatalkanPengesahan ? (
                                            <div className="mb-4 space-y-2">
                                                {/* Bukan "bisa turun" lagi, tapi menyebut keadaan
                                                    pendaftaran ini sebenarnya. */}
                                                <p
                                                    className={
                                                        'rounded-lg p-3 text-sm ' +
                                                        (ringkasan.akan_menurunkan_status
                                                            ? 'bg-amber-50 text-amber-800'
                                                            : 'bg-[#F5F9FD] text-gray-600')
                                                    }
                                                >
                                                    {ringkasan.akan_menurunkan_status ? (
                                                        <>
                                                            <b>Membatalkan ini menurunkan status pendaftaran</b> dari &quot;diterima&quot; jadi
                                                            &quot;diverifikasi&quot;.
                                                        </>
                                                    ) : (
                                                        <>
                                                            Membatalkan ini tidak mengubah status pendaftaran — pembayaran lain masih menutupi minimal
                                                            bayar.
                                                        </>
                                                    )}
                                                </p>

                                                {ringkasan.transfer_menunggu_lain > 0 && (
                                                    <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                                        Wali sudah mengirim {ringkasan.transfer_menunggu_lain} transfer lain sesudah ini (
                                                        {formatRupiah(ringkasan.nominal_menunggu_lain)}, menunggu diperiksa).
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mb-4 text-sm text-gray-500">
                                                Sahkan kalau bukti dan nominalnya cocok. Kalau tidak, tolak sambil menyebutkan alasannya.
                                            </p>
                                        )}

                                        {bolehDisahkan && (
                                            <Button
                                                className="w-full rounded-xl font-bold"
                                                disabled={sah.processing}
                                                onClick={() => sah.post(route('staf-ppdb.verifikasi-pembayaran.sahkan', pembayaran.id))}
                                            >
                                                {sah.processing ? 'Memproses...' : 'Sahkan Transfer'}
                                            </Button>
                                        )}

                                        <Button
                                            variant="outline"
                                            className={
                                                'w-full rounded-xl border-red-300 bg-white font-bold text-red-700 hover:bg-red-50 hover:text-red-800 ' +
                                                (bolehDisahkan ? 'mt-2' : '')
                                            }
                                            onClick={() => setFormTolakTampil(true)}
                                        >
                                            {membatalkanPengesahan ? 'Batalkan Pengesahan' : 'Tolak Transfer'}
                                        </Button>
                                    </>
                                ) : (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            tolak.post(route('staf-ppdb.verifikasi-pembayaran.tolak', pembayaran.id));
                                        }}
                                    >
                                        <p className="mb-3 text-sm text-gray-500">
                                            Sebutkan alasannya. Kalimat ini yang dibaca wali saat diminta mengunggah ulang.
                                        </p>

                                        <Textarea
                                            value={tolak.data.catatan_verifikasi}
                                            onChange={(e) => tolak.setData('catatan_verifikasi', e.target.value)}
                                            rows={4}
                                            placeholder="Contoh: Nominal pada bukti transfer tidak sesuai dengan yang diisi."
                                            className="border-gray-200 bg-[#F5F9FD] text-sm"
                                        />

                                        {tolak.errors.catatan_verifikasi && (
                                            <p className="mt-1.5 text-xs text-red-600">{tolak.errors.catatan_verifikasi}</p>
                                        )}

                                        <Button type="submit" className="mt-3 w-full rounded-xl font-bold" disabled={tolak.processing}>
                                            {tolak.processing ? 'Mengirim...' : membatalkanPengesahan ? 'Batalkan Pengesahan' : 'Tolak Transfer'}
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="mt-2 w-full rounded-xl border-gray-300 bg-white font-bold text-gray-600 hover:bg-gray-50"
                                            onClick={() => {
                                                setFormTolakTampil(false);
                                                tolak.reset();
                                                tolak.clearErrors();
                                            }}
                                        >
                                            Batal
                                        </Button>
                                    </form>
                                )}
                            </Kartu>
                        )}

                        <Kartu judul={`Transfer Lain (${transferLain.length})`}>
                            {transferLain.length === 0 ? (
                                <p className="text-sm text-gray-500">Belum ada transfer lain untuk pendaftaran ini.</p>
                            ) : (
                                transferLain.map((t, i) => (
                                    <div key={i} className="flex items-center justify-between gap-3 border-b border-gray-100 py-2.5 last:border-b-0">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{formatRupiah(t.nominal_transfer)}</p>
                                            <p className="text-xs text-gray-500">{t.tanggal_transfer}</p>
                                        </div>
                                        <span
                                            className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[t.status].className}`}
                                        >
                                            {statusBadge[t.status].label}
                                        </span>
                                    </div>
                                ))
                            )}
                        </Kartu>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
