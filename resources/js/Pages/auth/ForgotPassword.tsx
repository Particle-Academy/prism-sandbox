import { Link, useForm, usePage } from '@inertiajs/react';
import { Button, Callout, Input } from '@particle-academy/react-fancy';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/layouts/AuthLayout';

export default function ForgotPassword() {
    const status = usePage<{ status?: string }>().props.status;
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout title="Forgot password" description="We'll email you a reset link.">
            {status && (
                <Callout color="emerald" className="mb-4 text-sm">
                    {status}
                </Callout>
            )}
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Input
                    label="Email"
                    type="email"
                    autoComplete="email"
                    required
                    value={data.email}
                    onValueChange={(v) => setData('email', v)}
                    error={errors.email}
                />
                <Button type="submit" color="violet" loading={processing} className="justify-center">
                    Email reset link
                </Button>
            </form>
            <div className="mt-4 text-center text-sm text-zinc-500">
                <Link href="/login" className="font-medium text-violet-500 hover:underline">
                    Back to log in
                </Link>
            </div>
        </AuthLayout>
    );
}
