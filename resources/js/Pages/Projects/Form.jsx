import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ project, clients, owners, statuses, serviceTypes }) {
    const editing = Boolean(project.slug);

    const { data, setData, post, put, processing, errors } = useForm({
        title: project.title ?? '',
        client_id: project.client_id ?? '',
        service_type: project.service_type ?? 'gaussian_splatting',
        status: project.status ?? 'lead',
        owner_id: project.owner_id ?? '',
        deadline: project.deadline ?? '',
        budget: project.budget ?? '',
        area_sqm: project.area_sqm ?? '',
        site_location: project.site_location ?? '',
        gallery_url: project.gallery_url ?? '',
        brief: project.brief ?? '',
    });

    return (
        <AppLayout title={editing ? 'Ubah project' : 'Project baru'}>
            <h1 className="mb-4 text-2xl font-semibold">{editing ? 'Ubah project' : 'Project baru'}</h1>

            <form onSubmit={(e) => { e.preventDefault(); editing ? put(`/projects/${project.slug}`) : post('/projects'); }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Judul project *" error={errors.title}>
                        <input className={inputClass} value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                    </Field>
                    <Field label="Klien *" error={errors.client_id}>
                        <select className={inputClass} value={data.client_id} onChange={(e) => setData('client_id', e.target.value)} required>
                            <option value="">— pilih klien —</option>
                            {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Jenis layanan *" error={errors.service_type}>
                        <select className={inputClass} value={data.service_type} onChange={(e) => setData('service_type', e.target.value)}>
                            {serviceTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </Field>
                    <Field label="Penanggung jawab" error={errors.owner_id}>
                        <select className={inputClass} value={data.owner_id ?? ''} onChange={(e) => setData('owner_id', e.target.value)}>
                            <option value="">— belum ditentukan —</option>
                            {owners.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Deadline" error={errors.deadline}>
                        <input type="date" className={inputClass} value={data.deadline ?? ''} onChange={(e) => setData('deadline', e.target.value)} />
                    </Field>
                    <Field label="Budget (Rp)" error={errors.budget}>
                        <input type="number" step="0.01" min="0" className={inputClass} value={data.budget ?? ''} onChange={(e) => setData('budget', e.target.value)} />
                    </Field>
                    <Field label="Luas area (m²)" error={errors.area_sqm}>
                        <input type="number" min="0" className={inputClass} value={data.area_sqm ?? ''} onChange={(e) => setData('area_sqm', e.target.value)} />
                    </Field>
                    <Field label="Lokasi site" error={errors.site_location} wide>
                        <input className={inputClass} value={data.site_location ?? ''} onChange={(e) => setData('site_location', e.target.value)} />
                    </Field>
                    <Field label="Tautan virtual tour (GalleryVT)" error={errors.gallery_url} wide>
                        <input type="url" placeholder="https://…" className={inputClass} value={data.gallery_url ?? ''} onChange={(e) => setData('gallery_url', e.target.value)} />
                    </Field>
                    <Field label="Brief / request klien" error={errors.brief} wide>
                        <textarea rows={6} className={inputClass} value={data.brief ?? ''} onChange={(e) => setData('brief', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href={editing ? `/projects/${project.slug}` : '/projects'} variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
