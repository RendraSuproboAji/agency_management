import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Field, inputClass } from '@/Components/ui';

export default function ForgotPassword() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    return (
        <div className="grid min-h-screen place-items-center p-4">
            <Head title="Lupa kata sandi" />

            <form
                onSubmit={(event) => { event.preventDefault(); post('/portal/forgot-password'); }}
                className="w-full max-w-sm rounded-lg border border-line bg-surface p-6"
            >
                <h1 className="text-2xl font-semibold">Lupa kata sandi</h1>
                <p className="mb-4 text-sm text-muted">Portal klien</p>

                {flash?.status && <p className="mb-4 text-sm text-ok">{flash.status}</p>}

                <Field label="Email" error={errors.email}>
                    <input type="email" name="email" autoComplete="username" className={inputClass} value={data.email}
                           onChange={(e) => setData('email', e.target.value)} required autoFocus />
                </Field>

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    Kirim tautan setel ulang
                </Button>

                <p className="mt-4 text-center text-xs">
                    <Link href="/portal/login" className="text-accent">Kembali ke halaman masuk</Link>
                </p>
            </form>
        </div>
    );
}
