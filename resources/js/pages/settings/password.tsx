import { FieldError, Input, Kartu, Label } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Password() {
    const { data, setData, errors, put, reset, processing } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            // Kolom yang salah dikosongkan lagi, bukan dibiarkan terisi:
            // kata sandi tidak terbaca di layar, jadi membetulkan ketikan yang
            // tidak kelihatan lebih menyusahkan daripada mengetik ulang.
            onError: (errors) => {
                if (errors.password) reset('password', 'password_confirmation');
                if (errors.current_password) reset('current_password');
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Ganti Kata Sandi" />

            <SettingsLayout
                samping={
                    <Kartu judul="Lupa Kata Sandi?">
                        <p className="text-sm text-gray-500">
                            Kolom pertama butuh kata sandi Anda yang sekarang. Kalau sudah tidak ingat, minta Super Admin mengaturkan yang baru.
                        </p>
                    </Kartu>
                }
            >
                <form onSubmit={submit} className="space-y-6">
                    <Kartu judul="Ganti Kata Sandi">
                        <div className="mb-5">
                            <Label required htmlFor="current_password">
                                Kata Sandi Sekarang
                            </Label>
                            <Input
                                id="current_password"
                                type="password"
                                value={data.current_password}
                                onChange={(v) => setData('current_password', v)}
                                autoComplete="current-password"
                            />
                            <FieldError message={errors.current_password} />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label required htmlFor="password">
                                    Kata Sandi Baru
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(v) => setData('password', v)}
                                    placeholder="Minimal 8 karakter"
                                    autoComplete="new-password"
                                />
                                <FieldError message={errors.password} />
                            </div>
                            <div>
                                <Label required htmlFor="password_confirmation">
                                    Ulangi Kata Sandi Baru
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(v) => setData('password_confirmation', v)}
                                    autoComplete="new-password"
                                />
                                <FieldError message={errors.password_confirmation} />
                            </div>
                        </div>
                    </Kartu>

                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#E38E49] font-semibold text-white hover:bg-[#E38E49]/90">
                        {processing ? 'Menyimpan...' : 'Simpan Kata Sandi'}
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
