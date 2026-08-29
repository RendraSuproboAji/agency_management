import { Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, Money, PageHead, Panel, Table, Td } from '@/Components/ui';

export default function Show({ project, quotation, canManage }) {
    const { auth } = usePage().props;
    const base = `/projects/${project.slug}/quotations/${quotation.id}`;

    return (
        <AppLayout title={quotation.number}>
            <PageHead
                title={quotation.number}
                subtitle={<>
                    <Link href={`/projects/${project.slug}`} className="text-accent">{project.title}</Link>
                    {' · terbit '}{quotation.issued_at}
                    {quotation.valid_until && ` · berlaku s.d. ${quotation.valid_until}`}{' '}
                    <Badge status={quotation.status} />
                </>}
            >
                <a href={`${base}/print`} target="_blank" rel="noopener"
                   className="inline-block rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink no-underline hover:border-accent">
                    Cetak
                </a>
                {canManage && <ButtonLink href={`${base}/edit`}>Ubah</ButtonLink>}
                {canManage && quotation.status !== 'accepted' && (
                    <Button variant="primary" onClick={() => router.put(`${base}/accept`)}>Tandai disetujui</Button>
                )}
                {canManage && quotation.status === 'accepted' && (
                    <ButtonLink href={`/projects/${project.slug}/invoices/create?quotation=${quotation.id}`} variant="primary">
                        Buat invoice
                    </ButtonLink>
                )}
                {auth.user?.is_admin && (
                    <Button variant="danger"
                            onClick={() => window.confirm('Arsipkan penawaran ini?') && router.delete(base)}>
                        Arsipkan
                    </Button>
                )}
            </PageHead>

            <Panel>
                <Table head={['Deskripsi', 'Qty', 'Harga satuan', 'Jumlah']}>
                    {quotation.items.map((item) => (
                        <tr key={item.id}>
                            <Td>{item.description}</Td>
                            <Td>{item.qty} {item.unit}</Td>
                            <Td><Money amount={item.unit_price} /></Td>
                            <Td className="text-right"><Money amount={item.line_total} /></Td>
                        </tr>
                    ))}
                </Table>

                <div className="mt-3 space-y-1 text-right text-sm">
                    <div>Subtotal <Money amount={quotation.subtotal} /></div>
                    <div>Pajak {quotation.tax_percent}% <Money amount={quotation.tax_amount} /></div>
                    <div className="text-base font-semibold">Total <Money amount={quotation.total} /></div>
                </div>

                {quotation.notes && <p className="mt-3 whitespace-pre-line text-sm text-muted">{quotation.notes}</p>}
            </Panel>

            {quotation.invoices.length > 0 && (
                <Panel title="Invoice dari penawaran ini">
                    {quotation.invoices.map((invoice) => (
                        <div key={invoice.id} className="flex justify-between border-b border-line py-2 last:border-b-0">
                            <Link href={`/projects/${project.slug}/invoices/${invoice.id}`} className="text-accent">{invoice.number}</Link>
                            <span className="text-sm text-muted"><Money amount={invoice.amount} /> · {invoice.status}</span>
                        </div>
                    ))}
                </Panel>
            )}
        </AppLayout>
    );
}
