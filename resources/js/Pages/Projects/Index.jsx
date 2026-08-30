import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ projects, clients, filters, statuses }) {
    const [form, setForm] = useState({
        q: filters.q ?? '', status: filters.status ?? '', client: filters.client ?? '', mine: Boolean(filters.mine),
    });

    return (
        <AppLayout title="Project">
            <PageHead title="Project">
                <ButtonLink href="/projects/create" variant="primary">Project baru</ButtonLink>
            </PageHead>

            <form onSubmit={(e) => { e.preventDefault(); router.get('/projects', form, { preserveState: true }); }}
                  className="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" placeholder="Cari judul, lokasi, klien…" className={`${inputClass} sm:w-56`}
                       value={form.q} onChange={(e) => setForm({ ...form, q: e.target.value })} />
                <select className={`${inputClass} sm:w-40`} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <select className={`${inputClass} sm:w-44`} value={form.client} onChange={(e) => setForm({ ...form, client: e.target.value })}>
                    <option value="">Semua klien</option>
                    {clients.map((c) => <option key={c.slug} value={c.slug}>{c.name}</option>)}
                </select>
                <label className="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" checked={form.mine} onChange={(e) => setForm({ ...form, mine: e.target.checked })} /> Punya saya
                </label>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Judul', 'Klien', 'Layanan', 'PIC', 'Status', 'Deadline']} empty="Belum ada project.">
                {projects.data.map((project) => (
                    <tr key={project.id}>
                        <Td><Link href={`/projects/${project.slug}`} className="text-accent">{project.title}</Link></Td>
                        <Td><Link href={`/clients/${project.client_slug}`} className="text-accent">{project.client_name}</Link></Td>
                        <Td>{project.service_type}</Td>
                        <Td>{project.owner_name ?? '—'}</Td>
                        <Td><Badge status={project.status} /></Td>
                        <Td>{project.deadline ?? '—'}</Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={projects.links} />
        </AppLayout>
    );
}
