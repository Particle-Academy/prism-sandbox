import { useForm, usePage } from '@inertiajs/react';
import { Button, Callout, Input } from '@particle-academy/react-fancy';
import type { FormEvent } from 'react';
import { SettingsLayout } from '@/layouts/SettingsLayout';

type Auth = { user: { name: string; email: string } | null };

export default function Profile() {
    const user = usePage<{ auth: Auth }>().props.auth?.user;
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/user/profile-information', { preserveScroll: true });
    };

    return (
        <SettingsLayout title="Profile">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Input label="Name" required value={data.name} onValueChange={(v) => setData('name', v)} error={errors.name} />
                <Input label="Email" type="email" required value={data.email} onValueChange={(v) => setData('email', v)} error={errors.email} />
                <div className="flex items-center gap-3">
                    <Button type="submit" color="violet" loading={processing}>
                        Save
                    </Button>
                    {recentlySuccessful && (
                        <Callout color="emerald" className="!py-1 text-sm">
                            Saved.
                        </Callout>
                    )}
                </div>
            </form>
        </SettingsLayout>
    );
}
