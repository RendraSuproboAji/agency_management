import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ project, deliverable, scenes, types, statuses }) {
    const editing = Boolean(deliverable.id);

    const { data, setData, post, processing, errors } = useForm({
        title: deliverable.title ?? '',
        type: deliverable.type ?? 'splat',
        version: deliverable.version ?? 1,
        scene_id: deliverable.scene_id ?? '',
        status: deliverable.status ?? 'draft',
        external_url: deliverable.external_url ?? '',
        review_note: deliverable.review_note ?? '',
        file: null,
        // Unggahan berkas memaksa multipart, dan multipart tidak membawa PUT —
        // jadi update dikirim sebagai POST dengan _method.
        ...(editing ? { _method: 'put' } : {}),
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            editing
                ? `/projects/${project.slug}/deliverables/${deliverable.id}`
                : `/projects/${project.slug}/deliverables`,
            { forceFormData: true },
        );
    };

    return (
        <AppLayout title={editing ? 'Ubah deliverable' : 'Tambah deliverable'}>
            <h1 className="mb-4 text-2xl font-semibold">
                {editing ? 'Ubah deliverable' : 'Tambah deliverable'} — {project.title}
            </h1>

            <form onSubmit={submit}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Judul *" error={errors.title}>
                        <input className={inputClass} value={data.title}
                               onChange={(e) => setData('title', e.target.value)} required />
                    </Field>
                    <Field label="Jenis *" error={errors.type}>
                        <select className={inputClass} value={data.type} onChange={(e) => setData('type', e.target.value)}>
                            {types.map((type) => <option key={type} value={type}>{type}</option>)}
                        </select>
                    </Field>
                    <Field label="Scene" error={errors.scene_id}>
                        <select className={inputClass} value={data.scene_id} onChange={(e) => setData('scene_id', e.target.value)}>
                            <option value="">— seluruh project —</option>
                            {scenes.map((scene) => <option key={scene.id} value={scene.id}>{scene.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Versi *" error={errors.version}>
                        <input type="number" min="1" className={inputClass} value={data.version}
                               onChange={(e) => setData('version', e.target.value)} required />
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                        </select>
                    </Field>
                    <Field label="Tautan eksternal (mis. GalleryVT)" error={errors.external_url} wide>
                        <input type="url" placeholder="https://…" className={inputClass} value={data.external_url ?? ''}
                               onChange={(e) => setData('external_url', e.target.value)} />
                    </Field>
                    <Field label="Berkas" error={errors.file} wide>
                        <input type="file" className={inputClass} onChange={(e) => setData('file', e.target.files[0] ?? null)} />
                        {deliverable.file_name && (
                            <small className="mt-1 block text-muted">
                                Saat ini: {deliverable.file_name} (unggah berkas baru untuk mengganti)
                            </small>
                        )}
                    </Field>
                    <Field label="Catatan review" error={errors.review_note} wide>
                        <textarea rows={3} className={inputClass} value={data.review_note ?? ''}
                                  onChange={(e) => setData('review_note', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href={`/projects/${project.slug}`} variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
