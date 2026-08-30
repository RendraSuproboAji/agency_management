import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Money, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ invoices, filters, statuses }) {
    const [form, setForm] = useState({ status: filters.status ?? '', unsettled: Boolean(filters.unsettled) });

    return (
        <AppLayout title="Tagihan">
            <PageHead title="Tagihan" />

            <form onSubmit={(e) => { e.preventDefault(); router.get('/invoices', form, { preserveState: true }); }}
                  className="mb-3 flex flex-wrap items-center gap-2">
                <select className={`${inputClass} sm:w-40`} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <label className="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" checked={form.unsettled} onChange={(e) => setForm({ ...form, unsettled: e.target.checked })} /> Belum lunas
                </label>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Nomor', 'Project', 'Klien', 'Jatuh tempo', 'Nilai', 'Sisa', 'Status']} empty="Belum ada tagihan.">
                {invoices.data.map((invoice) => (
                    <tr key={invoice.id}>
                        <Td>
                            <Link href={`/projects/${invoice.project_slug}/invoices/${invoice.id}`} className="text-accent">{invoice.number}</Link>
                        </Td>
                        <Td>{invoice.project_title}</Td>
                        <Td>{invoice.client_name}</Td>
                        <Td>{invoice.due_at ?? '—'}</Td>
                        <Td><Money amount={invoice.amount} /></Td>
                        <Td><Money amount={invoice.outstanding} /></Td>
                        <Td><Badge status={invoice.status} /></Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={invoices.links} />
        </AppLayout>
    );
}
