import { Head, useForm } from '@inertiajs/react';
import { Button, Field, inputClass } from '@/Components/ui';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    return (
        <div className="grid min-h-screen place-items-center p-4">
            <Head title="Setel ulang kata sandi" />

            <form
                onSubmit={(event) => { event.preventDefault(); post('/reset-password'); }}
                className="w-full max-w-sm rounded-lg border border-line bg-surface p-6"
            >
                <h1 className="mb-4 text-2xl font-semibold">Setel ulang kata sandi</h1>

                <Field label="Email" error={errors.email}>
                    <input type="email" name="email" autoComplete="username" className={inputClass} value={data.email}
                           onChange={(e) => setData('email', e.target.value)} required />
                </Field>
                <Field label="Kata sandi baru" error={errors.password}>
                    <input type="password" name="password" autoComplete="new-password" className={inputClass} value={data.password}
                           onChange={(e) => setData('password', e.target.value)} required autoFocus />
                </Field>
                <Field label="Ulangi kata sandi baru">
                    <input type="password" name="password_confirmation" autoComplete="new-password" className={inputClass}
                           value={data.password_confirmation}
                           onChange={(e) => setData('password_confirmation', e.target.value)} required />
                </Field>

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>Simpan kata sandi</Button>
            </form>
        </div>
    );
}
