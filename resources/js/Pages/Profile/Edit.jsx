import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Field, PageHead, Panel, inputClass } from '@/Components/ui';

export default function Edit({ profile }) {
    const details = useForm({ name: profile.name, email: profile.email });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <AppLayout title="Profil saya">
            <PageHead title="Profil saya" subtitle="Ubah data dan kata sandi Anda sendiri" />

            <Panel title="Data diri">
                <form onSubmit={(e) => { e.preventDefault(); details.put('/profile'); }}>
                    <div className="gap-x-4 sm:grid sm:grid-cols-2">
                        <Field label="Nama *" error={details.errors.name}>
                            <input className={inputClass} value={details.data.name}
                                   onChange={(e) => details.setData('name', e.target.value)} required />
                        </Field>
                        <Field label="Email *" error={details.errors.email}>
                            <input type="email" className={inputClass} value={details.data.email}
                                   onChange={(e) => details.setData('email', e.target.value)} required />
                        </Field>
                    </div>
                    <Button type="submit" variant="primary" disabled={details.processing}>Simpan</Button>
                </form>
            </Panel>

            <Panel title="Ganti kata sandi">
                <form onSubmit={(e) => {
                    e.preventDefault();
                    password.put('/profile/password', { onSuccess: () => password.reset() });
                }}>
                    <Field label="Kata sandi saat ini *" error={password.errors.current_password}>
                        <input type="password" autoComplete="current-password" className={inputClass}
                               value={password.data.current_password}
                               onChange={(e) => password.setData('current_password', e.target.value)} required />
                    </Field>
                    <div className="gap-x-4 sm:grid sm:grid-cols-2">
                        <Field label="Kata sandi baru *" error={password.errors.password}>
                            <input type="password" autoComplete="new-password" className={inputClass}
                                   value={password.data.password}
                                   onChange={(e) => password.setData('password', e.target.value)} required />
                        </Field>
                        <Field label="Ulangi kata sandi baru *">
                            <input type="password" autoComplete="new-password" className={inputClass}
                                   value={password.data.password_confirmation}
                                   onChange={(e) => password.setData('password_confirmation', e.target.value)} required />
                        </Field>
                    </div>
                    <Button type="submit" variant="primary" disabled={password.processing}>Ganti kata sandi</Button>
                </form>
            </Panel>
        </AppLayout>
    );
}
