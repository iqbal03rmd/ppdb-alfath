import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PenggunaExisting {
    id: number;
    name: string;
    email: string;
    telepon: string | null;
    role: string;
    status_aktif: boolean;
    jumlah_pendaftaran: number;
    diri_sendiri: boolean;
    /** Null artinya peran boleh diganti. Kalimatnya datang dari server, bukan
     *  disusun ulang di sini - lihat PenggunaController::alasanPeranTerkunci(). */
    alasan_peran_terkunci: string | null;
}

interface PenggunaFormProps {
    peran: Record<string, string>;
    pengguna?: PenggunaExisting;
}

export default function PenggunaForm({ peran, pengguna }: PenggunaFormProps) {
    const isEdit = !!pengguna;
    const peranTerkunci = !!pengguna?.alasan_peran_terkunci;

    const { data, setData, post, put, processing, errors } = useForm({
        name: pengguna?.name ?? '',
        email: pengguna?.email ?? '',
        telepon: pengguna?.telepon ?? '',
        role: pengguna?.role ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('super-admin.pengguna.update', pengguna.id));
        } else {
            post(route('super-admin.pengguna.store'));
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? `Ubah Akun ${pengguna.name}` : 'Tambah Pengguna'} />
            <PageHeader
                title={isEdit ? `Ubah Akun — ${pengguna.name}` : 'Tambah Pengguna'}
                subtitle={
                    isEdit
                        ? 'Perbarui data akun, ganti perannya, atau atur hak masuknya.'
                        : 'Buat akun baru untuk staf, kepala sekolah, atau wali murid.'
                }
                wide
            />

            {/* `wide`, disamakan dengan halaman daftarnya. Halaman satu-record
                biasanya cukup lebar bawaan, tapi dua halaman ini bolak-balik
                lewat tombol Ubah dan Batal - lebar yang berganti tiap pindah
                bikin isinya kelihatan melompat, dan garis tepi kirinya pindah. */}
            <PageContainer wide>
                <div className="mb-5">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        <Link href={route('super-admin.pengguna.index')} aria-label="Kembali ke daftar pengguna" title="Kembali ke daftar pengguna">
                            <ArrowLeft size={18} strokeWidth={2} />
                        </Link>
                    </Button>
                </div>

                {/* Dua kolom, 2:1. Pembagiannya menurut JENIS pekerjaannya,
                    bukan sekadar memotong daftar kartu jadi dua tumpukan:

                      kiri  - yang DIKETIK admin (identitas, kata sandi)
                      kanan - yang DIPUTUSKAN tentang akun ini (peran, hak masuk)

                    Peran dan Hak Masuk memang sepasang: dua-duanya menentukan
                    orang ini boleh berbuat apa, dan dua-duanya cuma satu kendali
                    dengan penjelasan panjang - bentuk yang boros kalau dipaksa
                    selebar kolom isian.

                    Seluruhnya tetap di dalam satu <form>, jadi tombol di kolom
                    kanan WAJIB type="button" - kalau tidak, dia ikut jadi tombol
                    kirim dan menyimpan formulir tanpa diminta. */}
                <form onSubmit={submit} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Kartu judul="Identitas Akun">
                            <div className="mb-5">
                                <Label required htmlFor="name">
                                    Nama Lengkap
                                </Label>
                                <Input id="name" value={data.name} onChange={(v) => setData('name', v)} placeholder="Nama sesuai identitas" />
                                <FieldError message={errors.name} />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label required htmlFor="email">
                                        Email
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(v) => setData('email', v)}
                                        placeholder="nama@sekolah.sch.id"
                                    />
                                    <p className="mt-1 text-xs text-gray-500">Dipakai untuk masuk.</p>
                                    <FieldError message={errors.email} />
                                </div>
                                <div>
                                    <Label htmlFor="telepon">
                                        Telepon <span className="text-gray-500">(opsional)</span>
                                    </Label>
                                    <Input id="telepon" value={data.telepon} onChange={(v) => setData('telepon', v)} placeholder="08xxxxxxxxxx" />
                                    <FieldError message={errors.telepon} />
                                </div>
                            </div>
                        </Kartu>

                        <Kartu judul={isEdit ? 'Ganti Kata Sandi' : 'Kata Sandi'}>
                            {isEdit && <p className="mb-4 text-sm text-gray-500">Kosongkan dua kolom ini jika kata sandinya tidak perlu diubah.</p>}

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label required={!isEdit} htmlFor="password">
                                        Kata Sandi {isEdit && <span className="text-gray-500">(baru)</span>}
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(v) => setData('password', v)}
                                        placeholder="Minimal 8 karakter"
                                    />
                                    <FieldError message={errors.password} />
                                </div>
                                <div>
                                    <Label required={!isEdit} htmlFor="password_confirmation">
                                        Ulangi Kata Sandi
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(v) => setData('password_confirmation', v)}
                                        placeholder="Ketik ulang kata sandinya"
                                    />
                                    <FieldError message={errors.password_confirmation} />
                                </div>
                            </div>
                        </Kartu>

                        <div className="flex items-center gap-3">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                            >
                                {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Buat Akun'}
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-xl border-[#1F509A]/40 bg-white font-semibold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                            >
                                <Link href={route('super-admin.pengguna.index')}>Batal</Link>
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <Kartu judul="Peran">
                            <Label required htmlFor="role">
                                Peran dalam sistem
                            </Label>
                            <select
                                id="role"
                                value={data.role}
                                disabled={peranTerkunci}
                                onChange={(e) => setData('role', e.target.value)}
                                className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none disabled:cursor-not-allowed disabled:text-gray-500"
                            >
                                <option value="">Pilih peran</option>
                                {Object.entries(peran).map(([nilai, label]) => (
                                    <option key={nilai} value={nilai}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.role} />

                            {peranTerkunci && (
                                <p className="mt-2 flex items-start gap-2 rounded-xl bg-[#D4EBF8]/60 p-3 text-xs text-[#0A3981]">
                                    <Lock size={14} strokeWidth={2} className="mt-0.5 shrink-0" />
                                    <span>{pengguna.alasan_peran_terkunci}</span>
                                </p>
                            )}

                            {/* Daftar tugas tiap peran sengaja tidak diulang di
                                sini - nama perannya sendiri sudah menyebutkannya,
                                dan pembacanya cuma Super Admin sekolah. */}
                            <p className="mt-2 text-xs text-gray-500">Menentukan menu yang dia lihat setelah masuk.</p>
                        </Kartu>

                        {isEdit && <HakMasuk pengguna={pengguna} />}
                    </div>
                </form>
            </PageContainer>
        </AppLayout>
    );
}

/**
 * Hak masuk akun - pengganti "hapus akun".
 *
 * Sengaja berdiri di LUAR form utama dan paling bawah halaman: ini tindakan
 * yang berakibat langsung ke orangnya (sesinya diputus saat itu juga), bukan
 * sekadar mengubah data. Menaruhnya sebaris dengan "Simpan Perubahan" bikin
 * dua hal yang beda beratnya terlihat sama.
 *
 * Tombolnya bertingkat: sekali klik untuk memunculkan penegasan, klik kedua
 * yang benar-benar menjalankan. Bukan window.confirm - dialog bawaan browser
 * membekukan halaman dan tidak bisa menyebut angka pendaftaran yang terdampak.
 */
function HakMasuk({ pengguna }: { pengguna: PenggunaExisting }) {
    const [penegasanTampil, setPenegasanTampil] = useState(false);
    const [memproses, setMemproses] = useState(false);

    // Akunnya sendiri: tidak ada tombol sama sekali, bukan tombol yang dimatikan.
    // Server pun menolaknya - menonaktifkan diri sendiri berarti mengunci diri
    // di luar, dan cuma Super Admin yang bisa membukakan pintunya.
    if (pengguna.diri_sendiri) {
        return (
            <Kartu judul="Hak Masuk">
                <p className="text-sm text-gray-500">Akun Anda sendiri — hak masuknya tidak bisa dicabut dari sini.</p>
            </Kartu>
        );
    }

    // Nilai yang dikirim dibaca dari prop SAAT tombolnya ditekan, bukan
    // disimpan di state form waktu komponen ini pertama dirender. Halaman ini
    // tidak dipasang ulang sesudah aksinya berhasil - cuma propnya yang
    // diperbarui - jadi nilai yang dibekukan di awal akan tertinggal satu
    // langkah: sesudah menonaktifkan, tombol "Aktifkan Kembali" masih akan
    // mengirim "nonaktifkan" lagi.
    const jalankan = () => {
        setMemproses(true);
        router.post(
            route('super-admin.pengguna.status', pengguna.id),
            { status_aktif: !pengguna.status_aktif },
            {
                preserveScroll: true,
                onSuccess: () => setPenegasanTampil(false),
                onFinish: () => setMemproses(false),
            },
        );
    };

    return (
        <Kartu judul="Hak Masuk">
            {pengguna.status_aktif ? (
                <>
                    {/* "data tetap utuh" sengaja dipertahankan walau kalimatnya
                        dipendekkan: itu yang membedakan menonaktifkan dari
                        menghapus, dan alasan modul ini tidak punya tombol hapus. */}
                    <p className="text-sm text-gray-500">Mencabut hak masuknya. Data tetap utuh dan bisa diaktifkan lagi kapan saja.</p>

                    {!penegasanTampil ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPenegasanTampil(true)}
                            className="mt-4 rounded-xl border-red-200 bg-white font-semibold text-red-700 hover:bg-red-50 hover:text-red-800"
                        >
                            Nonaktifkan Akun
                        </Button>
                    ) : (
                        <div className="mt-4 rounded-xl bg-red-50 p-4">
                            {/* Penegasan boleh sependek ini karena cuma muncul
                                sesudah tombolnya ditekan - bukan teks yang ikut
                                meramaikan halaman saat dibaca sekilas. Yang
                                dipertahankan dua hal yang tidak bisa ditebak dari
                                nama tombolnya: sesinya putus saat itu juga, dan
                                pendaftarannya TIDAK ikut batal. */}
                            <p className="text-sm text-red-700">
                                Nonaktifkan <b>{pengguna.name}</b>? Sesinya langsung diputus.
                                {pengguna.jumlah_pendaftaran > 0 && (
                                    <> {pengguna.jumlah_pendaftaran} pendaftarannya tetap berjalan dan tetap memegang kursi kuota.</>
                                )}
                            </p>
                            <div className="mt-3 flex gap-2">
                                <Button
                                    type="button"
                                    disabled={memproses}
                                    onClick={jalankan}
                                    className="rounded-xl bg-red-600 font-semibold text-white hover:bg-red-700"
                                >
                                    {memproses ? 'Memproses...' : 'Ya, Nonaktifkan'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setPenegasanTampil(false)}
                                    className="rounded-xl border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-50"
                                >
                                    Batal
                                </Button>
                            </div>
                        </div>
                    )}
                </>
            ) : (
                <>
                    <p className="text-sm text-gray-500">Sedang dinonaktifkan, akun ini tidak bisa masuk.</p>
                    <Button
                        type="button"
                        disabled={memproses}
                        onClick={jalankan}
                        className="mt-4 rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                    >
                        {memproses ? 'Memproses...' : 'Aktifkan Kembali'}
                    </Button>
                </>
            )}
        </Kartu>
    );
}

function Kartu({ judul, children }: { judul: string; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <h2 className="mb-5 text-[15px] font-semibold text-gray-900">{judul}</h2>
            {children}
        </div>
    );
}

function Label({ children, required, htmlFor }: { children: React.ReactNode; required?: boolean; htmlFor: string }) {
    return (
        <label htmlFor={htmlFor} className="mb-1.5 block text-[13px] font-medium text-gray-600">
            {children}
            {required && <span className="ml-0.5 text-red-500">*</span>}
        </label>
    );
}

// `id` sengaja WAJIB, sama alasannya dengan Input di pendaftaran-create.tsx:
// itu yang menyambungkan kolom ke labelnya, dan TypeScript yang menahannya -
// bukan ingatan orang yang menambah kolom berikutnya.
function Input({
    id,
    value,
    onChange,
    placeholder,
    type = 'text',
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    type?: 'text' | 'email' | 'password';
}) {
    return (
        <input
            id={id}
            type={type}
            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
        />
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}
