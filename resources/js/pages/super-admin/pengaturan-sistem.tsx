import ConfirmationDialog from '@/components/confirmation-dialog';
import { FieldError, Input, Kartu, Label } from '@/components/form-field';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { PendingVisit, VisitOptions } from '@inertiajs/core';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface Pengaturan {
    nama_sekolah: string;
    tagline: string | null;
    alamat: string | null;
    telepon: string | null;
    email: string | null;
    nama_bank: string | null;
    nomor_rekening: string | null;
    nama_pemilik_rekening: string | null;
    instruksi_pembayaran: string | null;
    judul_landing: string;
    deskripsi_landing: string;
    pengumuman_landing: string | null;
    whatsapp_kontak: string | null;
}

const gayaTeksArea =
    'min-h-28 w-full resize-y rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none';

function TeksArea({ id, value, onChange, placeholder }: { id: string; value: string; onChange: (value: string) => void; placeholder?: string }) {
    return <textarea id={id} value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className={gayaTeksArea} />;
}

export default function PengaturanSistem({ pengaturan }: { pengaturan: Pengaturan }) {
    const { data, setData, put, processing, errors, isDirty, setDefaults } = useForm({
        nama_sekolah: pengaturan.nama_sekolah,
        tagline: pengaturan.tagline ?? '',
        alamat: pengaturan.alamat ?? '',
        telepon: pengaturan.telepon ?? '',
        email: pengaturan.email ?? '',
        nama_bank: pengaturan.nama_bank ?? '',
        nomor_rekening: pengaturan.nomor_rekening ?? '',
        nama_pemilik_rekening: pengaturan.nama_pemilik_rekening ?? '',
        instruksi_pembayaran: pengaturan.instruksi_pembayaran ?? '',
        judul_landing: pengaturan.judul_landing,
        deskripsi_landing: pengaturan.deskripsi_landing,
        pengumuman_landing: pengaturan.pengumuman_landing ?? '',
        whatsapp_kontak: pengaturan.whatsapp_kontak ?? '',
    });
    const [kunjunganTertunda, setKunjunganTertunda] = useState<PendingVisit | null>(null);
    const sedangMenyimpan = useRef(false);
    const lewatiPengamanSekali = useRef(false);

    useEffect(() => {
        const cegahTutupBrowser = (event: BeforeUnloadEvent) => {
            if (!isDirty || sedangMenyimpan.current) return;

            event.preventDefault();
            event.returnValue = '';
        };
        const lepasPengamanInertia = router.on('before', (event) => {
            const kunjungan = event.detail.visit;

            if (kunjungan.prefetch || !isDirty || sedangMenyimpan.current) return;

            if (lewatiPengamanSekali.current) {
                lewatiPengamanSekali.current = false;

                return;
            }

            setKunjunganTertunda(kunjungan);

            return false;
        });

        window.addEventListener('beforeunload', cegahTutupBrowser);

        return () => {
            lepasPengamanInertia();
            window.removeEventListener('beforeunload', cegahTutupBrowser);
        };
    }, [isDirty]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        sedangMenyimpan.current = true;

        put(route('super-admin.pengaturan-sistem.update'), {
            preserveScroll: true,
            onSuccess: () => setDefaults(data),
            onFinish: () => {
                sedangMenyimpan.current = false;
            },
        });
    };

    const tinggalkanHalaman = () => {
        if (!kunjunganTertunda) return;

        const kunjungan = kunjunganTertunda;
        const opsi: VisitOptions = {
            method: kunjungan.method,
            data: kunjungan.data,
            replace: kunjungan.replace,
            preserveScroll: kunjungan.preserveScroll,
            preserveState: kunjungan.preserveState,
            only: kunjungan.only,
            except: kunjungan.except,
            headers: kunjungan.headers,
            errorBag: kunjungan.errorBag,
            forceFormData: kunjungan.forceFormData,
            queryStringArrayFormat: kunjungan.queryStringArrayFormat,
            async: kunjungan.async,
            showProgress: kunjungan.showProgress,
            fresh: kunjungan.fresh,
            reset: kunjungan.reset,
            preserveUrl: kunjungan.preserveUrl,
        };

        setKunjunganTertunda(null);
        lewatiPengamanSekali.current = true;
        router.visit(kunjungan.url, opsi);
    };

    return (
        <AppLayout>
            <Head title="Pengaturan Sistem" />
            <PageHeader title="Pengaturan Sistem" subtitle="Identitas sekolah, tujuan pembayaran, dan konten landing page" wide />

            <PageContainer wide>
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Kartu judul="Identitas Sekolah">
                            <div className="space-y-5">
                                <div>
                                    <Label required htmlFor="nama_sekolah">
                                        Nama Sekolah
                                    </Label>
                                    <Input id="nama_sekolah" value={data.nama_sekolah} onChange={(value) => setData('nama_sekolah', value)} />
                                    <FieldError message={errors.nama_sekolah} />
                                </div>

                                <div>
                                    <Label htmlFor="tagline">Tagline</Label>
                                    <Input
                                        id="tagline"
                                        value={data.tagline}
                                        onChange={(value) => setData('tagline', value)}
                                        placeholder="Kalimat singkat yang menggambarkan sekolah"
                                    />
                                    <FieldError message={errors.tagline} />
                                </div>

                                <div>
                                    <Label htmlFor="alamat">Alamat</Label>
                                    <TeksArea id="alamat" value={data.alamat} onChange={(value) => setData('alamat', value)} />
                                    <FieldError message={errors.alamat} />
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="telepon">Telepon Sekolah</Label>
                                        <Input
                                            id="telepon"
                                            value={data.telepon}
                                            onChange={(value) => setData('telepon', value)}
                                            placeholder="0761..."
                                        />
                                        <FieldError message={errors.telepon} />
                                    </div>
                                    <div>
                                        <Label htmlFor="email">Email Sekolah</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(value) => setData('email', value)}
                                            placeholder="info@sekolah.sch.id"
                                        />
                                        <FieldError message={errors.email} />
                                    </div>
                                </div>
                            </div>
                        </Kartu>

                        <Kartu judul="Informasi Pembayaran">
                            <div className="space-y-5">
                                <div>
                                    <Label htmlFor="nama_bank">Nama Bank</Label>
                                    <Input
                                        id="nama_bank"
                                        value={data.nama_bank}
                                        onChange={(value) => setData('nama_bank', value)}
                                        placeholder="Contoh: Bank Syariah Indonesia"
                                    />
                                    <FieldError message={errors.nama_bank} />
                                </div>

                                <div>
                                    <Label htmlFor="nomor_rekening">Nomor Rekening</Label>
                                    <Input id="nomor_rekening" value={data.nomor_rekening} onChange={(value) => setData('nomor_rekening', value)} />
                                    <FieldError message={errors.nomor_rekening} />
                                </div>

                                <div>
                                    <Label htmlFor="nama_pemilik_rekening">Nama Pemilik Rekening</Label>
                                    <Input
                                        id="nama_pemilik_rekening"
                                        value={data.nama_pemilik_rekening}
                                        onChange={(value) => setData('nama_pemilik_rekening', value)}
                                    />
                                    <FieldError message={errors.nama_pemilik_rekening} />
                                </div>

                                <div>
                                    <Label htmlFor="instruksi_pembayaran">Instruksi Pembayaran</Label>
                                    <TeksArea
                                        id="instruksi_pembayaran"
                                        value={data.instruksi_pembayaran}
                                        onChange={(value) => setData('instruksi_pembayaran', value)}
                                        placeholder="Contoh: Cantumkan nomor pendaftaran pada berita transfer."
                                    />
                                    <FieldError message={errors.instruksi_pembayaran} />
                                </div>
                            </div>
                        </Kartu>
                    </div>

                    <Kartu judul="Konten Landing Page">
                        <div className="grid gap-5 lg:grid-cols-2">
                            <div>
                                <Label required htmlFor="judul_landing">
                                    Judul Utama
                                </Label>
                                <Input id="judul_landing" value={data.judul_landing} onChange={(value) => setData('judul_landing', value)} />
                                <FieldError message={errors.judul_landing} />
                            </div>

                            <div>
                                <Label htmlFor="whatsapp_kontak">WhatsApp Informasi</Label>
                                <Input
                                    id="whatsapp_kontak"
                                    value={data.whatsapp_kontak}
                                    onChange={(value) => setData('whatsapp_kontak', value)}
                                    placeholder="08xxxxxxxxxx"
                                />
                                <FieldError message={errors.whatsapp_kontak} />
                            </div>

                            <div>
                                <Label required htmlFor="deskripsi_landing">
                                    Deskripsi Utama
                                </Label>
                                <TeksArea
                                    id="deskripsi_landing"
                                    value={data.deskripsi_landing}
                                    onChange={(value) => setData('deskripsi_landing', value)}
                                />
                                <FieldError message={errors.deskripsi_landing} />
                            </div>

                            <div>
                                <Label htmlFor="pengumuman_landing">Pengumuman</Label>
                                <TeksArea
                                    id="pengumuman_landing"
                                    value={data.pengumuman_landing}
                                    onChange={(value) => setData('pengumuman_landing', value)}
                                    placeholder="Kosongkan kalau tidak ada pengumuman khusus."
                                />
                                <FieldError message={errors.pengumuman_landing} />
                            </div>
                        </div>
                    </Kartu>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={processing || !isDirty}
                            className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </form>
            </PageContainer>

            <ConfirmationDialog
                open={kunjunganTertunda !== null}
                title="Perubahan belum disimpan"
                description="Perubahan pada Pengaturan Sistem akan hilang jika halaman ditinggalkan."
                confirmLabel="Tinggalkan halaman"
                cancelLabel="Tetap di sini"
                tone="warning"
                onConfirm={tinggalkanHalaman}
                onCancel={() => setKunjunganTertunda(null)}
            />
        </AppLayout>
    );
}
