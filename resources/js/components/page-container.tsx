import { type ReactNode } from 'react';

/**
 * Pembungkus isi halaman - SATU-SATUNYA tempat lebar konten ditentukan.
 * Jangan menulis `max-w-*` atau `px-*` sendiri di halaman; pakai ini supaya
 * isi halaman nggak melompat lebar tiap pindah menu.
 *
 * Dua ragam saja, dipilih menurut jenis isinya:
 *   default → halaman satu record (detail, pembayaran, unggah berkas)
 *   wide    → halaman daftar & tabel data, atau formulir dengan panel samping
 *
 * Lebarnya dikalibrasi ke layar 1080p (1920px) sebagai patokan umum: dikurangi
 * sidebar 288px, area konten jadi 1632px. Batas atas tetap dipasang supaya di
 * monitor lebih besar baris "label <-> nilai" nggak terentang selebar layar -
 * jarak tempuh mata bikin nama komponen dan nominalnya susah dipasangkan.
 *
 *   1366px (laptop umum) -> area 1078px : dua-duanya terisi hampir penuh
 *   1920px (1080p)       -> area 1632px : sempit sisa ~304px, lebar sisa ~176px
 */
export default function PageContainer({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    return <div className={`mx-auto w-full px-4 pb-20 sm:px-6 lg:px-8 ${wide ? 'max-w-7xl' : 'max-w-5xl'}`}>{children}</div>;
}
