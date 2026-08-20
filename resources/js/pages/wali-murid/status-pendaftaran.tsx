import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import { Head } from '@inertiajs/react';

interface StatusPendaftaranProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        nama_pendaftar: string;
        status: string;
    };
}

export default function StatusPendaftaran({ pendaftaran }: StatusPendaftaranProps) {
    return (
        <AppLayout>
            <Head title="Status Pendaftaran" />
            <PageHeader title="Status Pendaftaran" subtitle={`${pendaftaran.nomor_pendaftaran} — ${pendaftaran.nama_pendaftar}`} />

            <div className="mx-auto max-w-3xl px-5 pb-20">
                <div className="rounded-2xl bg-white p-8 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                    <p className="text-sm text-gray-500">Halaman ini placeholder — isi aslinya (timeline status, dst) menyusul.</p>
                    <p className="mt-2 text-sm text-gray-700">
                        Status saat ini: <b className="text-[#0A3981]">{pendaftaran.status}</b>
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}