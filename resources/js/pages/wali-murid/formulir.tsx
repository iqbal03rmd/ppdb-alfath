import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/page-header';
import { FormEventHandler } from 'react';

interface KategoriSiswa {
    id: number;
    nama: string;
    deskripsi: string | null;
}

interface Gelombang {
    id: number;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
}

type WaliMuridInput = {
    nama: string;
    nik: string;
    hubungan: string;
    telepon: string;
};

interface FormulirProps {
    kategoriSiswa: KategoriSiswa[];
    gelombang: Gelombang | null;
}

export default function Formulir({ kategoriSiswa, gelombang }: FormulirProps) {
    const { data, setData, post, processing, errors: rawErrors } = useForm({
        kategori_siswa_id: '',
        nama_pendaftar: '',
        nik: '',
        tanggal_lahir: '',
        tempat_lahir: '',
        jenis_kelamin: '',
        agama: '',
        alamat: '',
        nama_saudara: '',
        nama_orang_tua_guru: '',
        wali_murid: [{ nama: '', nik: '', hubungan: '', telepon: '' }] as WaliMuridInput[],
    });

    // Inertia cuma tahu key top-level ('nama_pendaftar', dst) secara tipe,
    // padahal runtime-nya bisa ngasih key dinamis kayak "wali.0.nik" untuk
    // error field array. Cast ke Record<string, string> biar bisa diakses bebas.
    const errors = rawErrors as Record<string, string>;

    const kategoriTerpilih = kategoriSiswa.find(
        (k) => String(k.id) === data.kategori_siswa_id,
    );

    function addWaliMurid() {
        setData('wali_murid', [...data.wali_murid, { nama: '', nik: '', hubungan: '', telepon: '' }]);
    }

    function removeWaliMurid(index: number) {
        if (data.wali_murid.length <= 1) return; // minimal 1 wali murid wajib ada
        setData(
            'wali_murid',
            data.wali_murid.filter((_, i) => i !== index),
        );
    }

    function updateWaliMurid(index: number, field: keyof WaliMuridInput, value: string) {
        const newWaliMurid = [...data.wali_murid];
        newWaliMurid[index] = { ...newWaliMurid[index], [field]: value };
        setData('wali_murid', newWaliMurid);
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('wali-murid.formulir.store'));
    };

    return (
        <>
            <Head title="Formulir Pendaftaran PPDB" />

            <AppLayout>
                <PageHeader
                    title="Formulir Pendaftaran PPDB"
                    subtitle={
                        gelombang
                            ? `Gelombang: ${gelombang.nama} (${gelombang.tanggal_mulai} s/d ${gelombang.tanggal_selesai})`
                            : 'Tidak ada gelombang PPDB yang sedang dibuka saat ini.'
                    }
                />
                <div className="mx-auto max-w-6xl px-8 pb-20">
                    {/* Stepper */}
                    <div className="mb-10 flex items-center">
                        <Step label="Registrasi Akun" state="done" />
                        <StepLine />
                        <Step label="Formulir" state="active" />
                        <StepLine />
                        <Step label="Unggah Berkas" state="pending" />
                        <StepLine />
                        <Step label="Pembayaran" state="pending" />
                    </div>

                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-4">
                    <form onSubmit={submit} className="lg:col-span-3">
                        {/* Data calon peserta didik */}
                        <Section title="Data Calon Peserta Didik">
                            <div className="mb-5">
                                <Label required>Kategori Siswa</Label>
                                <select
                                    className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1F509A]/15"
                                    value={data.kategori_siswa_id}
                                    onChange={(e) => setData('kategori_siswa_id', e.target.value)}
                                >
                                    <option value="">Pilih kategori siswa</option>
                                    {kategoriSiswa.map((k) => (
                                        <option key={k.id} value={k.id}>
                                            {k.nama}
                                        </option>
                                    ))}
                                </select>
                                {kategoriTerpilih?.deskripsi && (
                                    <p className="mt-1 text-xs text-gray-500">{kategoriTerpilih.deskripsi}</p>
                                )}
                                <FieldError message={errors.kategori_siswa_id} />
                            </div>

                            <div className="mb-5 grid grid-cols-2 gap-5">
                                <div>
                                    <Label required>Nama Lengkap</Label>
                                    <Input
                                        value={data.nama_pendaftar}
                                        onChange={(v) => setData('nama_pendaftar', v)}
                                        placeholder="Nama lengkap calon peserta didik"
                                    />
                                    <FieldError message={errors.nama_pendaftar} />
                                </div>
                                <div>
                                    <Label>
                                        NIK <span className="text-gray-400">(opsional)</span>
                                    </Label>
                                    <Input
                                        value={data.nik}
                                        onChange={(v) => setData('nik', v)}
                                        placeholder="16 digit NIK"
                                        maxLength={16}
                                    />
                                    <FieldError message={errors.nik} />
                                </div>
                                <div>
                                    <Label required>Tempat Lahir</Label>
                                    <Input
                                        value={data.tempat_lahir}
                                        onChange={(v) => setData('tempat_lahir', v)}
                                        placeholder="Kota kelahiran"
                                    />
                                    <FieldError message={errors.tempat_lahir} />
                                </div>
                                <div>
                                    <Label required>Tanggal Lahir</Label>
                                    <input
                                        type="date"
                                        className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1F509A]/15"
                                        value={data.tanggal_lahir}
                                        onChange={(e) => setData('tanggal_lahir', e.target.value)}
                                    />
                                    <FieldError message={errors.tanggal_lahir} />
                                </div>
                            </div>

                            <div className="mb-5 grid grid-cols-2 gap-5">
                                <div>
                                    <Label required>Jenis Kelamin</Label>
                                    <div className="flex h-[42px] items-center gap-6">
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="radio"
                                                name="jenis_kelamin"
                                                checked={data.jenis_kelamin === 'laki-laki'}
                                                onChange={() => setData('jenis_kelamin', 'laki-laki')}
                                                className="h-4 w-4 accent-[#1F509A]"
                                            />
                                            Laki-laki
                                        </label>
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="radio"
                                                name="jenis_kelamin"
                                                checked={data.jenis_kelamin === 'perempuan'}
                                                onChange={() => setData('jenis_kelamin', 'perempuan')}
                                                className="h-4 w-4 accent-[#1F509A]"
                                            />
                                            Perempuan
                                        </label>
                                    </div>
                                    <FieldError message={errors.jenis_kelamin} />
                                </div>

                                <div>
                                    <Label>
                                        Agama <span className="text-gray-400">(opsional)</span>
                                    </Label>
                                    <Input value={data.agama} onChange={(v) => setData('agama', v)} placeholder="Agama" />
                                    <FieldError message={errors.agama} />
                                </div>
                            </div>

                            <div>
                                <Label required>Alamat Domisili</Label>
                                <textarea
                                    className="min-h-[90px] resize-y w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1F509A]/15"
                                    value={data.alamat}
                                    onChange={(e) => setData('alamat', e.target.value)}
                                    placeholder="Alamat lengkap tempat tinggal"
                                />
                                <FieldError message={errors.alamat} />
                            </div>
                        </Section>

                        {/* Data pendukung klaim kategori - tampil kondisional */}
                        {kategoriTerpilih?.nama === 'Saudara' && (
                            <Section title="Data Pendukung: Saudara di Sekolah Ini">
                                <Label>Nama Saudara</Label>
                                <Input
                                    value={data.nama_saudara}
                                    onChange={(v) => setData('nama_saudara', v)}
                                    placeholder="Nama saudara kandung yang terdaftar di sekolah ini"
                                />
                                <FieldError message={errors.nama_saudara} />
                            </Section>
                        )}

                        {kategoriTerpilih?.nama === 'Anak Guru/Tenaga Kependidikan' && (
                            <Section title="Data Pendukung: Orang Tua Guru/Tenaga Kependidikan">
                                <Label>Nama Orang Tua</Label>
                                <Input
                                    value={data.nama_orang_tua_guru}
                                    onChange={(v) => setData('nama_orang_tua_guru', v)}
                                    placeholder="Nama orang tua yang merupakan guru/tenaga kependidikan"
                                />
                                <FieldError message={errors.nama_orang_tua_guru} />
                            </Section>
                        )}

                        {/* Data wali - repeatable */}
                        <Section title="Data Orang Tua / Wali">
                            {data.wali_murid.map((w, index) => (
                                <div key={index} className="mb-5 rounded-xl border border-[#D4EBF8] bg-[#F5F9FD]/50 p-5">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-xs font-bold tracking-wide text-[#1F509A] uppercase">Wali {index + 1}</span>
                                        {data.wali_murid.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeWaliMurid(index)}
                                                className="rounded-full px-2.5 py-1 text-xs font-medium text-red-500 hover:bg-red-50"
                                            >
                                                Hapus
                                            </button>
                                        )}
                                    </div>
                                    <div className="grid grid-cols-2 gap-5">
                                        <div>
                                            <Label required>Nama</Label>
                                            <Input
                                                value={w.nama}
                                                onChange={(v) => updateWaliMurid(index, 'nama', v)}
                                                placeholder="Nama lengkap"
                                            />
                                            <FieldError message={errors[`wali_murid.${index}.nama`]} />
                                        </div>
                                        <div>
                                            <Label required>NIK</Label>
                                            <Input
                                                value={w.nik}
                                                onChange={(v) => updateWaliMurid(index, 'nik', v)}
                                                placeholder="16 digit NIK"
                                                maxLength={16}
                                            />
                                            <FieldError message={errors[`wali_murid.${index}.nik`]} />
                                        </div>
                                        <div>
                                            <Label required>Hubungan</Label>
                                            <select
                                                className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1F509A]/15"
                                                value={w.hubungan}
                                                onChange={(e) => updateWaliMurid(index, 'hubungan', e.target.value)}
                                            >
                                                <option value="">Pilih hubungan</option>
                                                <option value="Ayah">Ayah</option>
                                                <option value="Ibu">Ibu</option>
                                                <option value="Wali Lainnya">Wali Lainnya</option>
                                            </select>
                                            <FieldError message={errors[`wali_murid.${index}.hubungan`]} />
                                        </div>
                                        <div>
                                            <Label required>No. WhatsApp Aktif</Label>
                                            <Input
                                                value={w.telepon}
                                                onChange={(v) => updateWaliMurid(index, 'telepon', v)}
                                                placeholder="08xxxxxxxxxx"
                                            />
                                            <FieldError message={errors[`wali_murid.${index}.telepon`]} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                            <FieldError message={errors.wali_murid} />

                            <button
                                type="button"
                                onClick={addWaliMurid}
                                className="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-[#1F509A]/40 py-2.5 text-sm font-medium text-[#1F509A] transition-colors hover:bg-[#F5F9FD]"
                            >
                                <span className="text-lg leading-none">+</span> Tambah Wali
                            </button>
                        </Section>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-xl bg-[#E38E49] py-3.5 text-[15px] font-bold text-white shadow-[0_4px_14px_-4px_rgba(227,142,73,0.5)] transition-colors hover:bg-[#d47f3a] disabled:opacity-50"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan dan Lanjutkan ke Unggah Berkas'}
                        </button>
                    </form>

                    {/* Panel kanan - info bantu, sekaligus ngisi ruang kosong */}
                    <aside className="lg:col-span-1">
                        <div className="sticky top-8 flex flex-col gap-5">
                            {kategoriTerpilih && (
                                <div className="rounded-2xl bg-[#0A3981] p-6 text-white shadow-[0_8px_24px_-8px_rgba(10,57,129,0.35)]">
                                    <h3 className="mb-1 text-sm font-semibold text-[#D4EBF8]">Kategori Terpilih</h3>
                                    <p className="text-base font-semibold">{kategoriTerpilih.nama}</p>
                                    {kategoriTerpilih.deskripsi && (
                                        <p className="mt-2 text-sm leading-relaxed text-white/80">{kategoriTerpilih.deskripsi}</p>
                                    )}
                                </div>
                            )}

                            <div className="rounded-2xl border border-[#D4EBF8] bg-[#F5F9FD] p-6">
                                <h3 className="mb-2 text-sm font-semibold text-[#0A3981]">Tips Pengisian</h3>
                                <ul className="space-y-2 text-xs leading-relaxed text-gray-600">
                                    <li>• Isi data sesuai dokumen resmi (KK/Akta) untuk mempercepat verifikasi.</li>
                                    <li>• Nomor WhatsApp wali harus aktif, digunakan untuk semua notifikasi PPDB.</li>
                                    <li>• Kamu bisa menambahkan lebih dari satu data wali jika diperlukan.</li>
                                </ul>
                            </div>
                        </div>
                    </aside>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}

/* ---------- Komponen kecil bantu, biar form di atas nggak terlalu panjang ---------- */

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="mb-6 rounded-2xl bg-white p-8 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
            <h2 className="mb-6 flex items-center gap-2.5 text-[15px] font-semibold text-[#0A3981]">
                <span className="h-5 w-1 rounded-full bg-[#E38E49]" />
                {title}
            </h2>
            {children}
        </div>
    );
}

