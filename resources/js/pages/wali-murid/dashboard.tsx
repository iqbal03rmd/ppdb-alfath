import PageBanner from '@/components/page-banner';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, CircleAlert, FileText, GraduationCap, UploadCloud, UserPlus, Users, Wallet } from 'lucide-react';

interface RingkasanPendaftaran {
    id: number;
    nomor_pendaftaran: string;
    nama_pendaftar: string;
    kategori: string;
    status: string;
    catatan_verifikasi: string | null;
    sisa_tagihan: number | null;
    tahap: number;
    tahap_total: number;
    tindakan: string;
    tombol: string | null;
    rute: 'pendaftaran' | 'unggah-berkas' | 'pembayaran' | null;
    perlu_tindakan: boolean;
    // Dipisah karena bobotnya beda jauh: jatuh_tempo lewat = pendaftaran bisa
    // ditutup dan kursinya lepas; tanggal_cicilan cuma keterangan, tidak
    // berakibat apa-apa. Jangan disatukan lagi jadi satu label.
    jatuh_tempo: string | null;
    jatuh_tempo_lewat: boolean;
    tanggal_cicilan: string | null;
    menunggak: boolean;
}

interface DashboardProps {
    daftarPendaftaran: RingkasanPendaftaran[];
    ringkasan: {
        jumlah_anak: number;
        perlu_tindakan: number;
        total_sisa_tagihan: number;
        tenggat_terdekat: string | null;
        tenggat_terdekat_lewat: boolean;
        // Punya siapa tanggal itu - nama anaknya, atau "N anak" kalau tanggalnya
        // dipakai lebih dari satu. Tanpa ini satu tanggal telanjang nggak bisa
        // ditindaklanjuti wali yang punya beberapa anak.
        tenggat_terdekat_untuk: string | null;
    };
    // Cuma dipakai buat gerbang "boleh daftarkan anak baru" - tenggat pembayaran
    // TIDAK diturunkan dari sini, karena gelombang yang sedang dibuka belum tentu
    // gelombang milik pendaftaran wali. Tenggat tiap anak ada di item-nya sendiri.
    gelombangDibuka: { nama: string; tanggal_selesai: string } | null;
}

const statusBadge: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-100 text-gray-600' },
    diajukan: { label: 'Diajukan', className: 'bg-blue-100 text-blue-700' },
    diverifikasi: { label: 'Diverifikasi', className: 'bg-teal-100 text-teal-700' },
    perlu_perbaikan: { label: 'Perlu Perbaikan', className: 'bg-amber-100 text-amber-700' },
    diterima: { label: 'Diterima', className: 'bg-green-100 text-green-700' },
    ditolak: { label: 'Ditolak', className: 'bg-red-100 text-red-700' },
};

const NAMA_TAHAP = ['Registrasi', 'Formulir', 'Unggah Berkas', 'Pembayaran'];

function formatRupiah(nominal: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(nominal);
}

function tautan(item: RingkasanPendaftaran) {
    if (item.rute === 'pembayaran') return route('wali-murid.pembayaran.show', item.id);
    if (item.rute === 'unggah-berkas') return route('wali-murid.pendaftaran.unggah-berkas', item.id);

    return route('wali-murid.pendaftaran.index', { expand: item.id });
}

