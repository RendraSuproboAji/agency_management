import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Badge, Button, ButtonLink, DetailList, Money, PageHead, Panel, Table, Td, inputClass } from '@/Components/ui';

function RevisionForm({ action }) {
    const { data, setData, put, processing, errors } = useForm({ review_note: '' });

    return (
        <form className="flex items-center gap-1" onSubmit={(e) => { e.preventDefault(); put(action); }}>
            <input className={`${inputClass} sm:w-56`} placeholder="Apa yang perlu diperbaiki?" required
                   value={data.review_note} onChange={(e) => setData('review_note', e.target.value)} />
            <Button small type="submit" disabled={processing}>Minta revisi</Button>
            {errors.review_note && <span className="text-xs text-danger">{errors.review_note}</span>}
        </form>
    );
}

/**
 * Percakapan dengan tim. Catatan staf hanya sampai ke sini bila mereka
 * menandainya dibagikan, jadi yang internal tidak pernah ikut terbawa.
 */
function Messages({ project }) {
    const message = useForm({ body: '' });
    const upload = useForm({ title: '', file: null });

    const send = (event) => {
        event.preventDefault();
        message.post(`/portal/projects/${project.slug}/messages`, {
            onSuccess: () => message.reset(),
        });
    };

    const sendFile = (event) => {
        event.preventDefault();
        upload.post(`/portal/projects/${project.slug}/attachments`, {
            forceFormData: true,
            onSuccess: () => upload.reset(),
        });
    };

    return (
        <Panel title="Pesan">
            {project.messages.length === 0 && (
                <p className="text-sm text-muted">Belum ada pesan. Tulis pertanyaan Anda di bawah.</p>
            )}
            {project.messages.map((item) => (
                <div key={item.id} className="border-b border-line py-2 last:border-b-0">
                    <p className="whitespace-pre-line text-sm">{item.body}</p>
                    <p className="mt-1 text-xs text-muted">
                        {item.author}{item.from_client ? ' (Anda)' : ' · tim'} · {item.created_at}
                    </p>
                </div>
            ))}

            <form onSubmit={send} className="mt-3">
                <textarea rows={3} required className={inputClass} placeholder="Tulis pertanyaan atau masukan…"
                          value={message.data.body} onChange={(e) => message.setData('body', e.target.value)} />
                {message.errors.body && <p className="mt-1 text-xs text-danger">{message.errors.body}</p>}
                <Button type="submit" className="mt-2" disabled={message.processing}>Kirim pesan</Button>
            </form>

            <div className="mt-5 border-t border-line pt-4">
                <h3 className="text-sm font-semibold">Kirim berkas</h3>
                <p className="mt-1 text-xs text-muted">Denah, foto acuan, atau apa pun yang membantu tim kami.</p>

                {project.files.length > 0 && (
                    <ul className="mt-2 text-sm">
                        {project.files.map((file) => (
                            <li key={file.id} className="py-1">
                                <a href={file.download_url} className="text-accent">{file.title}</a>{' '}
                                <span className="text-xs text-muted">{file.size} · {file.created_at}</span>
                            </li>
                        ))}
                    </ul>
                )}

                <form onSubmit={sendFile} className="mt-2 grid gap-2 sm:grid-cols-2">
                    <input className={inputClass} required placeholder="Nama berkas"
                           value={upload.data.title} onChange={(e) => upload.setData('title', e.target.value)} />
                    <input type="file" className={inputClass} required
                           onChange={(e) => upload.setData('file', e.target.files[0])} />
                    {upload.errors.title && <p className="text-xs text-danger">{upload.errors.title}</p>}
                    {upload.errors.file && <p className="text-xs text-danger sm:col-span-2">{upload.errors.file}</p>}
                    <Button type="submit" className="sm:col-span-2 sm:justify-self-start" disabled={upload.processing}>
                        Unggah
                    </Button>
                </form>
            </div>
        </Panel>
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
                                {doc.days_overdue > 0 && (
                                    <><br /><small className="text-danger">lewat jatuh tempo {doc.days_overdue} hari</small></>
                                )}
                            </Td>
                            <Td>
                                <Badge status={doc.status} />
                                {' '}<a href={doc.print_url} className="text-accent">Cetak</a>
                                {doc.payments.length > 0 && (
                                    <ul className="mt-1 text-xs text-muted">
                                        {doc.payments.map((payment) => (
                                            <li key={payment.id}>
                                                {payment.paid_at} · <Money amount={payment.amount} /> · {payment.method}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Messages project={project} />
        </PortalLayout>
    );
}
