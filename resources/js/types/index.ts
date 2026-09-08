export interface Auth {
    user: User;
    /** Beranda sesuai role user - tidak ada rute bernama 'dashboard'. */
    home_url: string | null;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { error: string | null; success: string | null };
    [key: string]: unknown;
}

/**
 * Bentuk user yang dibagikan HandleInertiaRequests ke semua halaman.
 *
 * `role` dan `telepon` DIDAFTARKAN di sini, bukan dibiarkan jatuh ke index
 * signature di bawah: lewat index signature tipenya jadi `unknown`, dan
 * pemakainya terpaksa membungkusnya dengan String(...) atau cast - yang
 * artinya TypeScript berhenti memeriksa apa pun soal kolom itu.
 */
export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    telepon: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}
