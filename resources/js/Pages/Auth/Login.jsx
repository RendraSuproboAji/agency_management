import { Head, useForm } from '@inertiajs/react';
import { Button, Field, inputClass } from '@/Components/ui';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });

    return (
        <div className="grid min-h-screen place-items-center p-4">
            <Head title="Masuk" />

            <form
                onSubmit={(event) => { event.preventDefault(); post('/login'); }}
                className="w-full max-w-sm rounded-lg border border-line bg-surface p-6"
            >
                <h1 className="text-2xl font-semibold">Agency Management</h1>
                <p className="mb-4 text-sm text-muted">Manajemen jasa immersive 3D reconstruction</p>

                <Field label="Email" error={errors.email}>
                    <input type="email" className={inputClass} value={data.email}
                           onChange={(e) => setData('email', e.target.value)} required autoFocus />
                </Field>
                <Field label="Kata sandi" error={errors.password}>
                    <input type="password" className={inputClass} value={data.password}
                           onChange={(e) => setData('password', e.target.value)} required />
                </Field>
                <label className="mb-4 flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                    Ingat saya
                </label>

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>Masuk</Button>
            </form>
        </div>
    );
}
