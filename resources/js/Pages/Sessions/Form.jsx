import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ project, session, crew, equipment, statuses }) {
    const editing = Boolean(session.id);

    const { data, setData, post, put, processing, errors } = useForm({
        scheduled_at: session.scheduled_at ?? '',
        crew_id: session.crew_id ?? '',
        status: session.status ?? 'scheduled',
        shot_count: session.shot_count ?? '',
        raw_size_gb: session.raw_size_gb ?? '',
        frame_count: session.frame_count ?? '',
        backup_location: session.backup_location ?? '',
        location: session.location ?? '',
        weather_note: session.weather_note ?? '',
        equipment_note: session.equipment_note ?? '',
        notes: session.notes ?? '',
        equipment: session.equipment ?? [],
    });

    const toggle = (id) => setData('equipment', data.equipment.includes(id)
        ? data.equipment.filter((value) => value !== id)
        : [...data.equipment, id]);

    return (
        <AppLayout title={editing ? 'Ubah sesi' : 'Jadwalkan sesi'}>
            <h1 className="mb-4 text-2xl font-semibold">
                {editing ? 'Ubah sesi' : 'Jadwalkan sesi'} — {project.title}
            </h1>

            <form onSubmit={(e) => {
                e.preventDefault();
                editing ? put(`/projects/${project.slug}/sessions/${session.id}`) : post(`/projects/${project.slug}/sessions`);
            }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Jadwal *" error={errors.scheduled_at}>
                        <input type="datetime-local" className={inputClass} value={data.scheduled_at}
                               onChange={(e) => setData('scheduled_at', e.target.value)} required />
                    </Field>
                    <Field label="Kru" error={errors.crew_id}>
                        <select className={inputClass} value={data.crew_id ?? ''} onChange={(e) => setData('crew_id', e.target.value)}>
                            <option value="">— belum ditentukan —</option>
                            {crew.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </Field>
                    <Field label="Jumlah shot" error={errors.shot_count}>
                        <input type="number" min="0" className={inputClass} value={data.shot_count ?? ''}
                               onChange={(e) => setData('shot_count', e.target.value)} />
                    </Field>
                    <Field label="Ukuran data mentah (GB)" error={errors.raw_size_gb}>
                        <input type="number" step="0.01" min="0" className={inputClass} value={data.raw_size_gb ?? ''}
                               onChange={(e) => setData('raw_size_gb', e.target.value)} />
                    </Field>
                    <Field label="Jumlah frame" error={errors.frame_count}>
                        <input type="number" min="0" className={inputClass} value={data.frame_count ?? ''}
                               onChange={(e) => setData('frame_count', e.target.value)} />
                    </Field>
                    <Field label="Lokasi backup" error={errors.backup_location} wide>
                        <input className={inputClass} placeholder="Mis. NAS/2026/showroom-kemang" value={data.backup_location ?? ''}
                               onChange={(e) => setData('backup_location', e.target.value)} />
                    </Field>
                    <Field label="Lokasi" error={errors.location} wide>
                        <input className={inputClass} value={data.location ?? ''} onChange={(e) => setData('location', e.target.value)} />
                    </Field>
                    <Field label="Catatan cuaca / kondisi" error={errors.weather_note} wide>
                        <input className={inputClass} value={data.weather_note ?? ''} onChange={(e) => setData('weather_note', e.target.value)} />
                    </Field>
                    <Field label="Catatan peralatan" error={errors.equipment_note} wide>
                        <textarea rows={3} className={inputClass} value={data.equipment_note ?? ''}
                                  onChange={(e) => setData('equipment_note', e.target.value)} />
                    </Field>
                    <Field label="Catatan" error={errors.notes} wide>
                        <textarea rows={4} className={inputClass} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                </div>

                <h2 className="mb-2 text-base font-semibold">Peralatan dari inventaris</h2>
                {errors.equipment && <p className="mb-2 text-sm text-danger">{errors.equipment}</p>}
                {equipment.length === 0
                    ? <p className="text-sm text-muted">Belum ada peralatan tersedia di inventaris.</p>
                    : (
                        <div className="mb-4 grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(240px,1fr))]">
                            {equipment.map((item) => (
                                <label key={item.id} className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" checked={data.equipment.includes(item.id)} onChange={() => toggle(item.id)} />
                                    {item.name} <span className="text-xs text-muted">{item.code} · {item.category}</span>
                                </label>
                            ))}
                        </div>
                    )}

                <div className="flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href={`/projects/${project.slug}`} variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
