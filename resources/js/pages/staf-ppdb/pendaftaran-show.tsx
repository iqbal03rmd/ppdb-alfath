import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';

interface WaliMuridItem {
    nama: string;
    nik: string;
    hubungan: string;
    telepon: string;
}

interface BerkasItem {
    jenis: string;
    label: string;
    terunggah: boolean;
    url: string | null;
}

interface RincianTagihanItem {
    nama: string;
    nominal: number;
}

interface TransferItem {
    id: number;
    nominal_transfer: number;
    tanggal_transfer: string;
    status: string;
    catatan_verifikasi: string | null;
}

interface RingkasanPembayaran {
    statusPelunasan: string;
    totalTagihan: number;
    totalTerbayar: number;
    sisaTagihan: number;
    minimalBayar: number | null;
    kurangMinimal: number;
    sudahPenuhiMinimal: boolean;
    menunggak: boolean;
    jatuhTempoMinimal: string | null;
    tanggalPelunasanCicilan: string | null;
}

interface PendaftaranShowProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        status: string;
        kategori: string;
        gelombang: string;
        tahun_ajaran: string;
        nama_pendaftar: string;
        nik: string | null;
        tempat_lahir: string;
        tanggal_lahir: string;
        jenis_kelamin: string;
        agama: string | null;
        alamat: string;
        nama_saudara: string | null;
        nama_orang_tua_guru: string | null;
        catatan_verifikasi: string | null;
        diperiksa_oleh: string | null;
        akun_pendaftar: string;
        tanggal_daftar: string;
    };
    waliMurid: WaliMuridItem[];
    berkas: BerkasItem[];
    ringkasanPembayaran: RingkasanPembayaran | null;
    sebabTanpaTagihan: string | null;
    rincianTagihan: RincianTagihanItem[];
    riwayatTransfer: TransferItem[];
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-teal-100 text-teal-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

