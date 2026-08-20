import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import { Head } from '@inertiajs/react';

export default function PembayaranIndex() {
    return (
        <AppLayout>
            <Head title="Pembayaran" />
            <PageHeader title="Pembayaran" subtitle="Kelola tagihan dan bukti pembayaran PPDB" />

            <div className="mx-auto max-w-3xl px-5 pb-20">
                <div className="rounded-2xl bg-white p-8 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                    <p className="text-sm text-gray-500">
                        Halaman ini placeholder — fitur Pembayaran (tagihan, upload bukti, status verifikasi) dikerjakan di sesi
                        berikutnya.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}