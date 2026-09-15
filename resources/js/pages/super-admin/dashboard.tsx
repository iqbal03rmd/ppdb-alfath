import PageBanner from '@/components/page-banner';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    Database,
    FileCheck2,
    Files,
    Landmark,
    Settings2,
    ShieldCheck,
    Tags,
    UserRoundCog,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';

interface GelombangRingkas {
    nama: string;
    keadaan: string;
    label_keadaan: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
}

interface BagianKesiapan {
    kunci: string;
    nama: string;
    siap: boolean;
    keterangan: string;
}

interface DashboardProps {
    ringkasan: {
        pengguna_aktif: number;
        pengguna_nonaktif: number;
        tahun_ajaran: string | null;
        gelombang: GelombangRingkas | null;
        master_aktif: {
            komponen_biaya: number;
            berkas_persyaratan: number;
            jalur: number;
        };
        bagian_siap: number;
        total_bagian: number;
    };
    kesiapan: BagianKesiapan[];
    penggunaPeran: Record<'wali_murid' | 'staf_ppdb' | 'kepala_sekolah' | 'super_admin', number>;
}

const tujuanBagian: Record<string, string> = {
    tahun_ajaran: 'super-admin.tahun-ajaran.index',
    komponen_biaya: 'super-admin.komponen-biaya.index',
    berkas_persyaratan: 'super-admin.berkas-persyaratan.index',
    jalur: 'super-admin.jalur.index',
    gelombang: 'super-admin.gelombang.index',
    pengaturan_sistem: 'super-admin.pengaturan-sistem.edit',
};

const ikonBagian: Record<string, ReactNode> = {
    tahun_ajaran: <CalendarDays size={18} strokeWidth={1.9} />,
    komponen_biaya: <Tags size={18} strokeWidth={1.9} />,
    berkas_persyaratan: <Files size={18} strokeWidth={1.9} />,
    jalur: <FileCheck2 size={18} strokeWidth={1.9} />,
    gelombang: <Database size={18} strokeWidth={1.9} />,
    pengaturan_sistem: <Landmark size={18} strokeWidth={1.9} />,
};

const labelPeran = {
    wali_murid: 'Wali Murid',
    staf_ppdb: 'Staf PPDB',
    kepala_sekolah: 'Kepala Sekolah',
    super_admin: 'Super Admin',
};

function KartuRingkasan({
    ikon,
    judul,
    nilai,
    keterangan,
    tautan,
}: {
    ikon: ReactNode;
    judul: string;
    nilai: ReactNode;
    keterangan: string;
    tautan: string;
}) {
    return (
        <Link
            href={tautan}
            className="group flex min-h-40 flex-col rounded-2xl bg-white p-5 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] transition-transform hover:-translate-y-0.5"
        >
            <div className="flex items-start justify-between gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#D4EBF8]/70 text-[#0A3981]">{ikon}</span>
                <ArrowRight size={17} className="mt-1 text-gray-500 transition-transform group-hover:translate-x-1 group-hover:text-[#1F509A]" />
            </div>
            <p className="mt-4 text-sm font-semibold text-gray-600">{judul}</p>
            <div className="mt-1 text-2xl font-bold text-[#0A3981]">{nilai}</div>
            <p className="mt-1 text-xs leading-5 text-gray-500">{keterangan}</p>
        </Link>
    );
}

