import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, DetailList, Money, PageHead, Panel, Table, Td, inputClass } from '@/Components/ui';

export default function Show({ project, invoice, canManage, methods }) {
    const { auth } = usePage().props;
    const base = `/projects/${project.slug}/invoices/${invoice.id}`;

    const payment = useForm({
        paid_at: new Date().toISOString().slice(0, 10),
        amount: invoice.outstanding || '',
        method: 'transfer',
        reference: '',
        note: '',
    });

    return (
        <AppLayout title={invoice.number}>
            <PageHead
                title={invoice.number}
                subtitle={<>
                    <Link href={`/projects/${project.slug}`} className="text-accent">{project.title}</Link>
                    {' · terbit '}{invoice.issued_at}
                    {invoice.due_at && ` · jatuh tempo ${invoice.due_at}`} <Badge status={invoice.status} />
                </>}
            >
                <a href={`${base}/print`} target="_blank" rel="noopener"
                   className="inline-block rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink no-underline hover:border-accent">
                    Cetak
                </a>
                {canManage && <ButtonLink href={`${base}/edit`}>Ubah</ButtonLink>}
                {auth.user?.is_admin && (
                    <Button variant="danger" onClick={() => window.confirm('Arsipkan invoice ini?') && router.delete(base)}>Arsipkan</Button>
                )}
            </PageHead>

            <Panel>
                <DetailList items={[
                    { label: 'Nilai tagihan', value: <Money amount={invoice.amount} /> },
                    { label: 'Sudah dibayar', value: <Money amount={invoice.paid} /> },
                    { label: 'Sisa', value: <strong><Money amount={invoice.outstanding} /></strong> },
                ]} />
                {invoice.quotation && (
                    <p className="mt-3 text-sm text-muted">
                        Dari penawaran{' '}
                        <Link href={`/projects/${project.slug}/quotations/${invoice.quotation.id}`} className="text-accent">
                            {invoice.quotation.number}
                        </Link>.
                    </p>
                )}
                {invoice.notes && <p className="mt-2 whitespace-pre-line text-sm text-muted">{invoice.notes}</p>}
            </Panel>

            <Panel title="Pembayaran">
                <Table head={['Tanggal', 'Jumlah', 'Metode', 'Referensi', '']} empty="Belum ada pembayaran.">
                    {invoice.payments.map((item) => (
                        <tr key={item.id}>
                            <Td>{item.paid_at}</Td>
                            <Td><Money amount={item.amount} /></Td>
                            <Td>{item.method}</Td>
                            <Td>{item.reference || '—'}<br /><small className="text-muted">{item.note}</small></Td>
                            <Td>
                                {canManage && (
                                    <Button small variant="danger"
                                            onClick={() => window.confirm('Hapus pembayaran ini?') && router.delete(`${base}/payments/${item.id}`)}>
                                        Hapus
                                    </Button>
                                )}
                            </Td>
                        </tr>
                    ))}
                </Table>

                {canManage && (
                    <form className="mt-4" onSubmit={(e) => {
                        e.preventDefault();
                        payment.post(`${base}/payments`, { onSuccess: () => payment.reset('reference', 'note') });
                    }}>
                        <h3 className="mb-2 text-sm font-semibold">Catat pembayaran</h3>
                        <div className="flex flex-wrap items-end gap-2">
                            <input type="date" className={`${inputClass} w-40`} value={payment.data.paid_at}
                                   onChange={(e) => payment.setData('paid_at', e.target.value)} required />
                            <input type="number" step="0.01" min="0.01" placeholder="Jumlah" className={`${inputClass} w-40`}
                                   value={payment.data.amount} onChange={(e) => payment.setData('amount', e.target.value)} required />
                            <select className={`${inputClass} w-32`} value={payment.data.method}
                                    onChange={(e) => payment.setData('method', e.target.value)}>
                                {methods.map((m) => <option key={m} value={m}>{m}</option>)}
                            </select>
                            <input placeholder="Referensi" className={`${inputClass} w-44`} value={payment.data.reference}
                                   onChange={(e) => payment.setData('reference', e.target.value)} />
                            <input placeholder="Catatan, mis. DP 50%" className={`${inputClass} w-52`} value={payment.data.note}
                                   onChange={(e) => payment.setData('note', e.target.value)} />
                            <Button type="submit" variant="primary" disabled={payment.processing}>Catat</Button>
                        </div>
                    </form>
                )}
            </Panel>
        </AppLayout>
    );
}
