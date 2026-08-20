import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface DokumenItem {
    jenis: string;
    label: string;
    terunggah: boolean;
    nama_file: string | null;
}

interface UnggahBerkasProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        nama_pendaftar: string;
        status: string;
    };
    dokumenList: DokumenItem[];
}

export default function UnggahBerkas({ pendaftaran, dokumenList }: UnggahBerkasProps) {
    const [uploadingJenis, setUploadingJenis] = useState<string | null>(null);

    const semuaTerunggah = dokumenList.every((d) => d.terunggah);

    function handleFileChange(jenis: string, file: File | undefined) {
        if (!file) return;

        const formData = new FormData();
        formData.append('jenis_dokumen', jenis);
        formData.append('berkas', file);

        setUploadingJenis(jenis);
        router.post(route('wali-murid.unggah-berkas.store', pendaftaran.id), formData, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setUploadingJenis(null),
        });
    }

    function handleKirimBerkas() {
        router.post(route('wali-murid.unggah-berkas.submit', pendaftaran.id));
    }

    return (
        <AppLayout>
            <Head title="Unggah Berkas" />
            <PageHeader
                title="Unggah Berkas Persyaratan"
                subtitle={`${pendaftaran.nomor_pendaftaran} — ${pendaftaran.nama_pendaftar}`}
            />

            <div className="mx-auto max-w-3xl px-5 pb-20">
                {/* Stepper */}
                <div className="mb-8 flex items-center">
                    <Step label="Registrasi Akun" state="done" />
                    <StepLine />
                    <Step label="Formulir" state="done" />
                    <StepLine />
                    <Step label="Unggah Berkas" state="active" />
                    <StepLine />
                    <Step label="Pembayaran" state="pending" />
                </div>

                <div className="mb-6 rounded-2xl border border-[#D4EBF8] bg-[#F5F9FD] p-5 text-sm text-[#0A3981]">
                    Unggah dokumen berikut sesuai jalur pendaftaran. Format PDF/JPG/PNG, maksimal 2 MB per berkas.
                    Dokumen bertanda <b>Belum Diunggah</b> wajib dilengkapi sebelum berkas dapat dikirim untuk
                    diverifikasi Staf PPDB.
                </div>

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
                                    {doc.terunggah ? (
                                        <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-semibold text-green-700">
                                            Terunggah
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500">
                                            Belum Diunggah
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="w-64 shrink-0">
                                {doc.terunggah ? (
                                    <div className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                                        <span className="truncate text-sm font-medium text-green-800">{doc.nama_file}</span>
                                        <label
                                            htmlFor={`file-${doc.jenis}`}
                                            className="ml-2 shrink-0 cursor-pointer text-xs font-medium text-[#1F509A] underline"
                                        >
                                            Ganti
                                        </label>
                                    </div>
                                ) : (
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
                                )}
                                <input
                                    id={`file-${doc.jenis}`}
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    className="hidden"
                                    disabled={uploadingJenis !== null}
                                    onChange={(e) => handleFileChange(doc.jenis, e.target.files?.[0])}
                                />
                            </div>
                        </div>
                    ))}
                </div>

                <button
                    onClick={handleKirimBerkas}
                    disabled={!semuaTerunggah}
                    className="w-full rounded-xl bg-[#E38E49] py-3.5 text-[15px] font-bold text-white shadow-[0_4px_14px_-4px_rgba(227,142,73,0.5)] transition-colors hover:bg-[#d47f3a] disabled:cursor-not-allowed disabled:bg-gray-300 disabled:shadow-none"
                >
                    Kirim Berkas untuk Diverifikasi
                </button>
                {!semuaTerunggah && (
                    <p className="mt-2 text-center text-xs text-gray-500">
                        Lengkapi semua dokumen wajib di atas sebelum bisa mengirim.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}

function Step({ label, state }: { label: string; state: 'done' | 'active' | 'pending' }) {
    const circleClass =
        state === 'done'
            ? 'border-green-500 bg-green-500 text-white'
            : state === 'active'
              ? 'border-[#0A3981] bg-[#0A3981] text-white shadow-[0_0_0_4px_rgba(10,57,129,0.12)]'
              : 'border-gray-200 bg-white text-gray-300';

    return (
        <div className="flex items-center">
            <div className={`flex h-9 w-9 items-center justify-center rounded-full border-2 text-xs font-bold transition-all ${circleClass}`}>
                {state === 'done' ? '✓' : label[0]}
            </div>
            <span className={`ml-2 text-[13px] ${state === 'active' ? 'font-semibold text-[#0A3981]' : state === 'done' ? 'text-gray-500' : 'text-gray-300'}`}>
                {label}
            </span>
        </div>
    );
}

function StepLine() {
    return <div className="mx-3 h-0.5 w-14 bg-[#D4EBF8]" />;
}