import { useForm } from '@inertiajs/react';
import { Button, Input } from '@particle-academy/react-fancy';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/layouts/AuthLayout';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/reset-password', { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Reset password" description="Choose a new password.">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Input label="Email" type="email" autoComplete="email" required value={data.email} onValueChange={(v) => setData('email', v)} error={errors.email} />
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
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    onValueChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                />
                <Button type="submit" color="violet" loading={processing} className="justify-center">
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
