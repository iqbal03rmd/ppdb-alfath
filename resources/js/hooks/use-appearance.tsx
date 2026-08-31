import { useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

/**
 * Aplikasi ini SENGAJA terang saja.
 *
 * Seluruh halaman (layout wali murid, kartu, badge, palet navy/oranye) memakai
 * warna terang yang ditulis langsung di kelas Tailwind. Sementara komponen
 * shadcn mengikuti token `.dark`. Waktu starter kit masih memasang kelas `dark`
 * dari preferensi OS, dua hal itu bertabrakan: halaman masuk jadi kartu putih
 * berisi input hitam, dan label putih di atas latar putih (kontras 1,05:1 -
 * praktis tak terbaca).
 *
 * Menambahkan mode gelap sungguhan berarti menulis ulang setiap warna jadi token,
 * dan itu jauh lebih besar daripada nilainya untuk proyek ini. Jadi kelas `dark`
 * dipastikan tidak pernah menempel.
 */
const applyTheme = () => {
    document.documentElement.classList.remove('dark');
};

export function initializeTheme() {
    applyTheme();
}

export function useAppearance() {
    const [appearance] = useState<Appearance>('light');

    // Dipertahankan supaya halaman pengaturan bawaan starter kit tidak rusak,
    // tapi mengubah tema tidak lagi berpengaruh.
    const updateAppearance = () => {
        applyTheme();
    };

    useEffect(() => {
        applyTheme();
    }, []);

    return { appearance, updateAppearance };
}
