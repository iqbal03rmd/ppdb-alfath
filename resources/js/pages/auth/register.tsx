import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type RegisterForm = {
    name: string;
    email: string;
    telepon: string;
    persetujuan_whatsapp: boolean;
    password: string;
    password_confirmation: string;
};

export default function Register() {
    const { sistem } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        telepon: '',
        persetujuan_whatsapp: false,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Daftar Akun Wali" description="Lengkapi data berikut untuk membuat akun PPDB">
            <Head title="Daftar Akun" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nama Lengkap</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                            placeholder="Nama lengkap wali"
                        />
                        <InputError message={errors.name} />
                    </div>

                    {/* Email + No. WhatsApp sebaris */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                required
                                tabIndex={2}
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                disabled={processing}
                                placeholder="Masukkan alamat email"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="telepon">No. WhatsApp Aktif</Label>
                            <Input
                                id="telepon"
                                type="tel"
                                required
                                tabIndex={3}
                                autoComplete="tel"
                                value={data.telepon}
                                onChange={(e) => setData('telepon', e.target.value)}
                                disabled={processing}
                                placeholder="08xxxxxxxxxx"
                            />
                            <InputError message={errors.telepon} />
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl border border-gray-200 bg-[#F5F9FD] p-4">
                        <Checkbox
                            id="persetujuan_whatsapp"
                            checked={data.persetujuan_whatsapp}
                            onCheckedChange={(checked) => setData('persetujuan_whatsapp', checked === true)}
                            disabled={processing}
                            tabIndex={4}
                        />
                        <div className="min-w-0">
                            <label htmlFor="persetujuan_whatsapp" className="cursor-pointer text-sm font-medium text-gray-800">
                                Saya bersedia menerima pembaruan proses PPDB {sistem.nama_sekolah} melalui WhatsApp.
                            </label>
                            <p className="mt-1 text-xs text-gray-500">Notifikasi dapat dinonaktifkan kembali melalui Pengaturan akun.</p>
                            <InputError message={errors.persetujuan_whatsapp} />
                        </div>
                    </div>

                    {/* Password + Konfirmasi sebaris */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                required
                                tabIndex={5}
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                disabled={processing}
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                tabIndex={6}
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                disabled={processing}
                                placeholder="Ulangi password"
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>
                    </div>

                    <Button type="submit" className="mt-1 w-full" tabIndex={7} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Daftar
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    Sudah punya akun?{' '}
                    <TextLink href={route('login')} tabIndex={8}>
                        Masuk
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
