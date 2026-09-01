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
 *
 * `flush` dipakai waktu container ini ditaruh DI DALAM blok berwarna yang
 * melebar penuh (mis. banner Beranda): warnanya boleh mentok ke tepi, tapi
 * isinya harus tetap sebaris dengan konten halaman di bawahnya - kalau tidak,
 * ada dua garis tepi kiri yang berbeda dalam satu halaman dan itu kelihatan
 * salah walau susah ditunjuk. Jarak bawah halaman dilepas karena blok berwarna
 * mengatur tingginya sendiri.
 */
export default function PageContainer({ children, wide = false, flush = false }: { children: ReactNode; wide?: boolean; flush?: boolean }) {
    return <div className={`mx-auto w-full px-4 sm:px-6 lg:px-8 ${flush ? '' : 'pb-20'} ${wide ? 'max-w-7xl' : 'max-w-5xl'}`}>{children}</div>;
}
