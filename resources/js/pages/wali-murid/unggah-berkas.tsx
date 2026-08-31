import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import PageContainer from '@/components/page-container';
import AlurStepper from '@/components/alur-stepper';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface DokumenItem {
    jenis: string;
    label: string;
    terunggah: boolean;
    nama_file: string | null;
    url: string | null;
}

interface UnggahBerkasProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        nama_pendaftar: string;
        status: string;
    };
    dokumenList: DokumenItem[];
    bisaEdit: boolean;
}

export default function UnggahBerkas({ pendaftaran, dokumenList, bisaEdit }: UnggahBerkasProps) {
    const [uploadingJenis, setUploadingJenis] = useState<string | null>(null);
    const [baruTersimpan, setBaruTersimpan] = useState<string | null>(null);

    const semuaTerunggah = dokumenList.every((d) => d.terunggah);

    function handleFileChange(jenis: string, file: File | undefined) {
        if (!file) return;

        const formData = new FormData();
        formData.append('jenis_dokumen', jenis);
        formData.append('berkas', file);

        setUploadingJenis(jenis);
        router.post(route('wali-murid.pendaftaran.unggah-berkas.store', pendaftaran.id), formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setBaruTersimpan(jenis);
                setTimeout(() => setBaruTersimpan(null), 2000);
            },
            onFinish: () => setUploadingJenis(null),
        });
    }

    function handleKirimBerkas() {
        router.post(route('wali-murid.pendaftaran.unggah-berkas.submit', pendaftaran.id));
    }

    return (
        <AppLayout>
            <Head title="Unggah Berkas" />
            <PageHeader
                title="Unggah Berkas Persyaratan"
                subtitle={`${pendaftaran.nomor_pendaftaran} — ${pendaftaran.nama_pendaftar}`}
            />

            <PageContainer>
                <AlurStepper aktif="Unggah Berkas" />

                {!bisaEdit && (
                    <div className="mb-6 rounded-2xl border border-gray-200 bg-gray-100 p-5 text-sm text-gray-600">
                        Berkas pendaftaran ini sudah tidak bisa diubah lagi karena statusnya sudah lanjut ke tahap
                        berikutnya. Daftar di bawah cuma bisa dilihat, bukan diedit.
                    </div>
                )}

                {bisaEdit && (
                    <div className="mb-6 rounded-2xl border border-[#D4EBF8] bg-[#F5F9FD] p-5 text-sm text-[#0A3981]">
                        Unggah dokumen berikut sesuai jalur pendaftaran. Format PDF/JPG/PNG, maksimal 2 MB per berkas.
                        Dokumen bertanda <b>Belum Diunggah</b> wajib dilengkapi sebelum berkas dapat dikirim untuk
                        diverifikasi Staf PPDB.
                    </div>
                )}

                <div className="mb-6 overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                    {dokumenList.map((doc, index) => (
                        <div
                            key={doc.jenis}
                            className={
                                'flex items-center justify-between gap-6 p-6' +
                                (index !== dokumenList.length - 1 ? ' border-b border-gray-100' : '')
                            }
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <h3 className="text-[15px] font-semibold text-gray-900">{doc.label}</h3>
                                    {baruTersimpan === doc.jenis ? (
                                        <span className="animate-pulse rounded-full bg-green-500 px-2.5 py-0.5 text-xs font-semibold text-white">
                                            ✓ Tersimpan
                                        </span>
                                    ) : doc.terunggah ? (
                                        <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                            Terunggah
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">
                                            Belum Diunggah
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="w-64 shrink-0">
                                {doc.terunggah ? (
                                    <div className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                                        <a
                                            href={doc.url ?? '#'}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="truncate text-sm font-medium text-green-800 underline hover:text-green-900"
                                        >
                                            {doc.nama_file}
                                        </a>
                                        {bisaEdit && (
                                            <label
                                                htmlFor={`file-${doc.jenis}`}
                                                className="ml-2 shrink-0 cursor-pointer text-xs font-medium text-[#1F509A] underline"
                                            >
                                                Ganti
                                            </label>
                                        )}
                                    </div>
                                ) : bisaEdit ? (
                                    <label
                                        htmlFor={`file-${doc.jenis}`}
                                        className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-[#1F509A]/40 bg-[#F5F9FD] px-4 py-4 text-center transition-colors hover:bg-[#D4EBF8]/30"
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
                                            {uploadingJenis === doc.jenis ? 'Mengunggah...' : 'Klik untuk unggah'}
                                        </span>
                                    </label>
                                ) : (
                                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-4 text-center">
                                        <span className="text-xs font-medium text-gray-500">Tidak diunggah</span>
                                    </div>
                                )}
                                {bisaEdit && (
                                    <input
                                        id={`file-${doc.jenis}`}
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        className="hidden"
                                        disabled={uploadingJenis !== null}
                                        onChange={(e) => handleFileChange(doc.jenis, e.target.files?.[0])}
                                    />
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {bisaEdit && pendaftaran.status === 'draft' ? (
                    <>
                        <Button
                            onClick={handleKirimBerkas}
                            disabled={!semuaTerunggah}
                            className="w-full rounded-xl py-3.5 text-[15px] font-bold"
                        >
                            Kirim Berkas untuk Diverifikasi
                        </Button>
                        {!semuaTerunggah && (
                            <p className="mt-2 text-center text-xs text-gray-500">
                                Lengkapi semua dokumen wajib di atas sebelum bisa mengirim.
                            </p>
                        )}
                    </>
                ) : (
                    <Button
                        asChild
                        variant="outline"
                        className="w-full rounded-xl border-[#1F509A]/40 py-3.5 text-[15px] font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        <Link href={route('wali-murid.pendaftaran.index', { expand: pendaftaran.id })}>Selesai</Link>
                    </Button>
                )}
            </PageContainer>
        </AppLayout>
    );
}
