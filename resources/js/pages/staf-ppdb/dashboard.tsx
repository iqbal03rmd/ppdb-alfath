import { router } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <div style={{ padding: 40 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h1>Dashboard Staf PPDB</h1>
                <button
                    onClick={() => router.post(route('logout'))}
                    style={{ padding: '8px 16px', cursor: 'pointer' }}
                >
                    Logout
                </button>
            </div>
            <p>Halaman ini cuma placeholder — isi aslinya menyusul.</p>
        </div>
    );
}