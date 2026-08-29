import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ sessions, filters, statuses }) {
    const [form, setForm] = useState({ status: filters.status ?? '', mine: Boolean(filters.mine) });

    return (
        <AppLayout title="Sesi capture">
            <PageHead title="Agenda pengambilan gambar" />

            <form onSubmit={(e) => { e.preventDefault(); router.get('/sessions', form, { preserveState: true }); }}
                  className="mb-3 flex flex-wrap items-center gap-2">
                <select className={`${inputClass} w-40`} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <label className="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" checked={form.mine} onChange={(e) => setForm({ ...form, mine: e.target.checked })} /> Sesi saya
                </label>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Jadwal', 'Project', 'Klien', 'Kru', 'Peralatan', 'Status']} empty="Belum ada sesi.">
                {sessions.data.map((session) => (
                    <tr key={session.id}>
                        <Td>{session.scheduled_at}</Td>
                        <Td><Link href={`/projects/${session.project_slug}`} className="text-accent">{session.project_title}</Link></Td>
                        <Td>{session.client_name}</Td>
                        <Td>{session.crew_name ?? '—'}</Td>
                        <Td>{session.equipment || '—'}</Td>
                        <Td><Badge status={session.status} /></Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={sessions.links} />
        </AppLayout>
    );
}
