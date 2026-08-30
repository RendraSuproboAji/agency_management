import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Badge, Button, ButtonLink, DetailList, Money, PageHead, Panel, Table, Td, inputClass } from '@/Components/ui';

function RevisionForm({ action }) {
    const { data, setData, put, processing, errors } = useForm({ review_note: '' });

    return (
        <form className="flex items-center gap-1" onSubmit={(e) => { e.preventDefault(); put(action); }}>
            <input className={`${inputClass} w-56`} placeholder="Apa yang perlu diperbaiki?" required
                   value={data.review_note} onChange={(e) => setData('review_note', e.target.value)} />
            <Button small type="submit" disabled={processing}>Minta revisi</Button>
            {errors.review_note && <span className="text-xs text-danger">{errors.review_note}</span>}
        </form>
    );
}

/**
 * Kelompokkan hasil pekerjaan per scene, mempertahankan urutan aslinya.
 * Deliverable tanpa scene tetap tampil, di bawah judul kosong.
 */
function groupByScene(items) {
    const groups = new Map();

    for (const item of items) {
        const key = item.scene ?? null;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(item);
    }

    return [...groups.entries()];
}

export default function Project({ project, statuses }) {
    return (
        <PortalLayout title={project.title}>
            <PageHead title={project.title} subtitle={<>{project.service_type} <Badge status={project.status} /></>}>
                <ButtonLink href="/portal" variant="ghost">Kembali</ButtonLink>
            </PageHead>

            <Panel title="Tahap pengerjaan">
                <div className="mb-4 flex flex-wrap gap-2">
                    {statuses.map((status) => (
                        <div key={status}
                             className={`flex-1 basis-24 rounded-lg border px-2 py-3 text-center text-xs capitalize ${
                                 project.status === status ? 'border-accent bg-accent font-bold text-accent-ink' : 'border-line bg-raised text-muted'
                             }`}>
                            {status}
                        </div>
                    ))}
                </div>

                <DetailList items={[
                    { label: 'Deadline', value: project.deadline },
                    { label: 'Lokasi', value: project.site_location },
                    {
                        label: 'Virtual tour',
                        value: project.gallery_url
                            ? <a href={project.gallery_url} target="_blank" rel="noopener" className="text-accent">Buka tur</a>
                            : null,
                    },
                ]} />
            </Panel>

            <Panel title="Jadwal pengambilan gambar">
                <Table head={['Jadwal', 'Lokasi', 'Status']} empty="Belum ada jadwal.">
                    {project.capture_sessions.map((session) => (
                        <tr key={session.id}>
                            <Td>{session.scheduled_at}</Td>
                            <Td>{session.location ?? '—'}</Td>
                            <Td><Badge status={session.status} /></Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Hasil pekerjaan">
                {project.deliverables.length === 0 && <p className="text-sm text-muted">Belum ada hasil yang diserahkan.</p>}
                {groupByScene(project.deliverables).map(([scene, items]) => (
                    <section key={scene ?? ''}>
                        {scene && <h3 className="mt-4 text-sm font-semibold text-muted">{scene}</h3>}
                        {items.map((item) => (
                            <div key={item.id} className="flex flex-wrap justify-between gap-3 border-b border-line py-3 last:border-b-0">
                                <div>
                                    <strong>{item.title}</strong> <span className="text-sm text-muted">v{item.version} · {item.type}</span>{' '}
                                    <Badge status={item.status} />{' '}
                                    {item.url && <a href={item.url} target="_blank" rel="noopener" className="text-accent">Buka tur</a>}{' '}
                                    {item.download_url && <a href={item.download_url} className="text-accent">Unduh berkas</a>}
                                    {item.review_note && <p className="whitespace-pre-line text-sm text-muted">{item.review_note}</p>}
                                </div>

                                {item.can_review && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button small onClick={() => router.put(`/portal/projects/${project.slug}/deliverables/${item.id}/approve`)}>
                                            Setujui
                                        </Button>
                                        <RevisionForm action={`/portal/projects/${project.slug}/deliverables/${item.id}/revision`} />
                                    </div>
                                )}
                            </div>
                        ))}
                    </section>
                ))}
            </Panel>

            <Panel title="Penawaran & tagihan">
                <Table head={['Dokumen', 'Tanggal', 'Nilai', 'Status']} empty="Belum ada dokumen.">
                    {project.documents.map((doc) => (
                        <tr key={`${doc.kind}-${doc.id}`}>
                            <Td>{doc.number} <span className="text-muted">{doc.kind === 'quotation' ? 'penawaran' : 'invoice'}</span></Td>
                            <Td>{doc.issued_at}</Td>
                            <Td>
                                <Money amount={doc.amount} />
                                {doc.kind === 'invoice' && <><br /><small className="text-muted">sisa <Money amount={doc.outstanding} /></small></>}
                            </Td>
                            <Td><Badge status={doc.status} /></Td>
                        </tr>
                    ))}
                </Table>
            </Panel>
        </PortalLayout>
    );
}
