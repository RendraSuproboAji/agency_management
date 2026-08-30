import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, DetailList, Money, PageHead, Panel, Table, Td, inputClass } from '@/Components/ui';

function confirmThen(message, action) {
    if (window.confirm(message)) action();
}

export default function Show({ project, canManage, statuses, billed, paid, rawSizeGb, jobKinds, jobStatuses, attachmentCategories }) {
    const { auth } = usePage().props;
    const [status, setStatus] = useState(project.status);
    const base = `/projects/${project.slug}`;

    const note = useForm({ body: '' });
    const job = useForm({ kind: 'splat_training', status: 'queued', capture_session_id: '', machine: '' });
    const attachment = useForm({ title: '', category: 'contract', file: null });
    const scene = useForm({ name: '', gallery_url: '' });

    return (
        <AppLayout title={project.title}>
            <PageHead
                title={project.title}
                subtitle={<>
                    <Link href={`/clients/${project.client_slug}`} className="text-accent">{project.client_name}</Link>
                    {' · '}{project.service_type} <Badge status={project.status} />
                </>}
            >
                {canManage && <ButtonLink href={`${base}/edit`}>Ubah</ButtonLink>}
                {auth.user?.is_admin && (
                    <Button variant="danger" onClick={() => confirmThen('Arsipkan project ini?', () => router.delete(base))}>Arsipkan</Button>
                )}
            </PageHead>

            <Panel>
                <DetailList items={[
                    { label: 'PIC', value: project.owner_name },
                    { label: 'Deadline', value: project.deadline },
                    { label: 'Budget', value: project.budget ? <Money amount={project.budget} /> : null },
                    { label: 'Lokasi', value: project.site_location },
                    { label: 'Luas area', value: project.area_sqm ? `${project.area_sqm} m²` : null },
                    {
                        label: 'Virtual tour',
                        value: project.gallery_url
                            ? <a href={project.gallery_url} target="_blank" rel="noopener" className="text-accent">Buka tur</a>
                            : null,
                    },
                ]} />

                {project.brief && <>
                    <h3 className="mt-4 text-sm font-semibold">Brief / request klien</h3>
                    <p className="whitespace-pre-line text-sm text-muted">{project.brief}</p>
                </>}

                {canManage && (
                    <form className="mt-4 flex flex-wrap items-center gap-2"
                          onSubmit={(e) => { e.preventDefault(); router.put(`${base}/status`, { status }); }}>
                        <label className="text-xs text-muted">Pindah status</label>
                        <select className={`${inputClass} w-44`} value={status} onChange={(e) => setStatus(e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                        <Button type="submit">Simpan status</Button>
                    </form>
                )}
            </Panel>

            <Panel
                title="Penawaran & tagihan"
                actions={canManage && <>
                    <ButtonLink href={`${base}/quotations/create`} small>Penawaran baru</ButtonLink>
                    <ButtonLink href={`${base}/invoices/create`} small>Invoice baru</ButtonLink>
                </>}
            >
                <DetailList items={[
                    { label: 'Nilai ditagihkan', value: <Money amount={billed} /> },
                    { label: 'Sudah dibayar', value: <Money amount={paid} /> },
                    { label: 'Sisa tagihan', value: <strong><Money amount={billed - paid} /></strong> },
                ]} />

                <Table head={['Dokumen', 'Tanggal', 'Nilai', 'Status']} empty="Belum ada penawaran maupun tagihan.">
                    {[...project.quotations, ...project.invoices].map((doc) => (
                        <tr key={`${doc.kind}-${doc.id}`}>
                            <Td>
                                <Link href={`${base}/${doc.kind === 'quotation' ? 'quotations' : 'invoices'}/${doc.id}`} className="text-accent">
                                    {doc.number}
                                </Link>{' '}
                                <span className="text-muted">{doc.kind === 'quotation' ? 'penawaran' : 'invoice'}</span>
                            </Td>
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

            <Panel
                title="Sesi pengambilan gambar"
                actions={canManage && <ButtonLink href={`${base}/sessions/create`} small>Jadwalkan sesi</ButtonLink>}
            >
                <Table head={['Jadwal', 'Kru', 'Lokasi', 'Peralatan', 'Shot', 'Status', '']} empty="Belum ada sesi terjadwal.">
                    {project.capture_sessions.map((session) => (
                        <tr key={session.id}>
                            <Td>{session.scheduled_at}</Td>
                            <Td>{session.crew_name ?? '—'}</Td>
                            <Td>{session.location ?? '—'}</Td>
                            <Td>{session.equipment || '—'}</Td>
                            <Td>{session.shot_count ?? '—'}</Td>
                            <Td><Badge status={session.status} /></Td>
                            <Td className="flex flex-wrap items-center gap-1">
                                {canManage && <>
                                    <Link href={`${base}/sessions/${session.id}/edit`} className="text-accent text-xs">Ubah</Link>
                                    {session.status === 'scheduled' && (
                                        <Button small onClick={() => router.put(`${base}/sessions/${session.id}/complete`)}>Selesai</Button>
                                    )}
                                    <Button small variant="danger"
                                            onClick={() => confirmThen('Hapus sesi ini?', () => router.delete(`${base}/sessions/${session.id}`))}>
                                        Hapus
                                    </Button>
                                </>}
                            </Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Job processing">
                <p className="text-sm text-muted">Total data mentah dari seluruh sesi: {rawSizeGb} GB</p>

                <Table head={['Jenis', 'Sesi', 'Mesin', 'Durasi', 'Output', 'Status', '']} empty="Belum ada job processing.">
                    {project.processing_jobs.map((item) => (
                        <tr key={item.id}>
                            <Td>{item.kind}</Td>
                            <Td>{item.session ?? '—'}</Td>
                            <Td>{item.machine ?? '—'}</Td>
                            <Td>{item.duration}</Td>
                            <Td>{item.output_size_gb ? `${item.output_size_gb} GB` : '—'}</Td>
                            <Td>
                                <Badge status={item.status} />
                                {item.notes && <><br /><small className="text-muted">{item.notes}</small></>}
                            </Td>
                            <Td className="flex flex-wrap gap-1">
                                {canManage && <>
                                    {['queued', 'failed'].includes(item.status) && (
                                        <Button small onClick={() => router.put(`${base}/jobs/${item.id}/start`)}>Jalankan</Button>
                                    )}
                                    {item.status === 'running' && (
                                        <Button small onClick={() => router.put(`${base}/jobs/${item.id}/finish`, { status: 'done' })}>Selesai</Button>
                                    )}
                                    <Button small variant="danger"
                                            onClick={() => confirmThen('Hapus job ini?', () => router.delete(`${base}/jobs/${item.id}`))}>
                                        Hapus
                                    </Button>
                                </>}
                            </Td>
                        </tr>
                    ))}
                </Table>

                {canManage && (
                    <form className="mt-3 flex flex-wrap items-end gap-2"
                          onSubmit={(e) => { e.preventDefault(); job.post(`${base}/jobs`, { onSuccess: () => job.reset() }); }}>
                        <select className={`${inputClass} w-44`} value={job.data.kind} onChange={(e) => job.setData('kind', e.target.value)}>
                            {jobKinds.map((k) => <option key={k} value={k}>{k.replace(/_/g, ' ')}</option>)}
                        </select>
                        <select className={`${inputClass} w-32`} value={job.data.status} onChange={(e) => job.setData('status', e.target.value)}>
                            {jobStatuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                        <select className={`${inputClass} w-48`} value={job.data.capture_session_id}
                                onChange={(e) => job.setData('capture_session_id', e.target.value)}>
                            <option value="">— tidak terkait sesi —</option>
                            {project.capture_sessions.map((s) => <option key={s.id} value={s.id}>{s.scheduled_at}</option>)}
                        </select>
                        <input className={`${inputClass} w-52`} placeholder="Mesin" value={job.data.machine}
                               onChange={(e) => job.setData('machine', e.target.value)} />
                        <Button type="submit" disabled={job.processing}>Tambah job</Button>
                    </form>
                )}
            </Panel>

            <Panel title="Scene">
                {project.scenes.length === 0 && <p className="text-sm text-muted">Belum ada scene. Satu project bisa punya beberapa ruang atau titik tangkap.</p>}
                {project.scenes.map((item) => (
                    <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 border-b border-line py-2 last:border-b-0">
                        <div>
                            <strong>{item.name}</strong> <span className="text-xs text-muted">{item.slug}</span>
                            {item.gallery_url && <>{' · '}<a href={item.gallery_url} target="_blank" rel="noopener" className="text-accent">Buka tur</a></>}
                            {item.notes && <p className="whitespace-pre-line text-sm text-muted">{item.notes}</p>}
                        </div>
                        {canManage && (
                            <Button small variant="danger"
                                    onClick={() => confirmThen('Arsipkan scene ini?', () => router.delete(`${base}/scenes/${item.id}`))}>
                                Arsipkan
                            </Button>
                        )}
                    </div>
                ))}

                {canManage && (
                    <form className="mt-4 flex flex-wrap items-end gap-2"
                          onSubmit={(e) => { e.preventDefault(); scene.post(`${base}/scenes`, { onSuccess: () => scene.reset() }); }}>
                        <input className={`${inputClass} w-56`} placeholder="Nama scene" value={scene.data.name}
                               onChange={(e) => scene.setData('name', e.target.value)} />
                        <input className={`${inputClass} w-72`} placeholder="URL tur (opsional)" value={scene.data.gallery_url}
                               onChange={(e) => scene.setData('gallery_url', e.target.value)} />
                        <Button type="submit" disabled={scene.processing}>Tambah scene</Button>
                    </form>
                )}
                {scene.errors.name && <p className="mt-2 text-sm text-danger">{scene.errors.name}</p>}
                {scene.errors.gallery_url && <p className="mt-2 text-sm text-danger">{scene.errors.gallery_url}</p>}
            </Panel>

            <Panel
                title="Deliverable"
                actions={canManage && <ButtonLink href={`${base}/deliverables/create`} small>Tambah deliverable</ButtonLink>}
            >
                {project.deliverables.length === 0 && <p className="text-sm text-muted">Belum ada deliverable.</p>}
                {project.deliverables.map((item) => (
                    <div key={item.id} className="flex flex-wrap justify-between gap-3 border-b border-line py-3 last:border-b-0">
                        <div>
                            <strong>{item.title}</strong> <span className="text-sm text-muted">v{item.version} · {item.type}{item.scene ? ` · ${item.scene}` : ''}</span>{' '}
                            <Badge status={item.status} />{' '}
                            {item.url && <a href={item.url} target="_blank" rel="noopener" className="text-accent">Buka aset</a>}
                            {item.review_note && <p className="whitespace-pre-line text-sm text-muted">{item.review_note}</p>}
                        </div>
                        {canManage && (
                            <div className="flex flex-wrap items-center gap-1">
                                <Link href={`${base}/deliverables/${item.id}/edit`} className="text-xs text-accent">Ubah</Link>
                                {item.status !== 'approved' && (
                                    <Button small onClick={() => router.put(`${base}/deliverables/${item.id}/approve`)}>Setujui</Button>
                                )}
                                <Button small variant="danger"
                                        onClick={() => confirmThen('Arsipkan deliverable ini?', () => router.delete(`${base}/deliverables/${item.id}`))}>
                                    Arsipkan
                                </Button>
                            </div>
                        )}
                    </div>
                ))}
            </Panel>

            <Panel title="Lampiran">
                <Table head={['Judul', 'Kategori', 'Ukuran', 'Diunggah', '']} empty="Belum ada lampiran.">
                    {project.attachments.map((item) => (
                        <tr key={item.id}>
                            <Td><a href={`${base}/attachments/${item.id}`} className="text-accent">{item.title}</a></Td>
                            <Td>{item.category}</Td>
                            <Td>{item.size}</Td>
                            <Td>{item.created_at}<br /><small className="text-muted">{item.uploader}</small></Td>
                            <Td>
                                {canManage && (
                                    <Button small variant="danger"
                                            onClick={() => confirmThen('Hapus lampiran ini?', () => router.delete(`${base}/attachments/${item.id}`))}>
                                        Hapus
                                    </Button>
                                )}
                            </Td>
                        </tr>
                    ))}
                </Table>

                {canManage && (
                    <form className="mt-3 flex flex-wrap items-end gap-2"
                          onSubmit={(e) => {
                              e.preventDefault();
                              attachment.post(`${base}/attachments`, { forceFormData: true, onSuccess: () => attachment.reset() });
                          }}>
                        <input className={`${inputClass} w-56`} placeholder="Judul lampiran" required
                               value={attachment.data.title} onChange={(e) => attachment.setData('title', e.target.value)} />
                        <select className={`${inputClass} w-40`} value={attachment.data.category}
                                onChange={(e) => attachment.setData('category', e.target.value)}>
                            {attachmentCategories.map((c) => <option key={c} value={c}>{c}</option>)}
                        </select>
                        <input type="file" required className={`${inputClass} w-64`}
                               onChange={(e) => attachment.setData('file', e.target.files[0])} />
                        <Button type="submit" disabled={attachment.processing}>Unggah</Button>
                    </form>
                )}
            </Panel>

            <Panel title="Catatan internal">
                {project.notes.length === 0 && <p className="text-sm text-muted">Belum ada catatan.</p>}
                {project.notes.map((item) => (
                    <div key={item.id} className="border-b border-line py-2 last:border-b-0">
                        <p className="whitespace-pre-line text-sm">{item.body}</p>
                        <p className="mt-1 flex items-center gap-2 text-xs text-muted">
                            {item.author} · {item.created_at}
                            {item.can_delete && (
                                <Button small variant="danger"
                                        onClick={() => confirmThen('Hapus catatan ini?', () => router.delete(`${base}/notes/${item.id}`))}>
                                    Hapus
                                </Button>
                            )}
                        </p>
                    </div>
                ))}

                {canManage && (
                    <form className="mt-3" onSubmit={(e) => { e.preventDefault(); note.post(`${base}/notes`, { onSuccess: () => note.reset() }); }}>
                        <textarea rows={3} required className={inputClass} placeholder="Hasil rapat, kendala di lapangan, kesepakatan dengan klien…"
                                  value={note.data.body} onChange={(e) => note.setData('body', e.target.value)} />
                        <Button type="submit" className="mt-2" disabled={note.processing}>Simpan catatan</Button>
                    </form>
                )}
            </Panel>

            <Panel title="Riwayat aktivitas">
                {project.activities.length === 0 && <p className="text-sm text-muted">Belum ada aktivitas tercatat.</p>}
                {project.activities.map((item) => (
                    <div key={item.id} className="flex flex-wrap justify-between gap-2 border-b border-line py-2 text-sm last:border-b-0">
                        <span>{item.description}</span>
                        <span className="text-muted">{item.actor} · {item.created_at}</span>
                    </div>
                ))}
            </Panel>
        </AppLayout>
    );
}
