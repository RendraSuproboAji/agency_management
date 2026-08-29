import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ user, roles }) {
    const editing = Boolean(user.id);

    const { data, setData, post, put, processing, errors } = useForm({
        name: user.name ?? '',
        email: user.email ?? '',
        role: user.role ?? 'staff',
        password: '',
    });

    return (
        <AppLayout title={editing ? 'Ubah pengguna' : 'Tambah pengguna'}>
            <h1 className="mb-4 text-2xl font-semibold">{editing ? 'Ubah pengguna' : 'Tambah pengguna'}</h1>

            <form onSubmit={(e) => { e.preventDefault(); editing ? put(`/users/${user.id}`) : post('/users'); }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Nama *" error={errors.name}>
                        <input className={inputClass} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Email *" error={errors.email}>
                        <input type="email" className={inputClass} value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                    </Field>
                    <Field label="Peran *" error={errors.role}>
                        <select className={inputClass} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                        </select>
                    </Field>
                    <Field label={`Kata sandi ${editing ? '(kosongkan bila tidak diubah)' : '*'}`} error={errors.password}>
                        <input type="password" minLength={8} required={!editing} className={inputClass}
                               value={data.password} onChange={(e) => setData('password', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href="/users" variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
