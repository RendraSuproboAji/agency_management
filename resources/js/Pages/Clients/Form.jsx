import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ client, statuses }) {
    const editing = Boolean(client.slug);

    const { data, setData, post, put, processing, errors } = useForm({
        name: client.name ?? '',
        status: client.status ?? 'lead',
        contact_name: client.contact_name ?? '',
        email: client.email ?? '',
        phone: client.phone ?? '',
        industry: client.industry ?? '',
        address: client.address ?? '',
        notes: client.notes ?? '',
        portal_enabled: client.portal_enabled ?? false,
        password: '',
    });

    const submit = (event) => {
        event.preventDefault();
        editing ? put(`/clients/${client.slug}`) : post('/clients');
    };

    return (
        <AppLayout title={editing ? 'Ubah klien' : 'Tambah klien'}>
            <h1 className="mb-4 text-2xl font-semibold">{editing ? 'Ubah klien' : 'Tambah klien'}</h1>

            <form onSubmit={submit}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Nama klien *" error={errors.name}>
                        <input className={inputClass} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                        </select>
                    </Field>
                    <Field label="Nama narahubung" error={errors.contact_name}>
                        <input className={inputClass} value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <input type="email" className={inputClass} value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </Field>
                    <Field label="Telepon" error={errors.phone}>
                        <input className={inputClass} value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    </Field>
                    <Field label="Industri" error={errors.industry}>
                        <input className={inputClass} value={data.industry} onChange={(e) => setData('industry', e.target.value)} />
                    </Field>
                    <Field label="Alamat" error={errors.address} wide>
                        <input className={inputClass} value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </Field>
                    <Field label="Catatan" error={errors.notes} wide>
                        <textarea rows={4} className={inputClass} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                </div>

                {editing && (
                    <>
                        <h2 className="mt-4 mb-1 text-base font-semibold">Akses portal klien</h2>
                        <p className="mb-3 text-sm text-muted">
                            Klien yang diaktifkan bisa masuk ke <code>/portal/login</code> memakai email di atas.
                        </p>
                        <div className="gap-x-4 sm:grid sm:grid-cols-2">
                            <label className="mb-3 flex items-center gap-2 text-xs text-muted">
                                <input type="checkbox" checked={data.portal_enabled}
                                       onChange={(e) => setData('portal_enabled', e.target.checked)} />
                                Aktifkan portal
                            </label>
                            <Field label="Kata sandi portal (kosongkan bila tidak diubah)" error={errors.password}>
                                <input type="password" className={inputClass} value={data.password}
                                       onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" />
                            </Field>
                        </div>
                    </>
                )}

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href={editing ? `/clients/${client.slug}` : '/clients'} variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