function Label({ children, required }: { children: React.ReactNode; required?: boolean }) {
    return (
        <label className="mb-1.5 block text-[13px] font-medium text-gray-600">
            {children}
            {required && <span className="ml-0.5 text-red-500">*</span>}
        </label>
    );
}

function Input({
    value,
    onChange,
    placeholder,
    maxLength,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    maxLength?: number;
}) {
    return (
        <input
            type="text"
            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1F509A]/15"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            maxLength={maxLength}
        />
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

function Step({ label, state }: { label: string; state: 'done' | 'active' | 'pending' }) {
    const circleClass =
        state === 'done'
            ? 'border-green-500 bg-green-500 text-white'
            : state === 'active'
              ? 'border-[#0A3981] bg-[#0A3981] text-white shadow-[0_0_0_4px_rgba(10,57,129,0.12)]'
              : 'border-gray-200 bg-white text-gray-300';

    return (
        <div className="flex items-center">
            <div className={`flex h-9 w-9 items-center justify-center rounded-full border-2 text-xs font-bold transition-all ${circleClass}`}>
                {state === 'done' ? '✓' : label[0]}
            </div>
            <span className={`ml-2 text-[13px] ${state === 'active' ? 'font-semibold text-[#0A3981]' : state === 'done' ? 'text-gray-500' : 'text-gray-300'}`}>
                {label}
            </span>
        </div>
    );
}

function StepLine() {
    return <div className="mx-3 h-0.5 w-14 bg-[#D4EBF8]" />;
}