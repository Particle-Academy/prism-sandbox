import { useForm } from '@inertiajs/react';
import { Button, Callout, Input } from '@particle-academy/react-fancy';
import type { FormEvent } from 'react';
import { SettingsLayout } from '@/layouts/SettingsLayout';

export default function Password() {
    const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/user/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <SettingsLayout title="Password">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Input
                    label="Current password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={data.current_password}
                    onValueChange={(v) => setData('current_password', v)}
                    error={errors.current_password}
                />
                <Input
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password}
                    onValueChange={(v) => setData('password', v)}
                    error={errors.password}
                />
                <Input
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    onValueChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                />
                <div className="flex items-center gap-3">
                    <Button type="submit" color="violet" loading={processing}>
                        Update password
                    </Button>
                    {recentlySuccessful && (
                        <Callout color="emerald" className="!py-1 text-sm">
                            Updated.
                        </Callout>
                    )}
                </div>
            </form>
        </SettingsLayout>
    );
}
