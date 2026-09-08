import { FieldError, Input, Kartu, Label } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const peranLabel: Record<string, string> = {
    wali_murid: 'Wali Murid',
    staf_ppdb: 'Staf PPDB',
    kepala_sekolah: 'Kepala Sekolah',
    super_admin: 'Super Admin',
};

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing } = useForm({
        name: auth.user.name,
        email: auth.user.email,
        // ?? '' karena kolomnya nullable di database, sedangkan kotak isian
        // terkendali di React tidak boleh menerima null.
        telepon: auth.user.telepon ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <AppLayout>
            <Head title="Pengaturan Akun" />

            <SettingsLayout
                samping={
                    /* Peran ditampilkan sebagai keterangan, bukan isian - dan
                       kalimat berikutnya menyebut ke mana harus minta kalau mau
                       diubah, supaya jalan buntunya tidak dibiarkan buntu. */
                    <Kartu judul="Peran Anda">
                        <p className="text-sm font-semibold text-[#0A3981]">{peranLabel[auth.user.role] ?? auth.user.role}</p>
                        <p className="mt-1.5 text-xs text-gray-500">Hanya Super Admin yang bisa mengubah peran.</p>
                    </Kartu>
                }
            >
                <form onSubmit={submit} className="space-y-6">
                    <Kartu judul="Data Diri">
                        <div className="mb-5">
                            <Label required htmlFor="name">
                                Nama Lengkap
                            </Label>
                            <Input id="name" value={data.name} onChange={(v) => setData('name', v)} autoComplete="name" />
                            <FieldError message={errors.name} />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label required htmlFor="email">
                                    Email
                                </Label>
                                <Input id="email" type="email" value={data.email} onChange={(v) => setData('email', v)} autoComplete="username" />
                                <p className="mt-1 text-xs text-gray-500">Dipakai untuk masuk.</p>
                                <FieldError message={errors.email} />
                            </div>
                            <div>
                                <Label htmlFor="telepon">
                                    Telepon <span className="text-gray-500">(opsional)</span>
                                </Label>
                                <Input
                                    id="telepon"
                                    value={data.telepon}
                                    onChange={(v) => setData('telepon', v)}
                                    placeholder="08xxxxxxxxxx"
                                    autoComplete="tel"
                                />
                                <p className="mt-1 text-xs text-gray-500">Dipakai sekolah kalau perlu menghubungi Anda.</p>
                                <FieldError message={errors.telepon} />
                            </div>
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div className="mt-5 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                                Email Anda belum diverifikasi.{' '}
                                <Link href={route('verification.send')} method="post" as="button" className="font-semibold underline">
                                    Kirim ulang tautan verifikasi
                                </Link>
                                {status === 'verification-link-sent' && (
                                    <span className="mt-1 block font-semibold text-green-700">Tautan verifikasi baru sudah dikirim.</span>
                                )}
                            </div>
                        )}
                    </Kartu>

                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                        {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
