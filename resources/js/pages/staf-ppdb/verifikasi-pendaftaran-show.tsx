import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';
import { useState } from 'react';

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
    nama_file: string | null;
}

interface PeriksaPendaftaranProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        status: string;
        kategori: string;
        gelombang: string;
        nama_pendaftar: string;
        nik: string | null;
        tempat_lahir: string;
        tanggal_lahir: string;
        jenis_kelamin: string;
        alamat: string;
        nama_saudara: string | null;
        nama_orang_tua_guru: string | null;
        catatan_verifikasi: string | null;
        akun_pendaftar: string;
    };
    waliMurid: WaliMuridItem[];
    berkas: BerkasItem[];
}

function Baris({ label, nilai }: { label: string; nilai: string | null }) {
    return (
        <div className="flex flex-col gap-0.5 border-b border-gray-100 py-2.5 last:border-b-0 sm:flex-row sm:gap-4">
            <span className="w-56 shrink-0 text-sm text-gray-500">{label}</span>
            <span className="text-sm text-gray-900">{nilai || '—'}</span>
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

export default function VerifikasiPendaftaranShow({ pendaftaran, waliMurid, berkas }: PeriksaPendaftaranProps) {
    const berkasKurang = berkas.filter((b) => !b.terunggah).length;

    // Tombol aksi hanya ada selama pendaftaran memang sedang menunggu diperiksa.
    // Ini cuma penjaga tampilan; penjaga sebenarnya ada di controller.
    const menungguDiperiksa = pendaftaran.status === 'diajukan';

    // Kotak catatan disembunyikan sampai staf memilih "Minta Perbaikan", supaya
    // keputusan menyetujui tidak berdampingan dengan kolom isian yang mengganggu.
    const [formPerbaikanTampil, setFormPerbaikanTampil] = useState(false);

    const setuju = useForm({});
    const perbaikan = useForm({ catatan_verifikasi: '' });

    return (
        <AppLayout>
            <Head title={`Periksa Pendaftaran — ${pendaftaran.nama_pendaftar}`} />
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
                    <Link href={route('staf-ppdb.verifikasi-pendaftaran.index')} aria-label="Kembali ke antrian" title="Kembali ke antrian">
                        <ArrowLeft size={18} strokeWidth={2} />
                    </Link>
                </Button>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom kiri: data yang diperiksa */}
                    <div className="space-y-6 lg:col-span-2">
                        <Kartu judul="Data Calon Peserta Didik">
                            <Baris label="Nama Lengkap" nilai={pendaftaran.nama_pendaftar} />
                            <Baris label="NIK" nilai={pendaftaran.nik} />
                            <Baris label="Tempat, Tanggal Lahir" nilai={`${pendaftaran.tempat_lahir}, ${pendaftaran.tanggal_lahir}`} />
                            <Baris label="Jenis Kelamin" nilai={pendaftaran.jenis_kelamin} />
                            <Baris label="Alamat" nilai={pendaftaran.alamat} />
                            <Baris label="Gelombang" nilai={pendaftaran.gelombang} />
                        </Kartu>

                        {/* Dua kolom ini yang mendasari klaim kategori, jadi staf perlu
                            melihatnya berdampingan dengan kategori yang dipilih wali. */}
                        {(pendaftaran.nama_saudara || pendaftaran.nama_orang_tua_guru) && (
                            <Kartu judul={`Pendukung Klaim Kategori ${pendaftaran.kategori}`}>
                                <Baris label="Nama Saudara di Sekolah Ini" nilai={pendaftaran.nama_saudara} />
                                <Baris label="Nama Orang Tua yang Mengajar" nilai={pendaftaran.nama_orang_tua_guru} />
                            </Kartu>
                        )}

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
                    </div>

                    {/* Kolom kanan: berkas, yang paling sering dibuka staf */}
                    <div className="space-y-6">
                        <Kartu judul="Berkas Persyaratan">
                            <p className="mb-4 text-sm text-gray-500">
                                {berkasKurang === 0 ? 'Semua berkas wajib sudah diunggah.' : `${berkasKurang} berkas wajib belum diunggah.`}
                            </p>

                            <div className="space-y-2">
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
                                                <a
                                                    href={b.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-xs text-[#1F509A] underline"
                                                >
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

                        {menungguDiperiksa && (
                            <Kartu judul="Keputusan Verifikasi">
                                {!formPerbaikanTampil ? (
                                    <>
                                        <p className="mb-4 text-sm text-gray-500">
                                            Setujui kalau formulir dan berkasnya sudah benar. Setelah disetujui, wali bisa mulai membayar.
                                        </p>

                                        <Button
                                            className="w-full rounded-xl font-bold"
                                            disabled={setuju.processing}
                                            onClick={() => setuju.post(route('staf-ppdb.verifikasi-pendaftaran.setujui', pendaftaran.id))}
                                        >
                                            {setuju.processing ? 'Memproses...' : 'Setujui Pendaftaran'}
                                        </Button>

                                        <Button
                                            variant="outline"
                                            className="mt-2 w-full rounded-xl border-[#1F509A]/40 bg-white font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                                            onClick={() => setFormPerbaikanTampil(true)}
                                        >
                                            Minta Perbaikan
                                        </Button>
                                    </>
                                ) : (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            perbaikan.post(route('staf-ppdb.verifikasi-pendaftaran.minta-perbaikan', pendaftaran.id));
                                        }}
                                    >
                                        <p className="mb-3 text-sm text-gray-500">
                                            Sebutkan bagian mana yang perlu dibetulkan. Kalimat ini yang dibaca wali, jadi tulis sejelas mungkin.
                                        </p>

                                        <Textarea
                                            value={perbaikan.data.catatan_verifikasi}
                                            onChange={(e) => perbaikan.setData('catatan_verifikasi', e.target.value)}
                                            rows={5}
                                            placeholder="Contoh: Foto Kartu Keluarga buram, nomor KK tidak terbaca. Mohon unggah ulang."
                                            className="border-gray-200 bg-[#F5F9FD] text-sm"
                                        />

                                        {perbaikan.errors.catatan_verifikasi && (
                                            <p className="mt-1.5 text-xs text-red-600">{perbaikan.errors.catatan_verifikasi}</p>
                                        )}

                                        <Button type="submit" className="mt-3 w-full rounded-xl font-bold" disabled={perbaikan.processing}>
                                            {perbaikan.processing ? 'Mengirim...' : 'Kirim Permintaan Perbaikan'}
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="mt-2 w-full rounded-xl border-gray-300 bg-white font-bold text-gray-600 hover:bg-gray-50"
                                            onClick={() => {
                                                setFormPerbaikanTampil(false);
                                                perbaikan.reset();
                                                perbaikan.clearErrors();
                                            }}
                                        >
                                            Batal
                                        </Button>
                                    </form>
                                )}
                            </Kartu>
                        )}

                        <Kartu judul="Akun Pendaftar">
                            <p className="text-sm text-gray-700">{pendaftaran.akun_pendaftar}</p>
                        </Kartu>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
