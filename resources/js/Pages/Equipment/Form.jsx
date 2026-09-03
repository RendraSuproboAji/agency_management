import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ item, categories, statuses }) {
    const editing = Boolean(item.id);

    const { data, setData, post, put, processing, errors } = useForm({
        name: item.name ?? '',
        code: item.code ?? '',
        category: item.category ?? 'camera',
        status: item.status ?? 'available',
        serial_number: item.serial_number ?? '',
        notes: item.notes ?? '',
    });

    return (
        <AppLayout title={editing ? 'Ubah peralatan' : 'Tambah peralatan'}>
            <h1 className="mb-4 text-2xl font-semibold">{editing ? 'Ubah peralatan' : 'Tambah peralatan'}</h1>

            <form onSubmit={(e) => { e.preventDefault(); editing ? put(`/equipment/${item.id}`) : post('/equipment'); }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Nama *" error={errors.name}>
                        <input className={inputClass} placeholder="Mis. Sony A7IV" value={data.name}
                               onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Kode *" error={errors.code}>
                        <input className={inputClass} placeholder="Mis. CAM-01" value={data.code}
                               onChange={(e) => setData('code', e.target.value)} required />
                    </Field>
                    <Field label="Kategori *" error={errors.category}>
                        <select className={inputClass} value={data.category} onChange={(e) => setData('category', e.target.value)}>
                            {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                        </select>
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </Field>
                    <Field label="Nomor seri" error={errors.serial_number} wide>
                        <input className={inputClass} value={data.serial_number ?? ''} onChange={(e) => setData('serial_number', e.target.value)} />
                    </Field>
                    <Field label="Catatan" error={errors.notes} wide>
                        <textarea rows={3} className={inputClass} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href="/equipment" variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
