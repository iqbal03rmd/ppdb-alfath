import AlurStepper from '@/components/alur-stepper';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface KategoriSiswa {
    id: number;
    nama: string;
    deskripsi: string | null;
    kuota: number | null;
    sisa_kuota: number | null;
    penuh: boolean;
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

interface PendaftaranExisting {
    id: number;
    kategori_siswa_id: string;
    nama_pendaftar: string;
    nik: string;
    tanggal_lahir: string;
    tempat_lahir: string;
    jenis_kelamin: string;
    agama: string;
    alamat: string;
    rt: string;
    rw: string;
    kelurahan: string;
    kecamatan: string;
    kota_kabupaten: string;
    provinsi: string;
    asal_paud_id: number | null;
    asal_paud_lainnya: string;
    tanpa_paud: boolean;
    tahu_dari: string;
    tahu_dari_lainnya: string;
    nama_saudara: string;
    nama_orang_tua_guru: string;
    wali_murid: WaliMuridInput[];
}

interface FormulirProps {
    kategoriSiswa: KategoriSiswa[];
    gelombang: Gelombang | null;
    pendaftaran?: PendaftaranExisting;
    asalPaud: { jenis: string; label: string; sekolah: { id: number; nama: string }[] }[];
    sumberInformasi: { nilai: string; label: string }[];
}

/** Dua nilai khusus di daftar sekolah asal, dengan alasan yang berbeda:
 *  'tanpa' itu JAWABAN (anak memang tidak lewat PAUD), 'lainnya' itu jalan
 *  keluar saat sekolahnya belum ada di daftar. */
const TANPA_PAUD = 'tanpa-paud';
const PAUD_LAINNYA = 'paud-lainnya';

export default function Formulir({ kategoriSiswa, gelombang, pendaftaran, asalPaud, sumberInformasi }: FormulirProps) {
    const isEdit = !!pendaftaran;

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors: rawErrors,
    } = useForm({
        kategori_siswa_id: pendaftaran?.kategori_siswa_id ?? '',
        nama_pendaftar: pendaftaran?.nama_pendaftar ?? '',
        nik: pendaftaran?.nik ?? '',
        tanggal_lahir: pendaftaran?.tanggal_lahir ?? '',
        tempat_lahir: pendaftaran?.tempat_lahir ?? '',
        jenis_kelamin: pendaftaran?.jenis_kelamin ?? '',
        agama: pendaftaran?.agama ?? '',
        alamat: pendaftaran?.alamat ?? '',
        rt: pendaftaran?.rt ?? '',
        rw: pendaftaran?.rw ?? '',
        kelurahan: pendaftaran?.kelurahan ?? '',
        kecamatan: pendaftaran?.kecamatan ?? '',
        kota_kabupaten: pendaftaran?.kota_kabupaten ?? '',
        provinsi: pendaftaran?.provinsi ?? '',
        asal_paud_id: pendaftaran?.asal_paud_id ?? null,
        asal_paud_lainnya: pendaftaran?.asal_paud_lainnya ?? '',
        tanpa_paud: pendaftaran?.tanpa_paud ?? false,
        tahu_dari: pendaftaran?.tahu_dari ?? '',
        tahu_dari_lainnya: pendaftaran?.tahu_dari_lainnya ?? '',
        nama_saudara: pendaftaran?.nama_saudara ?? '',
        nama_orang_tua_guru: pendaftaran?.nama_orang_tua_guru ?? '',
        wali_murid: (pendaftaran?.wali_murid?.length
            ? pendaftaran.wali_murid
            : [{ nama: '', nik: '', hubungan: '', telepon: '' }]) as WaliMuridInput[],
    });

    // Inertia cuma tahu key top-level ('nama_pendaftar', dst) secara tipe,
    // padahal runtime-nya bisa ngasih key dinamis kayak "wali.0.nik" untuk
    // error field array. Cast ke Record<string, string> biar bisa diakses bebas.
    const errors = rawErrors as Record<string, string>;

    const kategoriTerpilih = kategoriSiswa.find((k) => String(k.id) === data.kategori_siswa_id);

    // Pilihan sekolah asal disimpan di layar saja, TIDAK ikut dikirim: yang
    // dikirim tiga kolom aslinya (id / ketikan / tanpa PAUD), sedangkan nilai
    // select-nya cuma alat bantu. Tanpa state ini, memilih "ketik sendiri" akan
    // langsung terpental balik selama kotak ketiknya masih kosong.
    const [paudTerpilih, setPaudTerpilih] = useState<string>(
        pendaftaran?.tanpa_paud
            ? TANPA_PAUD
            : pendaftaran?.asal_paud_id
              ? String(pendaftaran.asal_paud_id)
              : pendaftaran?.asal_paud_lainnya
                ? PAUD_LAINNYA
                : '',
    );

    function pilihPaud(nilai: string) {
        setPaudTerpilih(nilai);
        setData((d) => ({
            ...d,
            tanpa_paud: nilai === TANPA_PAUD,
            asal_paud_id: nilai === TANPA_PAUD || nilai === PAUD_LAINNYA || nilai === '' ? null : Number(nilai),
            asal_paud_lainnya: nilai === PAUD_LAINNYA ? d.asal_paud_lainnya : '',
        }));
    }

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
        if (isEdit) {
            put(route('wali-murid.pendaftaran.update', pendaftaran.id));
        } else {
            post(route('wali-murid.pendaftaran.store'));
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Edit Pendaftaran PPDB' : 'Formulir Pendaftaran PPDB'} />

            <AppLayout>
                <PageHeader
                    title={isEdit ? `Edit Pendaftaran - ${pendaftaran.nama_pendaftar}` : 'Formulir Pendaftaran PPDB'}
                    subtitle={
                        isEdit
                            ? 'Perbarui data di bawah, lalu simpan perubahan.'
                            : gelombang
                              ? `Gelombang: ${gelombang.nama} (${gelombang.tanggal_mulai} s/d ${gelombang.tanggal_selesai})`
                              : 'Tidak ada gelombang PPDB yang sedang dibuka saat ini.'
                    }
                    wide
                />
                <PageContainer wide>
                    {/* Stepper tetap tampil waktu mengedit. Formulir cuma bisa diedit
                        saat status draft atau perlu_perbaikan - dua-duanya masih di
                        dalam alur pendaftaran, jadi wali justru butuh orientasi di
                        situ. Halaman Unggah Berkas juga menampilkannya tanpa syarat. */}
                    <AlurStepper aktif="Formulir" />

                    {/* Pendaftaran baru ditutup: formulirnya nggak ditampilkan sama
                        sekali, bukan sekadar tombol simpannya dimatikan. Server pun
                        menolak store() dalam keadaan ini, jadi membiarkan 16 kolom
                        bisa diisi cuma menunda kabar buruknya sampai klik terakhir.
                        Mode EDIT dikecualikan - wali yang diminta memperbaiki data
                        harus tetap bisa mengirim perbaikannya walau gelombangnya
                        sudah ditutup. */}
                    {!isEdit && !gelombang ? (
                        <div className="rounded-2xl bg-white p-10 text-center shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                            <span className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[#D4EBF8]/60 text-[#1F509A]">
                                <CalendarClock size={22} strokeWidth={1.8} />
                            </span>
                            <h2 className="text-[15px] font-semibold text-gray-900">Pendaftaran sedang ditutup</h2>
                            <p className="mx-auto mt-1.5 max-w-md text-sm text-gray-500">
                                Sekolah belum membuka gelombang PPDB berikutnya, jadi pendaftaran anak baru belum bisa diisi. Silakan cek kembali
                                nanti — pendaftaran yang sudah berjalan tetap bisa kamu urus dari halaman Pendaftaran.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                className="mt-5 rounded-xl border-[#1F509A]/40 bg-white text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                            >
                                <Link href={route('wali-murid.pendaftaran.index')}>Kembali ke Pendaftaran</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-8 lg:grid-cols-4">
                            <form onSubmit={submit} className="lg:col-span-3">
                                {/* Data calon peserta didik */}
                                <Section title="Data Calon Peserta Didik">
                                    <div className="mb-5">
                                        <Label required htmlFor="kategori_siswa_id">
                                            Kategori Siswa
                                        </Label>
                                        <select
                                            id="kategori_siswa_id"
                                            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
                                            value={data.kategori_siswa_id}
                                            onChange={(e) => setData('kategori_siswa_id', e.target.value)}
                                        >
                                            <option value="">Pilih kategori siswa</option>
                                            {kategoriSiswa.map((k) => (
                                                <option key={k.id} value={k.id} disabled={k.penuh}>
                                                    {k.nama}
                                                    {k.penuh ? ' — Kuota penuh' : k.sisa_kuota !== null ? ` — sisa kuota ${k.sisa_kuota}` : ''}
                                                </option>
                                            ))}
                                        </select>
                                        {kategoriTerpilih?.deskripsi && <p className="mt-1 text-xs text-gray-500">{kategoriTerpilih.deskripsi}</p>}
                                        {kategoriTerpilih && kategoriTerpilih.sisa_kuota !== null && !kategoriTerpilih.penuh && (
                                            <p className="mt-1 text-xs text-[#1F509A]">
                                                Sisa kuota kategori ini: <b>{kategoriTerpilih.sisa_kuota}</b> dari {kategoriTerpilih.kuota}.
                                            </p>
                                        )}
                                        <FieldError message={errors.kategori_siswa_id} />
                                    </div>

                                    <div className="mb-5 grid grid-cols-2 gap-5">
                                        <div>
                                            <Label required htmlFor="nama_pendaftar">
                                                Nama Lengkap
                                            </Label>
                                            <Input
                                                id="nama_pendaftar"
                                                value={data.nama_pendaftar}
                                                onChange={(v) => setData('nama_pendaftar', v)}
                                                placeholder="Nama lengkap calon peserta didik"
                                            />
                                            <FieldError message={errors.nama_pendaftar} />
                                        </div>
                                        <div>
                                            <Label htmlFor="nik">
                                                NIK <span className="text-gray-500">(opsional)</span>
                                            </Label>
                                            <Input
                                                id="nik"
                                                value={data.nik}
                                                onChange={(v) => setData('nik', v)}
                                                placeholder="16 digit NIK"
                                                maxLength={16}
                                            />
                                            <FieldError message={errors.nik} />
                                        </div>
                                        <div>
                                            <Label required htmlFor="tempat_lahir">
                                                Tempat Lahir
                                            </Label>
                                            <Input
                                                id="tempat_lahir"
                                                value={data.tempat_lahir}
                                                onChange={(v) => setData('tempat_lahir', v)}
                                                placeholder="Kota kelahiran"
                                            />
                                            <FieldError message={errors.tempat_lahir} />
                                        </div>
                                        <div>
                                            <Label required htmlFor="tanggal_lahir">
                                                Tanggal Lahir
                                            </Label>
                                            <input
                                                id="tanggal_lahir"
                                                type="date"
                                                className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
                                                value={data.tanggal_lahir}
                                                onChange={(e) => setData('tanggal_lahir', e.target.value)}
                                            />
                                            <FieldError message={errors.tanggal_lahir} />
                                        </div>
                                    </div>

                                    <div className="mb-5 grid grid-cols-2 gap-5">
                                        <div>
                                            <Label required id="label_jenis_kelamin">
                                                Jenis Kelamin
                                            </Label>
                                            {/* Kelompok radio, bukan satu kolom - jadi labelnya yang
                                                ditunjuk balik lewat aria-labelledby. Dua pilihan di
                                                dalamnya sudah dibungkus <label> masing-masing. */}
                                            <div role="radiogroup" aria-labelledby="label_jenis_kelamin" className="flex h-[42px] items-center gap-6">
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
                                            <Label htmlFor="agama">
                                                Agama <span className="text-gray-500">(opsional)</span>
                                            </Label>
                                            <Input id="agama" value={data.agama} onChange={(v) => setData('agama', v)} placeholder="Agama" />
                                            <FieldError message={errors.agama} />
                                        </div>
                                    </div>
                                </Section>

                                <Section title="Alamat Tempat Tinggal">
                                    <div>
                                        <Label required htmlFor="alamat">
                                            Alamat Lengkap
                                        </Label>
                                        <textarea
                                            id="alamat"
                                            className="min-h-[90px] w-full resize-y rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
                                            value={data.alamat}
                                            onChange={(e) => setData('alamat', e.target.value)}
                                            placeholder="Nama jalan, nomor rumah, blok, patokan"
                                        />
                                        {/* Disebut eksplisit "tempat tinggal", bukan sekadar "alamat":
                                            alamat KTP dan tempat tinggal sering beda, dan yang berguna
                                            buat sekolah yang kedua. */}
                                        <p className="mt-1 text-xs text-gray-500">
                                            Isi alamat tempat anak tinggal sehari-hari, walaupun berbeda dengan alamat di KTP.
                                        </p>
                                        <FieldError message={errors.alamat} />
                                    </div>

                                    <div className="mt-5 grid gap-5 md:grid-cols-2">
                                        {/* RT dan RW berdampingan dalam satu baris: dua-duanya
                                            cuma beberapa angka, dan orang memang menyebutnya
                                            sepasang ("RT 03 / RW 05"). */}
                                        <div className="grid grid-cols-2 gap-5">
                                            <div>
                                                <Label htmlFor="rt">
                                                    RT <span className="text-gray-500">(opsional)</span>
                                                </Label>
                                                <Input id="rt" value={data.rt} onChange={(v) => setData('rt', v)} placeholder="003" maxLength={5} />
                                                <FieldError message={errors.rt} />
                                            </div>
                                            <div>
                                                <Label htmlFor="rw">
                                                    RW <span className="text-gray-500">(opsional)</span>
                                                </Label>
                                                <Input id="rw" value={data.rw} onChange={(v) => setData('rw', v)} placeholder="005" maxLength={5} />
                                                <FieldError message={errors.rw} />
                                            </div>
                                        </div>

                                        <div>
                                            <Label required htmlFor="kelurahan">
                                                Kelurahan/Desa
                                            </Label>
                                            <Input
                                                id="kelurahan"
                                                value={data.kelurahan}
                                                onChange={(v) => setData('kelurahan', v)}
                                                placeholder="Sidomulyo Timur"
                                            />
                                            <FieldError message={errors.kelurahan} />
                                        </div>

                                        <div>
                                            <Label required htmlFor="kecamatan">
                                                Kecamatan
                                            </Label>
                                            <Input
                                                id="kecamatan"
                                                value={data.kecamatan}
                                                onChange={(v) => setData('kecamatan', v)}
                                                placeholder="Marpoyan Damai"
                                            />
                                            <FieldError message={errors.kecamatan} />
                                        </div>

                                        <div>
                                            <Label required htmlFor="kota_kabupaten">
                                                Kota/Kabupaten
                                            </Label>
                                            <Input
                                                id="kota_kabupaten"
                                                value={data.kota_kabupaten}
                                                onChange={(v) => setData('kota_kabupaten', v)}
                                                placeholder="Kota Pekanbaru"
                                            />
                                            <FieldError message={errors.kota_kabupaten} />
                                        </div>

                                        <div>
                                            <Label required htmlFor="provinsi">
                                                Provinsi
                                            </Label>
                                            <Input id="provinsi" value={data.provinsi} onChange={(v) => setData('provinsi', v)} placeholder="Riau" />
                                            <FieldError message={errors.provinsi} />
                                        </div>
                                    </div>
                                </Section>

                                <Section title="Asal Sekolah dan Sumber Informasi">
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <div>
                                            <Label required htmlFor="asal_paud">
                                                Asal TK/RA/PAUD
                                            </Label>
                                            <Select id="asal_paud" value={paudTerpilih} onChange={pilihPaud}>
                                                <option value="">Pilih sekolah asal</option>
                                                {/* Dua pilihan khusus ditaruh DI ATAS daftar sekolah:
                                                    daftarnya ratusan baris, dan kalau jalan keluarnya
                                                    ada di paling bawah, wali yang anaknya tidak lewat
                                                    PAUD harus menggulir seluruh daftar dulu. */}
                                                <option value={TANPA_PAUD}>Belum/tidak ikut PAUD</option>
                                                <option value={PAUD_LAINNYA}>Tidak ada di daftar — ketik sendiri</option>
                                                {asalPaud.map((kelompok) => (
                                                    <optgroup key={kelompok.jenis} label={kelompok.label}>
                                                        {kelompok.sekolah.map((s) => (
                                                            <option key={s.id} value={s.id}>
                                                                {s.nama}
                                                            </option>
                                                        ))}
                                                    </optgroup>
                                                ))}
                                            </Select>
                                            {paudTerpilih === PAUD_LAINNYA && (
                                                <div className="mt-2">
                                                    <Input
                                                        id="asal_paud_lainnya"
                                                        value={data.asal_paud_lainnya}
                                                        onChange={(v) => setData('asal_paud_lainnya', v)}
                                                        placeholder="Contoh: TK Nurul Ilmi"
                                                    />
                                                </div>
                                            )}
                                            <FieldError message={errors.asal_paud_id ?? errors.asal_paud_lainnya} />
                                        </div>

                                        <div>
                                            <Label htmlFor="tahu_dari">
                                                Tahu PPDB ini dari mana? <span className="text-gray-500">(opsional)</span>
                                            </Label>
                                            <Select
                                                id="tahu_dari"
                                                value={data.tahu_dari}
                                                onChange={(v) =>
                                                    setData((d) => ({
                                                        ...d,
                                                        tahu_dari: v,
                                                        tahu_dari_lainnya: v === 'lainnya' ? d.tahu_dari_lainnya : '',
                                                    }))
                                                }
                                            >
                                                <option value="">Tidak menjawab</option>
                                                {sumberInformasi.map((s) => (
                                                    <option key={s.nilai} value={s.nilai}>
                                                        {s.label}
                                                    </option>
                                                ))}
                                            </Select>
                                            {data.tahu_dari === 'lainnya' && (
                                                <div className="mt-2">
                                                    <Input
                                                        id="tahu_dari_lainnya"
                                                        value={data.tahu_dari_lainnya}
                                                        onChange={(v) => setData('tahu_dari_lainnya', v)}
                                                        placeholder="Sebutkan dari mana"
                                                    />
                                                </div>
                                            )}
                                            <FieldError message={errors.tahu_dari ?? errors.tahu_dari_lainnya} />
                                        </div>
                                    </div>

                                    <p className="mt-4 text-xs text-gray-500">
                                        Dua isian ini tidak memengaruhi hasil seleksi. Gunanya membantu sekolah mengetahui dari mana calon murid
                                        berasal.
                                    </p>
                                </Section>

                                {/* Data pendukung klaim kategori - tampil kondisional */}
                                {kategoriTerpilih?.nama === 'Saudara' && (
                                    <Section title="Data Pendukung: Saudara di Sekolah Ini">
                                        <Label htmlFor="nama_saudara">Nama Saudara</Label>
                                        <Input
                                            id="nama_saudara"
                                            value={data.nama_saudara}
                                            onChange={(v) => setData('nama_saudara', v)}
                                            placeholder="Nama saudara kandung yang terdaftar di sekolah ini"
                                        />
                                        <FieldError message={errors.nama_saudara} />
                                    </Section>
                                )}

                                {kategoriTerpilih?.nama === 'Anak Guru/Tenaga Kependidikan' && (
                                    <Section title="Data Pendukung: Orang Tua Guru/Tenaga Kependidikan">
                                        <Label htmlFor="nama_orang_tua_guru">Nama Orang Tua</Label>
                                        <Input
                                            id="nama_orang_tua_guru"
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
                                                    <Label required htmlFor={`wali_${index}_nama`}>
                                                        Nama
                                                    </Label>
                                                    <Input
                                                        id={`wali_${index}_nama`}
                                                        value={w.nama}
                                                        onChange={(v) => updateWaliMurid(index, 'nama', v)}
                                                        placeholder="Nama lengkap"
                                                    />
                                                    <FieldError message={errors[`wali_murid.${index}.nama`]} />
                                                </div>
                                                <div>
                                                    <Label required htmlFor={`wali_${index}_nik`}>
                                                        NIK
                                                    </Label>
                                                    <Input
                                                        id={`wali_${index}_nik`}
                                                        value={w.nik}
                                                        onChange={(v) => updateWaliMurid(index, 'nik', v)}
                                                        placeholder="16 digit NIK"
                                                        maxLength={16}
                                                    />
                                                    <FieldError message={errors[`wali_murid.${index}.nik`]} />
                                                </div>
                                                <div>
                                                    <Label required htmlFor={`wali_${index}_hubungan`}>
                                                        Hubungan
                                                    </Label>
                                                    <select
                                                        id={`wali_${index}_hubungan`}
                                                        className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
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
                                                    <Label required htmlFor={`wali_${index}_telepon`}>
                                                        No. WhatsApp Aktif
                                                    </Label>
                                                    <Input
                                                        id={`wali_${index}_telepon`}
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

                                <Button type="submit" disabled={processing} className="w-full rounded-xl py-3.5 text-[15px] font-bold">
                                    {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Simpan dan Lanjutkan ke Unggah Berkas'}
                                </Button>
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
                    )}
                </PageContainer>
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

/**
 * `htmlFor` menyambungkan label ke isiannya: mengklik tulisannya memindahkan
 * kursor ke kolomnya, dan pembaca layar menyebutkan namanya saat kolom itu
 * disorot. Tanpa itu, kolomnya cuma terbaca "edit text" tanpa keterangan.
 *
 * `id` dipakai untuk KELOMPOK isian (mis. radio jenis kelamin) yang tidak
 * punya satu kolom untuk ditunjuk - di sana kelompoknya yang menunjuk balik
 * ke label lewat aria-labelledby.
 */
function Label({ children, required, htmlFor, id }: { children: React.ReactNode; required?: boolean; htmlFor?: string; id?: string }) {
    return (
        <label id={id} htmlFor={htmlFor} className="mb-1.5 block text-[13px] font-medium text-gray-600">
            {children}
            {required && <span className="ml-0.5 text-red-500">*</span>}
        </label>
    );
}

// `id` sengaja WAJIB, bukan opsional: itu yang menyambungkan kolom ini ke
// labelnya. Kalau boleh dikosongkan, kolom yang ditambahkan orang berikutnya
// akan lupa lagi - sekarang TypeScript yang mengingatkan, bukan manusia.
function Input({
    id,
    value,
    onChange,
    placeholder,
    maxLength,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    maxLength?: number;
}) {
    return (
        <input
            id={id}
            type="text"
            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            maxLength={maxLength}
        />
    );
}

/**
 * Daftar pilihan. Sengaja `<select>` bawaan, bukan combobox dengan pencarian.
 *
 * Daftar terpanjangnya sekolah asal - 634 PAUD se-Pekanbaru. Panjang, tapi masih
 * terpakai karena dua hal: isinya dipecah `<optgroup>` per jenis (TK/RA/KB/...)
 * dan browser sendiri membolehkan mengetik huruf awal untuk melompat.
 * Combobox buatan sendiri berarti menangani papan ketik, fokus, dan pembaca
 * layar sendirian; itu ongkos yang baru pantas dibayar kalau daftarnya sudah
 * ribuan baris.
 *
 * `id` wajib dengan alasan yang sama seperti di Input: itu yang menyambungkan
 * kolom ini ke labelnya, dan TypeScript yang menahannya - bukan ingatan orang.
 */
function Select({
    id,
    value,
    onChange,
    disabled,
    children,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    children: React.ReactNode;
}) {
    return (
        <select
            id={id}
            value={value}
            disabled={disabled}
            onChange={(e) => onChange(e.target.value)}
            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none disabled:cursor-not-allowed disabled:text-gray-500"
        >
            {children}
        </select>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}
