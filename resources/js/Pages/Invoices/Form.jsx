import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ project, invoice, quotations, fromQuotation, statuses }) {
    const editing = Boolean(invoice.id);

    const { data, setData, post, put, processing, errors } = useForm({
        issued_at: invoice.issued_at ?? '',
        due_at: invoice.due_at ?? '',
        amount: invoice.amount ?? '',
        status: invoice.status ?? 'draft',
        quotation_id: invoice.quotation_id ?? '',
        notes: invoice.notes ?? '',
    });

    return (
        <AppLayout title={editing ? 'Ubah invoice' : 'Invoice baru'}>
            <h1 className="mb-1 text-2xl font-semibold">
                {editing ? `Ubah ${invoice.number}` : 'Invoice baru'} — {project.title}
            </h1>
            {fromQuotation && <p className="mb-4 text-sm text-muted">Nilai disalin dari penawaran {fromQuotation}.</p>}

            <form onSubmit={(e) => {
                e.preventDefault();
                editing ? put(`/projects/${project.slug}/invoices/${invoice.id}`) : post(`/projects/${project.slug}/invoices`);
            }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Tanggal terbit *" error={errors.issued_at}>
                        <input type="date" className={inputClass} value={data.issued_at} onChange={(e) => setData('issued_at', e.target.value)} required />
                    </Field>
                    <Field label="Jatuh tempo" error={errors.due_at}>
                        <input type="date" className={inputClass} value={data.due_at ?? ''} onChange={(e) => setData('due_at', e.target.value)} />
                    </Field>
                    <Field label="Nilai tagihan (Rp) *" error={errors.amount}>
                        <input type="number" step="0.01" min="0" className={inputClass} value={data.amount}
                               onChange={(e) => setData('amount', e.target.value)} required />
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </Field>
                    <Field label="Penawaran terkait" error={errors.quotation_id} wide>
                        <select className={inputClass} value={data.quotation_id ?? ''} onChange={(e) => setData('quotation_id', e.target.value)}>
                            <option value="">— tidak ada —</option>
                            {quotations.map((q) => <option key={q.id} value={q.id}>{q.number}</option>)}
                        </select>
                    </Field>
                    <Field label="Catatan" error={errors.notes} wide>
                        <textarea rows={3} className={inputClass} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-4 flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href={`/projects/${project.slug}`} variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
