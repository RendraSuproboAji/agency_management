import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, Money, Table, Td, inputClass } from '@/Components/ui';

const EMPTY_ITEM = { description: '', qty: 1, unit: 'paket', unit_price: '' };

export default function Form({ project, quotation, statuses }) {
    const editing = Boolean(quotation.id);

    const { data, setData, post, put, processing, errors } = useForm({
        issued_at: quotation.issued_at ?? '',
        valid_until: quotation.valid_until ?? '',
        tax_percent: quotation.tax_percent ?? 11,
        status: quotation.status ?? 'draft',
        notes: quotation.notes ?? '',
        items: quotation.items?.length ? quotation.items : [{ ...EMPTY_ITEM }],
    });

    const setItem = (index, key, value) => setData('items', data.items.map(
        (item, position) => (position === index ? { ...item, [key]: value } : item),
    ));

    const subtotal = data.items.reduce((sum, item) => sum + (Number(item.qty) || 0) * (Number(item.unit_price) || 0), 0);
    const tax = subtotal * (Number(data.tax_percent) || 0) / 100;

    return (
        <AppLayout title={editing ? 'Ubah penawaran' : 'Penawaran baru'}>
            <h1 className="mb-4 text-2xl font-semibold">
                {editing ? `Ubah ${quotation.number}` : 'Penawaran baru'} — {project.title}
            </h1>

            <form onSubmit={(e) => {
                e.preventDefault();
                editing
                    ? put(`/projects/${project.slug}/quotations/${quotation.id}`)
                    : post(`/projects/${project.slug}/quotations`);
            }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Tanggal terbit *" error={errors.issued_at}>
                        <input type="date" className={inputClass} value={data.issued_at} onChange={(e) => setData('issued_at', e.target.value)} required />
                    </Field>
                    <Field label="Berlaku sampai" error={errors.valid_until}>
                        <input type="date" className={inputClass} value={data.valid_until ?? ''} onChange={(e) => setData('valid_until', e.target.value)} />
                    </Field>
                    <Field label="Pajak (%) *" error={errors.tax_percent}>
                        <input type="number" step="0.01" min="0" max="100" className={inputClass}
                               value={data.tax_percent} onChange={(e) => setData('tax_percent', e.target.value)} required />
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </Field>
                    <Field label="Catatan" error={errors.notes} wide>
                        <textarea rows={3} className={inputClass} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                </div>

                <h2 className="text-base font-semibold">Item penawaran</h2>
                {errors.items && <p className="text-sm text-danger">{errors.items}</p>}

                <Table head={['Deskripsi', 'Qty', 'Satuan', 'Harga satuan', '']}>
                    {data.items.map((item, index) => (
                        <tr key={index}>
                            <Td><input className={inputClass} value={item.description} required
                                       onChange={(e) => setItem(index, 'description', e.target.value)} /></Td>
                            <Td><input type="number" step="0.01" min="0" className={`${inputClass} w-20`} value={item.qty} required
                                       onChange={(e) => setItem(index, 'qty', e.target.value)} /></Td>
                            <Td><input className={`${inputClass} w-24`} value={item.unit ?? ''}
                                       onChange={(e) => setItem(index, 'unit', e.target.value)} /></Td>
                            <Td><input type="number" step="0.01" min="0" className={inputClass} value={item.unit_price} required
                                       onChange={(e) => setItem(index, 'unit_price', e.target.value)} /></Td>
                            <Td>
                                {data.items.length > 1 && (
                                    <Button small variant="danger" type="button"
                                            onClick={() => setData('items', data.items.filter((_, position) => position !== index))}>
                                        Hapus
                                    </Button>
                                )}
                            </Td>
                        </tr>
                    ))}
                </Table>

                <Button type="button" small className="mt-2" onClick={() => setData('items', [...data.items, { ...EMPTY_ITEM }])}>
                    Tambah baris
                </Button>

                <p className="mt-3 text-sm text-muted">
                    Subtotal <Money amount={subtotal} /> · Pajak <Money amount={tax} /> ·{' '}
                    <strong className="text-ink">Total <Money amount={subtotal + tax} /></strong>
                </p>

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href={`/projects/${project.slug}`} variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
