import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Dashboard Staf PPDB" />
            <div style={{ padding: 40 }}>
                <h1>Dashboard Staf PPDB</h1>
                <p>Halaman ini cuma placeholder — isi aslinya menyusul.</p>
            </div>
        </AppLayout>
    );
}