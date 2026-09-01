import { Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, ConfirmButton, Money, PageHead, Panel, Table, Td } from '@/Components/ui';

export default function Show({ project, serviceRequest, quotation, canManage }) {
    const { auth } = usePage().props;

    // Satu halaman melayani dua konteks, sama seperti form penawaran.
    const target = project
        ? {
            base: `/projects/${project.slug}/quotations/${quotation.id}`,
            parentUrl: `/projects/${project.slug}`,
            parentLabel: project.title,
        }
        : {
            base: `/requests/${serviceRequest.id}/quotations/${quotation.id}`,
            parentUrl: `/requests/${serviceRequest.id}`,
            parentLabel: serviceRequest.name,
        };

    return (
        <AppLayout title={quotation.number}>
            <PageHead
                title={quotation.number}
                subtitle={<>
                    <Link href={target.parentUrl} className="text-accent">{target.parentLabel}</Link>
                    {' · terbit '}{quotation.issued_at}
                    {quotation.valid_until && ` · berlaku s.d. ${quotation.valid_until}`}{' '}
                    <Badge status={quotation.status} />{' '}
                    {quotation.is_expired && <Badge status="kedaluwarsa" />}
                </>}
            >
                <a href={`${target.base}/print`} target="_blank" rel="noopener"
                   className="inline-block rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink no-underline hover:border-accent">
                    Cetak
                </a>
                {canManage && <ButtonLink href={`${target.base}/edit`}>Ubah</ButtonLink>}
                {/* Penawaran kedaluwarsa harganya sudah tidak mengikat, jadi
                    tombolnya hilang sampai tanggal berlakunya diperbarui —
                    servernya menolak juga, ini supaya penolakannya tidak
                    mengejutkan. */}
                {canManage && quotation.status !== 'accepted' && ! quotation.is_expired && (
                    <Button variant="primary" onClick={() => router.put(`${target.base}/accept`)}>Tandai disetujui</Button>
                )}
                {canManage && quotation.is_expired && (
                    <ButtonLink href={`${target.base}/edit`} variant="primary">Perbarui masa berlaku</ButtonLink>
                )}
                {/* Menagih menuntut project, dan project menuntut klien. Jadi
                    penawaran calon klien yang disetujui mengarah ke konversi
                    dulu — setuju, jadi klien, baru ditagih. */}
                {canManage && quotation.status === 'accepted' && project && (
                    <ButtonLink href={`/projects/${project.slug}/invoices/create?quotation=${quotation.id}`} variant="primary">
                        Buat invoice
                    </ButtonLink>
                )}
                {canManage && quotation.status === 'accepted' && ! project && (
                    <ButtonLink href={target.parentUrl} variant="primary">
                        Konversi jadi project
                    </ButtonLink>
                )}
                {auth.user?.is_admin && (
                    <ConfirmButton message="Arsipkan penawaran ini?" confirmLabel="Ya, arsipkan"
                                   onConfirm={() => router.delete(target.base)}>
                        Arsipkan
                    </ConfirmButton>
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
