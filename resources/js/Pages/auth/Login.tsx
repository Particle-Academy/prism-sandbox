import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, Input } from '@particle-academy/react-fancy';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/layouts/AuthLayout';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout title="Log in" description="Welcome back.">
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
                <Input
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={data.password}
                    onValueChange={(v) => setData('password', v)}
                    error={errors.password}
                />
                <Checkbox
                    label="Remember me"
                    checked={data.remember}
                    onCheckedChange={(v) => setData('remember', v)}
                />
                <Button type="submit" color="violet" loading={processing} className="justify-center">
                    Log in
                </Button>
            </form>
            <div className="mt-4 flex items-center justify-between text-sm text-zinc-500">
                <Link href="/forgot-password" className="hover:underline">
                    Forgot password?
                </Link>
                <Link href="/register" className="font-medium text-violet-500 hover:underline">
                    Create account
                </Link>
            </div>
        </AuthLayout>
    );
}
