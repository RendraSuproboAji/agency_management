import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, ButtonLink, Button, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ clients, filters, statuses }) {
    const [form, setForm] = useState({ q: filters.q ?? '', status: filters.status ?? '' });

    const submit = (event) => {
        event.preventDefault();
        router.get('/clients', form, { preserveState: true });
    };

    return (
        <AppLayout title="Klien">
            <PageHead title="Klien">
                <ButtonLink href="/clients/create" variant="primary">Tambah klien</ButtonLink>
            </PageHead>

            <form onSubmit={submit} className="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" placeholder="Cari nama, kontak, email…" className={`${inputClass} sm:w-56`}
                       value={form.q} onChange={(e) => setForm({ ...form, q: e.target.value })} />
                <select className={`${inputClass} sm:w-40`} value={form.status}
                        onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Nama', 'Kontak', 'Industri', 'Project', 'Status']} empty="Belum ada klien.">
                {clients.data.map((client) => (
                    <tr key={client.id}>
                        <Td><Link href={`/clients/${client.slug}`} className="text-accent">{client.name}</Link></Td>
                        <Td>{client.contact_name || '—'}<br /><small className="text-muted">{client.email}</small></Td>
                        <Td>{client.industry || '—'}</Td>
                        <Td>{client.projects_count}</Td>
                        <Td><Badge status={client.status} /></Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={clients.links} />
        </AppLayout>
    );
}
