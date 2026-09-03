import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, ConfirmButton, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

export default function Index({ equipment, filters, categories, statuses, isAdmin }) {
    const [form, setForm] = useState({ q: filters.q ?? '', category: filters.category ?? '', status: filters.status ?? '' });

    return (
        <AppLayout title="Peralatan">
            <PageHead title="Peralatan">
                <ButtonLink href="/equipment/create" variant="primary">Tambah peralatan</ButtonLink>
            </PageHead>

            <form onSubmit={(e) => { e.preventDefault(); router.get('/equipment', form, { preserveState: true }); }}
                  className="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" placeholder="Cari nama, kode, no. seri…" className={`${inputClass} sm:w-56`}
                       value={form.q} onChange={(e) => setForm({ ...form, q: e.target.value })} />
                <select className={`${inputClass} sm:w-36`} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
                    <option value="">Semua kategori</option>
                    {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                </select>
                <select className={`${inputClass} sm:w-36`} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Nama', 'Kode', 'Kategori', 'No. seri', 'Status', '']} empty="Belum ada peralatan.">
                {equipment.data.map((item) => (
                    <tr key={item.id}>
                        <Td>{item.name}</Td>
                        <Td>{item.code}</Td>
                        <Td>{item.category}</Td>
                        <Td>{item.serial_number || '—'}</Td>
                        <Td><Badge status={item.status} /></Td>
                        <Td className="flex flex-wrap gap-1">
                            <ButtonLink href={`/equipment/${item.id}/edit`} small>Ubah</ButtonLink>
                            {isAdmin && (
                                <ConfirmButton small message="Arsipkan peralatan ini?" confirmLabel="Ya, arsipkan"
                                               onConfirm={() => router.delete(`/equipment/${item.id}`)}>
                                    Arsipkan
                                </ConfirmButton>
                            )}
                        </Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={equipment.links} />
        </AppLayout>
    );
}
