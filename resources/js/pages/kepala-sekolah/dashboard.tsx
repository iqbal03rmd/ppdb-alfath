import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Dashboard Kepala Sekolah" />
            <div style={{ padding: 40 }}>
                <h1>Dashboard Kepala Sekolah</h1>
                <p>Halaman ini cuma placeholder — isi aslinya menyusul.</p>
            </div>
        </AppLayout>
    );
}