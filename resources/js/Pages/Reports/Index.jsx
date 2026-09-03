import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Money, PageHead, Panel, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ filters, months, totals, exports }) {
    const [form, setForm] = useState({ from: filters.from, to: filters.to });

    const submit = (event) => {
        event.preventDefault();
        router.get('/reports', form, { preserveState: true });
    };

    const linkClass = 'inline-block rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink no-underline hover:border-accent';

    return (
        <AppLayout title="Laporan">
            <PageHead title="Laporan" subtitle="Nilai invoice terbit dan pembayaran diterima, per bulan.">
                {/* Unduhan berkas, bukan kunjungan Inertia: harus <a> biasa. */}
                <a href={exports.invoices} className={linkClass}>Ekspor invoice (CSV)</a>
                <a href={exports.payments} className={linkClass}>Ekspor pembayaran (CSV)</a>
            </PageHead>

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2">
                <label className="text-xs text-muted">
                    Dari
                    <input type="date" className={`${inputClass} sm:w-40`} value={form.from}
                           onChange={(e) => setForm({ ...form, from: e.target.value })} />
                </label>
                <label className="text-xs text-muted">
                    Sampai
                    <input type="date" className={`${inputClass} sm:w-40`} value={form.to}
                           onChange={(e) => setForm({ ...form, to: e.target.value })} />
                </label>
                <Button type="submit">Terapkan</Button>
            </form>

            <div className="mb-4 grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
                <Panel><span className="text-xs text-muted">Invoice terbit</span>
                    <p className="text-xl font-bold"><Money amount={totals.invoiced} /></p></Panel>
                <Panel><span className="text-xs text-muted">Pembayaran diterima</span>
                    <p className="text-xl font-bold"><Money amount={totals.received} /></p></Panel>
                <Panel><span className="text-xs text-muted">Piutang hari ini</span>
                    <p className="text-xl font-bold"><Money amount={totals.outstanding} /></p>
                    <span className="text-xs text-muted">seluruh invoice belum lunas, bukan hanya periode ini</span></Panel>
            </div>

            <Table head={['Bulan', 'Invoice terbit', 'Pembayaran diterima']} empty="Tidak ada data pada rentang ini.">
                {months.map((row) => (
                    <tr key={row.month}>
                        <Td>{row.label}</Td>
                        <Td><Money amount={row.invoiced} /></Td>
                        <Td><Money amount={row.received} /></Td>
                    </tr>
                ))}
            </Table>
        </AppLayout>
    );
}
