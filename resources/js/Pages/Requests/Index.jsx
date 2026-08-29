import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ requests, filters, statuses }) {
    const [form, setForm] = useState({ q: filters.q ?? '', status: filters.status ?? '' });

    return (
        <AppLayout title="Request masuk">
            <PageHead title="Request masuk">
                <a href="/request" target="_blank" rel="noopener"
                   className="inline-block rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink no-underline hover:border-accent">
                    Lihat form publik
                </a>
            </PageHead>

            <form onSubmit={(e) => { e.preventDefault(); router.get('/requests', form, { preserveState: true }); }}
                  className="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" placeholder="Cari nama, perusahaan, email…" className={`${inputClass} w-56`}
                       value={form.q} onChange={(e) => setForm({ ...form, q: e.target.value })} />
                <select className={`${inputClass} w-40`} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Masuk', 'Pengirim', 'Layanan', 'Lokasi', 'Status']} empty="Belum ada request masuk.">
                {requests.data.map((item) => (
                    <tr key={item.id}>
                        <Td>{item.created_at}</Td>
                        <Td>
                            <Link href={`/requests/${item.id}`} className="text-accent">{item.company || item.name}</Link>
                            <br /><small className="text-muted">{item.name} · {item.email}</small>
                        </Td>
                        <Td>{item.service_type}</Td>
                        <Td>{item.site_location ?? '—'}</Td>
                        <Td><Badge status={item.status} /></Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={requests.links} />
        </AppLayout>
    );
}
