import { Link, useForm } from '@inertiajs/react';
import { Button, Input } from '@particle-academy/react-fancy';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/layouts/AuthLayout';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/register', { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Create your account" description="Start building with Fancy UI.">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Input
                    label="Name"
                    autoComplete="name"
                    required
                    value={data.name}
                    onValueChange={(v) => setData('name', v)}
                    error={errors.name}
                />
                <Input
                    label="Email"
                    type="email"
                    autoComplete="email"
                    required
                    value={data.email}
                    onValueChange={(v) => setData('email', v)}
                    error={errors.email}
                />
                <Input
                    label="Password"
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
                    Create account
                </Button>
            </form>
            <div className="mt-4 text-center text-sm text-zinc-500">
                Already have an account?{' '}
                <Link href="/login" className="font-medium text-violet-500 hover:underline">
                    Log in
                </Link>
            </div>
        </AuthLayout>
    );
}