export default function Dashboard({ daftarPendaftaran, ringkasan, gelombangDibuka }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const namaDepan = String(auth.user?.name ?? '').split(' ')[0];
    const adaPendaftaran = daftarPendaftaran.length > 0;

    const subtitleText = !adaPendaftaran
        ? 'Belum ada anak yang kamu daftarkan.'
        : ringkasan.perlu_tindakan > 0
          ? `${ringkasan.perlu_tindakan} pendaftaran menunggu tindakan kamu.`
          : 'Semua pendaftaran sedang diproses sekolah — tidak ada yang perlu kamu lakukan.';

    const tanggalHariIni = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <AppLayout>
            <Head title="Beranda" />

            {/* Banner ciri khas, sama untuk semua peran - lihat PageBanner.
                Lapis keduanya membawa status gelombang + pintu mendaftar. */}
            <PageBanner
                ikon={<GraduationCap size={118} strokeWidth={1} />}
                tanggal={tanggalHariIni}
                judul={`Assalamu'alaikum, ${namaDepan}`}
                subjudul={subtitleText}
                stripVarian={gelombangDibuka ? 'biru' : 'abu'}
                strip={
                    gelombangDibuka ? (
                        <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                            <p className="text-sm text-[#0A3981]">
                                <b>{gelombangDibuka.nama}</b> dibuka sampai {gelombangDibuka.tanggal_selesai}.
                            </p>
                            {/* Wali baru: mendaftar itu satu-satunya hal yang bisa dia lakukan,
                                jadi tombolnya aksi utama. Wali yang sudah punya anak terdaftar:
                                aksi utamanya ada di kartu anak, jadi yang ini turun jadi
                                sekunder - satu aksi utama per layar.

                                Labelnya "Anak Lagi", bukan "Anak Lain": dalam bahasa
                                sehari-hari "anak lain" terbaca sebagai anak milik orang
                                lain, sedangkan yang dimaksud jelas anak wali ini juga. */}
                            <Button
                                asChild
                                size="sm"
                                variant={adaPendaftaran ? 'outline' : 'default'}
                                className={
                                    'h-8 rounded-xl font-bold ' +
                                    (adaPendaftaran ? 'border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-white hover:text-[#0A3981]' : '')
                                }
                            >
                                <Link href={route('wali-murid.pendaftaran.create')}>
                                    {adaPendaftaran ? '+ Daftarkan Anak Lagi' : '+ Daftarkan Anak'}
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <p className="text-sm text-gray-600">
                            Belum ada gelombang PPDB yang dibuka. Pendaftaran anak baru akan tersedia lagi begitu sekolah membuka gelombang
                            berikutnya.
                        </p>
                    )
                }
            />

            <PageContainer wide>
                <div className="pt-6">
                    {/* Empat angka yang paling dicari wali - lebar penuh, tepat di bawah
                    status gelombang, sebelum masuk ke rincian per anak. */}
                    {adaPendaftaran && (
                        <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Ringkas ikon={<Users size={18} strokeWidth={1.8} />} label="Anak Didaftarkan" nilai={String(ringkasan.jumlah_anak)} />
                            <Ringkas
                                ikon={<CircleAlert size={18} strokeWidth={1.8} />}
                                label="Perlu Tindakan"
                                nilai={String(ringkasan.perlu_tindakan)}
                                sorot={ringkasan.perlu_tindakan > 0}
                            />
                            <Ringkas
                                ikon={<Wallet size={18} strokeWidth={1.8} />}
                                label="Sisa Tagihan"
                                nilai={ringkasan.total_sisa_tagihan > 0 ? formatRupiah(ringkasan.total_sisa_tagihan) : 'Lunas'}
                            />
                            <Ringkas
                                ikon={<CalendarClock size={18} strokeWidth={1.8} />}
                                label={ringkasan.tenggat_terdekat ? 'Jatuh Tempo' : 'Gelombang'}
                                nilai={ringkasan.tenggat_terdekat ?? (gelombangDibuka ? gelombangDibuka.nama : 'Ditutup')}
                                catatan={ringkasan.tenggat_terdekat_untuk}
                                sorot={ringkasan.tenggat_terdekat_lewat}
                            />
                        </div>
                    )}

                    {/* Isi utama di kiri, rujukan di kanan - pola aside yang sama dengan
                    formulir pendaftaran. Di bawah lg dua-duanya menumpuk, jadi urutan
                    baca di layar kecil tetap: daftar anak dulu, alur belakangan. */}
                    <div className="grid gap-6 lg:grid-cols-4">
                        <div className="lg:col-span-3">
                            {!adaPendaftaran ? (
                                <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                                    <span className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[#D4EBF8]/60 text-[#1F509A]">
                                        <GraduationCap size={22} strokeWidth={1.8} />
                                    </span>
                                    <h2 className="text-[15px] font-semibold text-gray-900">Belum ada anak yang kamu daftarkan</h2>
                                    <p className="mx-auto mt-1.5 max-w-sm text-sm text-gray-500">
                                        {gelombangDibuka
                                            ? 'Mulai lewat tombol Daftarkan Anak di atas, lalu ikuti empat tahap di samping.'
                                            : 'Begitu sekolah membuka gelombang berikutnya, tombol untuk mendaftar akan muncul di atas.'}
                                    </p>
                                </div>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {daftarPendaftaran.map((item) => {
                                        const badge = statusBadge[item.status] ?? statusBadge.draft;

                                        return (
                                            <div
                                                key={item.id}
                                                className={
                                                    'flex flex-col rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ' +
                                                    (item.perlu_tindakan ? 'ring-1 ring-[#E38E49]/40' : '')
                                                }
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <h2 className="truncate text-[15px] font-semibold text-gray-900">{item.nama_pendaftar}</h2>
                                                        <p className="mt-0.5 text-xs text-gray-500">
                                                            {item.nomor_pendaftaran} · {item.kategori}
                                                        </p>
                                                    </div>
                                                    <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${badge.className}`}>
                                                        {badge.label}
                                                    </span>
                                                </div>

                                                <TahapMini tahap={item.tahap} total={item.tahap_total} ditolak={item.status === 'ditolak'} />

                                                <p className="mt-3 flex-1 text-sm text-gray-700">{item.tindakan}</p>

                                                {item.catatan_verifikasi && item.status === 'perlu_perbaikan' && (
                                                    <p className="mt-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-700">
                                                        {item.catatan_verifikasi}
                                                    </p>
                                                )}

                                                {/* Pendaftaran yang ditutup wajib menyebutkan sebabnya.
                                                    Sebelumnya wali cuma membaca "ditutup sekolah" tanpa
                                                    keterangan apa pun - dan sebagian dari mereka sudah
                                                    terlanjur menyetor uang. */}
                                                {item.status === 'ditolak' && item.catatan_verifikasi && (
                                                    <p className="mt-2 rounded-lg bg-red-50 p-3 text-xs text-red-700">{item.catatan_verifikasi}</p>
                                                )}

                                                {item.sisa_tagihan !== null && item.sisa_tagihan > 0 && (
                                                    <p className="mt-2 text-sm text-gray-500">
                                                        Sisa tagihan <b className="text-[#0A3981]">{formatRupiah(item.sisa_tagihan)}</b>
                                                    </p>
                                                )}

                                                {/* Jatuh tempo = batas minimal bayar, satu-satunya tanggal
                                            yang berakibat. Dua anak bisa punya tanggal berbeda kalau
                                            gelombangnya berbeda. */}
                                                {item.jatuh_tempo && (
                                                    <p className={'mt-1 text-xs ' + (item.jatuh_tempo_lewat ? 'text-red-600' : 'text-gray-500')}>
                                                        {item.jatuh_tempo_lewat
                                                            ? `Jatuh tempo ${item.jatuh_tempo} sudah lewat — hubungi Staf PPDB`
                                                            : `Jatuh tempo ${item.jatuh_tempo}`}
                                                    </p>
                                                )}

                                                {/* Tanggal cicilan sengaja dibedakan nadanya: sudah diterima,
                                            kursinya aman, jadi ini keterangan - bukan peringatan. */}
                                                {item.tanggal_cicilan && (
                                                    <p className={'mt-1 text-xs ' + (item.menunggak ? 'text-amber-600' : 'text-gray-500')}>
                                                        {item.menunggak
                                                            ? `Sisa cicilan melewati ${item.tanggal_cicilan} — pendaftaran tetap diterima`
                                                            : `Sisa boleh dicicil sampai ${item.tanggal_cicilan}`}
                                                    </p>
                                                )}

                                                {item.tombol && (
                                                    <Button
                                                        asChild
                                                        variant={item.perlu_tindakan ? 'default' : 'outline'}
                                                        size="sm"
                                                        className={
                                                            'mt-4 w-full rounded-xl font-bold ' +
                                                            (item.perlu_tindakan
                                                                ? ''
                                                                : 'border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]')
                                                        }
                                                    >
                                                        <Link href={tautan(item)}>{item.tombol}</Link>
                                                    </Button>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Rujukan - orientasi buat wali yang baru pertama kali ikut PPDB
                        online. Di kolom sempit tahapnya jadi menurun, dan itu justru
                        lebih enak dibaca daripada empat kolom melebar seperti waktu
                        blok ini masih di dasar halaman. */}
                        <aside className="lg:col-span-1">
                            <div className="rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                                <h2 className="text-[15px] font-semibold text-gray-900">Alur Pendaftaran</h2>
                                <p className="mb-4 text-sm text-gray-500">Empat tahap yang dilalui setiap pendaftaran.</p>
                                {/* Jarak antar langkah sengaja rapat: keempatnya harus kebaca
                                    tanpa scroll begitu wali sampai di Beranda, termasuk di
                                    laptop tinggi 768px. */}
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                    <Tahap
                                        no={1}
                                        judul="Registrasi"
                                        isi="Membuat akun wali murid. Tahap ini sudah kamu lewati."
                                        icon={<UserPlus size={16} strokeWidth={1.8} />}
                                    />
                                    <Tahap
                                        no={2}
                                        judul="Formulir"
                                        isi="Mengisi data calon peserta didik dan data orang tua/wali."
                                        icon={<FileText size={16} strokeWidth={1.8} />}
                                    />
                                    <Tahap
                                        no={3}
                                        judul="Unggah Berkas"
                                        isi="Mengunggah KK, akta, KTP, dan pas foto untuk diperiksa Staf PPDB."
                                        icon={<UploadCloud size={16} strokeWidth={1.8} />}
                                    />
                                    <Tahap
                                        no={4}
                                        judul="Pembayaran"
                                        isi="Membayar biaya PPDB setelah berkas dinyatakan lengkap."
                                        icon={<Wallet size={16} strokeWidth={1.8} />}
                                    />
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}

/**
 * Satu angka penting di Beranda wali - ikon, label, nilai. Berjajar empat di
 * bawah banner.
 *
 * `sorot` memberi cincin oranye: dipakai HANYA kalau angkanya menuntut
 * tindakan. Kalau semua kartu disorot, tidak ada yang tersorot.
 */
function Ringkas({
    ikon,
    label,
    nilai,
    catatan,
    sorot = false,
}: {
    ikon: React.ReactNode;
    label: string;
    nilai: string;
    catatan?: string | null;
    sorot?: boolean;
}) {
    return (
        <div
            className={
                'flex items-center gap-3 rounded-2xl bg-white p-4 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ' +
                (sorot ? 'ring-1 ring-[#E38E49]/40' : '')
            }
        >
            <span
                className={
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full ' +
                    (sorot ? 'bg-[#E38E49] text-white' : 'bg-[#D4EBF8]/60 text-[#1F509A]')
                }
            >
                {ikon}
            </span>
            <div className="min-w-0">
                <p className="text-xs text-gray-500">{label}</p>
                <p className="truncate text-[15px] font-semibold text-[#0A3981]">{nilai}</p>
                {catatan && <p className="truncate text-xs text-gray-500">{catatan}</p>}
            </div>
        </div>
    );
}

/** Empat titik: sejauh mana pendaftaran ini berjalan, tanpa perlu dibaca. */
function TahapMini({ tahap, total, ditolak }: { tahap: number; total: number; ditolak: boolean }) {
    return (
        <div className="mt-3 flex items-center gap-2">
            <div className="flex gap-1">
                {Array.from({ length: total }, (_, i) => (
                    <span
                        key={i}
                        title={NAMA_TAHAP[i]}
                        className={'h-1.5 w-6 rounded-full ' + (ditolak ? 'bg-gray-200' : i < tahap ? 'bg-green-500' : 'bg-gray-200')}
                    />
                ))}
            </div>
            <span className="text-xs text-gray-500">{ditolak ? 'Berhenti' : tahap >= total ? 'Selesai' : `Tahap ${tahap} dari ${total}`}</span>
        </div>
    );
}

function Tahap({ no, judul, isi, icon }: { no: number; judul: string; isi: string; icon: React.ReactNode }) {
    return (
        <div className="flex gap-3">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#D4EBF8]/60 text-[#1F509A]">{icon}</span>
            <div className="min-w-0">
                <p className="text-sm font-semibold text-gray-900">
                    <span className="mr-1 text-[#1F509A]/50">{no}.</span>
                    {judul}
                </p>
                <p className="mt-0.5 text-xs text-gray-500">{isi}</p>
            </div>
        </div>
    );
}