export default function Dashboard({ ringkasan, kesiapan, penggunaPeran }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const namaDepan = String(auth.user?.name ?? '').split(' ')[0];
    const belumSiap = ringkasan.total_bagian - ringkasan.bagian_siap;
    const tanggalHariIni = new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const subjudul =
        belumSiap === 0 ? 'Seluruh kebutuhan dasar sistem PPDB sudah tersedia.' : `${belumSiap} bagian konfigurasi masih perlu dilengkapi.`;

    return (
        <AppLayout>
            <Head title="Beranda" />

            <PageBanner
                ikon={<ShieldCheck size={118} strokeWidth={1} />}
                tanggal={tanggalHariIni}
                judul={`Assalamu'alaikum, ${namaDepan}`}
                subjudul={subjudul}
                stripVarian={ringkasan.gelombang?.keadaan === 'menerima' ? 'biru' : 'abu'}
                strip={
                    ringkasan.gelombang ? (
                        <p className="text-sm text-[#0A3981]">
                            <b>{ringkasan.gelombang.nama}</b> · {ringkasan.gelombang.label_keadaan} ({ringkasan.gelombang.tanggal_mulai}–
                            {ringkasan.gelombang.tanggal_selesai}).
                        </p>
                    ) : (
                        <p className="text-sm text-gray-600">
                            Belum ada gelombang pada tahun ajaran berjalan. Siapkan gelombang sebelum pendaftaran dibuka.
                        </p>
                    )
                }
            />

            <PageContainer wide>
                <div className="space-y-6 pt-6">
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <KartuRingkasan
                            ikon={<Users size={19} strokeWidth={1.9} />}
                            judul="Pengguna Aktif"
                            nilai={ringkasan.pengguna_aktif}
                            keterangan={
                                ringkasan.pengguna_nonaktif > 0
                                    ? `${ringkasan.pengguna_nonaktif} akun sedang dinonaktifkan.`
                                    : 'Semua akun saat ini aktif.'
                            }
                            tautan={route('super-admin.pengguna.index')}
                        />
                        <KartuRingkasan
                            ikon={<CalendarDays size={19} strokeWidth={1.9} />}
                            judul="Tahun Ajaran Berjalan"
                            nilai={ringkasan.tahun_ajaran ?? 'Belum ada'}
                            keterangan={
                                ringkasan.tahun_ajaran ? 'Menjadi acuan laporan dan gelombang aktif.' : 'Tentukan satu tahun ajaran berjalan.'
                            }
                            tautan={route('super-admin.tahun-ajaran.index')}
                        />
                        <KartuRingkasan
                            ikon={<Database size={19} strokeWidth={1.9} />}
                            judul="Gelombang PPDB"
                            nilai={ringkasan.gelombang?.nama ?? 'Belum ada'}
                            keterangan={ringkasan.gelombang?.label_keadaan ?? 'Buat gelombang pada tahun ajaran berjalan.'}
                            tautan={route('super-admin.gelombang.index')}
                        />
                        <KartuRingkasan
                            ikon={<CheckCircle2 size={19} strokeWidth={1.9} />}
                            judul="Kesiapan Sistem"
                            nilai={
                                <>
                                    {ringkasan.bagian_siap}
                                    <span className="text-base font-semibold text-gray-500">/{ringkasan.total_bagian} bagian</span>
                                </>
                            }
                            keterangan={belumSiap === 0 ? 'Kebutuhan dasar PPDB sudah lengkap.' : `${belumSiap} bagian masih perlu perhatian.`}
                            tautan={`${route('super-admin.dashboard')}#kesiapan`}
                        />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-[1.45fr_0.55fr]">
                        <section
                            id="kesiapan"
                            className="scroll-mt-5 rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-base font-bold text-gray-900">Kesiapan Konfigurasi PPDB</h2>
                                    <p className="mt-1 text-sm text-gray-500">Periksa bahan dasar sebelum pendaftaran dibuka kepada wali murid.</p>
                                </div>
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#F5F9FD] text-[#1F509A]">
                                    <Settings2 size={20} strokeWidth={1.8} />
                                </span>
                            </div>

                            <div className="mt-5 divide-y divide-[#E7EEF5]">
                                {kesiapan.map((bagian) => (
                                    <div key={bagian.kunci} className="flex items-center gap-3 py-3.5 first:pt-0 last:pb-0">
                                        <span
                                            className={
                                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ' +
                                                (bagian.siap ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700')
                                            }
                                        >
                                            {ikonBagian[bagian.kunci]}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="text-sm font-semibold text-gray-900">{bagian.nama}</h3>
                                                <span
                                                    className={
                                                        'rounded-full px-2 py-0.5 text-xs font-semibold ' +
                                                        (bagian.siap ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800')
                                                    }
                                                >
                                                    {bagian.siap ? 'Siap' : 'Perlu dilengkapi'}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-xs leading-5 text-gray-500 sm:text-sm">{bagian.keterangan}</p>
                                        </div>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                            className="shrink-0 rounded-xl font-bold text-[#1F509A] hover:bg-[#F5F9FD]"
                                        >
                                            <Link href={route(tujuanBagian[bagian.kunci])}>
                                                Buka <ArrowRight size={15} />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)]">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-base font-bold text-gray-900">Pengguna per Peran</h2>
                                    <p className="mt-1 text-sm text-gray-500">Seluruh akun, termasuk yang dinonaktifkan.</p>
                                </div>
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#F5F9FD] text-[#1F509A]">
                                    <UserRoundCog size={20} strokeWidth={1.8} />
                                </span>
                            </div>

                            <div className="mt-5 space-y-3">
                                {Object.entries(labelPeran).map(([peran, label]) => (
                                    <div key={peran} className="flex items-center justify-between rounded-xl bg-[#F5F9FD] px-4 py-3">
                                        <span className="text-sm font-medium text-gray-700">{label}</span>
                                        <span className="text-lg font-bold text-[#0A3981]">{penggunaPeran[peran as keyof typeof penggunaPeran]}</span>
                                    </div>
                                ))}
                            </div>

                            {ringkasan.pengguna_nonaktif > 0 && (
                                <div className="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">
                                    <CircleAlert size={16} className="mt-0.5 shrink-0" />
                                    {ringkasan.pengguna_nonaktif} akun tidak memiliki akses masuk saat ini.
                                </div>
                            )}

                            <Button
                                asChild
                                variant="outline"
                                className="mt-5 w-full rounded-xl border-[#1F509A]/40 font-bold text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                            >
                                <Link href={route('super-admin.pengguna.index')}>Kelola Pengguna</Link>
                            </Button>
                        </section>
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