const transferBadge: Record<string, { label: string; className: string }> = {
    menunggu_verifikasi: { label: 'Menunggu diperiksa', className: 'bg-amber-100 text-amber-700' },
    terverifikasi: { label: 'Sah', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

function Baris({ label, nilai }: { label: string; nilai: string | null }) {
    return (
        <div className="flex flex-col gap-0.5 border-b border-gray-100 py-2.5 last:border-b-0 sm:flex-row sm:gap-4">
            <span className="w-56 shrink-0 text-sm text-gray-500">{label}</span>
            <span className="text-sm text-gray-900">{nilai || '—'}</span>
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

function Kartu({ judul, children }: { judul: string; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <h2 className="mb-3 text-[15px] font-semibold text-gray-900">{judul}</h2>
            {children}
        </div>
    );
}

export default function PendaftaranShow({
    pendaftaran,
    waliMurid,
    berkas,
    ringkasanPembayaran,
    sebabTanpaTagihan,
    rincianTagihan,
    riwayatTransfer,
}: PendaftaranShowProps) {
    const badge = statusBadge[pendaftaran.status] ?? statusBadge.draft;
    const berkasKurang = berkas.filter((b) => !b.terunggah).length;

    return (
        <AppLayout>
            <Head title={`${pendaftaran.nama_pendaftar} — Detail Pendaftaran`} />
            <PageHeader
                title={pendaftaran.nama_pendaftar}
                subtitle={`${pendaftaran.nomor_pendaftaran} · ${pendaftaran.kategori} · ${pendaftaran.gelombang} ${pendaftaran.tahun_ajaran}`}
                wide
            />

            <PageContainer wide>
                <div className="mb-5 flex items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        <Link href={route('staf-ppdb.pendaftaran.index')} aria-label="Kembali ke daftar pendaftaran" title="Kembali ke daftar pendaftaran">
                            <ArrowLeft size={18} strokeWidth={2} />
                        </Link>
                    </Button>

                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${badge.className}`}>{badge.label}</span>
                    <span className="text-xs text-gray-500">Didaftarkan {pendaftaran.tanggal_daftar}</span>
                </div>

                {/* Halaman ini tidak mengubah status. Kalau pendaftarannya memang
                    sedang menunggu diperiksa, staf diantar ke tempat keputusannya
                    diambil - bukan diberi tombol kedua di sini. */}
                {pendaftaran.status === 'diajukan' && (
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#D4EBF8] p-4">
                        <p className="text-sm text-[#0A3981]">Pendaftaran ini masih menunggu diperiksa.</p>
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-xl border-[#1F509A]/40 bg-white font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                        >
                            <Link href={route('staf-ppdb.verifikasi-pendaftaran.show', pendaftaran.id)}>Buka Halaman Verifikasi</Link>
                        </Button>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom kiri: identitas dan berkas */}
                    <div className="space-y-6 lg:col-span-2">
                        <Kartu judul="Data Calon Peserta Didik">
                            <Baris label="Nama Lengkap" nilai={pendaftaran.nama_pendaftar} />
                            <Baris label="NIK" nilai={pendaftaran.nik} />
                            <Baris label="Tempat, Tanggal Lahir" nilai={`${pendaftaran.tempat_lahir}, ${pendaftaran.tanggal_lahir}`} />
                            <Baris label="Jenis Kelamin" nilai={pendaftaran.jenis_kelamin} />
                            <Baris label="Agama" nilai={pendaftaran.agama} />
                            <Baris label="Alamat" nilai={pendaftaran.alamat} />
                            <Baris label="Gelombang" nilai={`${pendaftaran.gelombang} · ${pendaftaran.tahun_ajaran}`} />
                        </Kartu>

                        {(pendaftaran.nama_saudara || pendaftaran.nama_orang_tua_guru) && (
                            <Kartu judul={`Pendukung Klaim Kategori ${pendaftaran.kategori}`}>
                                <Baris label="Nama Saudara di Sekolah Ini" nilai={pendaftaran.nama_saudara} />
                                <Baris label="Nama Orang Tua yang Mengajar" nilai={pendaftaran.nama_orang_tua_guru} />
                            </Kartu>
                        )}

                        {/* Nomor telepon wali - alasan utama halaman ini dibuka
                            waktu staf perlu menghubungi balik. */}
                        <Kartu judul={`Data Wali Murid (${waliMurid.length})`}>
                            {waliMurid.length === 0 ? (
                                <p className="text-sm text-gray-500">Wali murid belum diisi.</p>
                            ) : (
                                waliMurid.map((w, i) => (
                                    <div key={i} className="border-b border-gray-100 py-3 last:border-b-0">
                                        <p className="text-sm font-medium text-gray-900">
                                            {w.nama} <span className="font-normal text-gray-500">· {w.hubungan}</span>
                                        </p>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            NIK {w.nik} · {w.telepon}
                                        </p>
                                    </div>
                                ))
                            )}
                        </Kartu>

                        <Kartu judul="Berkas Persyaratan">
                            <p className="mb-4 text-sm text-gray-500">
                                {berkasKurang === 0 ? 'Semua berkas wajib sudah diunggah.' : `${berkasKurang} berkas wajib belum diunggah.`}
                            </p>

                            <div className="grid gap-2 sm:grid-cols-2">
                                {berkas.map((b) => (
                                    <div key={b.jenis} className="flex items-start gap-2.5 rounded-xl bg-[#F5F9FD] p-3">
                                        <span
                                            className={
                                                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full ' +
                                                (b.terunggah ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')
                                            }
                                        >
                                            {b.terunggah ? <Check size={13} strokeWidth={2.5} /> : <X size={13} strokeWidth={2.5} />}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-gray-900">{b.label}</p>
                                            {b.terunggah && b.url ? (
                                                <a href={b.url} target="_blank" rel="noopener noreferrer" className="text-xs text-[#1F509A] underline">
                                                    Buka berkas
                                                </a>
                                            ) : (
                                                <p className="text-xs text-gray-500">Belum diunggah</p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Kartu>
                    </div>

                    {/* Kolom kanan: uang - bagian yang tidak ada di halaman verifikasi */}
                    <div className="space-y-6">
                        <Kartu judul="Ringkasan Pembayaran">
                            {ringkasanPembayaran === null ? (
                                <p className="text-sm text-gray-500">{sebabTanpaTagihan}</p>
                            ) : (
                                <>
                                    <BarisUang label="Total tagihan" nominal={ringkasanPembayaran.totalTagihan} />
                                    <BarisUang label="Sudah dibayar" nominal={ringkasanPembayaran.totalTerbayar} />
                                    <BarisUang label="Sisa tagihan" nominal={ringkasanPembayaran.sisaTagihan} tebal />

                                    {/* Minimal bayar - syarat diterima, bukan syarat lunas.
                                        Dua hal ini paling sering tertukar saat menjelaskan
                                        ke wali, jadi kalimatnya dieja lengkap.

                                        Tiga keadaan, bukan dua. Yang sudah lunas TIDAK boleh
                                        ikut kalimat "sisanya boleh dicicil": buat wali yang
                                        membayar penuh sekaligus, kalimat itu menjanjikan sisa
                                        yang sebetulnya sudah tidak ada. */}
                                    {ringkasanPembayaran.minimalBayar !== null && (
                                        <div className="mt-4 rounded-xl bg-[#F5F9FD] p-3.5">
                                            {ringkasanPembayaran.sisaTagihan === 0 ? (
                                                <p className="text-sm text-gray-700">
                                                    Seluruh tagihan sudah terbayar. Tidak ada sisa yang perlu ditagih lagi.
                                                </p>
                                            ) : ringkasanPembayaran.sudahPenuhiMinimal ? (
                                                <p className="text-sm text-gray-700">
                                                    Minimal bayar {formatRupiah(ringkasanPembayaran.minimalBayar)} sudah terpenuhi. Sisanya boleh
                                                    dicicil dan tidak membatalkan penerimaan.
                                                </p>
                                            ) : (
                                                <p className="text-sm text-gray-700">
                                                    Kurang <span className="font-bold">{formatRupiah(ringkasanPembayaran.kurangMinimal)}</span> lagi
                                                    dari minimal bayar {formatRupiah(ringkasanPembayaran.minimalBayar)} supaya bisa diterima.
                                                </p>
                                            )}

                                            {ringkasanPembayaran.jatuhTempoMinimal && (
                                                <p className="mt-1.5 text-xs text-gray-500">
                                                    Jatuh tempo {ringkasanPembayaran.jatuhTempoMinimal}
                                                </p>
                                            )}

                                            {/* Tanggal cicilan sengaja TIDAK disebut jatuh tempo -
                                                lewatnya tidak melepas kursi dan tidak berakibat
                                                apa pun di sistem. */}
                                            {ringkasanPembayaran.tanggalPelunasanCicilan && (
                                                <p className="mt-1.5 text-xs text-gray-500">
                                                    Sisanya boleh dicicil sampai {ringkasanPembayaran.tanggalPelunasanCicilan}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {ringkasanPembayaran.menunggak && (
                                        <p className="mt-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-800">
                                            Sudah lewat tanggal cicilan dan sisanya belum lunas. Kursinya tetap aman — penagihannya diteruskan di
                                            luar sistem.
                                        </p>
                                    )}
                                </>
                            )}
                        </Kartu>

                        {rincianTagihan.length > 0 && (
                            <Kartu judul="Rincian Tagihan">
                                {rincianTagihan.map((r, i) => (
                                    <BarisUang key={i} label={r.nama} nominal={r.nominal} />
                                ))}
                            </Kartu>
                        )}

                        <Kartu judul={`Riwayat Transfer (${riwayatTransfer.length})`}>
                            {riwayatTransfer.length === 0 ? (
                                <p className="text-sm text-gray-500">Belum ada bukti transfer yang diunggah wali.</p>
                            ) : (
                                riwayatTransfer.map((t) => {
                                    const tb = transferBadge[t.status] ?? transferBadge.menunggu_verifikasi;

                                    return (
                                        <div key={t.id} className="border-b border-gray-100 py-3 last:border-b-0">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <span className="text-sm font-semibold text-gray-900">{formatRupiah(t.nominal_transfer)}</span>
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${tb.className}`}>{tb.label}</span>
                                            </div>
                                            <p className="mt-0.5 text-xs text-gray-500">{t.tanggal_transfer}</p>
                                            {t.catatan_verifikasi && <p className="mt-1 text-xs text-gray-500">{t.catatan_verifikasi}</p>}
                                            <Link
                                                href={route('staf-ppdb.verifikasi-pembayaran.show', t.id)}
                                                className="mt-1 inline-block text-xs text-[#1F509A] underline"
                                            >
                                                Lihat bukti transfer
                                            </Link>
                                        </div>
                                    );
                                })
                            )}
                        </Kartu>

                        {pendaftaran.catatan_verifikasi && (
                            <Kartu judul="Catatan Verifikasi">
                                <p className="text-sm text-gray-700">{pendaftaran.catatan_verifikasi}</p>
                            </Kartu>
                        )}

                        <Kartu judul="Akun Pendaftar">
                            <p className="text-sm text-gray-700">{pendaftaran.akun_pendaftar}</p>
                            <p className="mt-1 text-xs text-gray-500">
                                Diperiksa oleh {pendaftaran.diperiksa_oleh ?? 'belum ada'}
                            </p>
                        </Kartu>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
