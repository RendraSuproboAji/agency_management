import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useServerState } from '@/useServerState';
import { Badge, Button, ButtonLink, DetailList, Field, Money, PageHead, Panel, Table, Td, inputClass } from '@/Components/ui';

export default function Show({ serviceRequest, clients, statuses }) {
    const { auth } = usePage().props;
    const [status, setStatus] = useServerState(serviceRequest.status);

    const convert = useForm({
        title: `Tur 3D ${serviceRequest.company || serviceRequest.name}`,
        client_id: '',
    });

    return (
        <AppLayout title="Request">
            <PageHead
                title={serviceRequest.company || serviceRequest.name}
                subtitle={<>Masuk {serviceRequest.created_at} <Badge status={serviceRequest.status} /></>}
            >
                {/* Permintaan yang sudah punya penawaran tidak bisa dihapus —
                    tombolnya disembunyikan supaya penolakannya tidak mengejutkan. */}
                {auth.user?.is_admin && serviceRequest.quotations.length === 0 && (
                    <Button variant="danger"
                            onClick={() => window.confirm('Hapus request ini?') && router.delete(`/requests/${serviceRequest.id}`)}>
                        Hapus
                    </Button>
                )}
            </PageHead>

            <Panel>
                <DetailList items={[
                    { label: 'Nama', value: serviceRequest.name },
                    { label: 'Email', value: serviceRequest.email },
                    { label: 'Telepon', value: serviceRequest.phone },
                    { label: 'Layanan', value: serviceRequest.service_type },
                    { label: 'Lokasi', value: serviceRequest.site_location },
                    { label: 'Luas area', value: serviceRequest.area_sqm ? `${serviceRequest.area_sqm} m²` : null },
                ]} />

                {serviceRequest.message && <>
                    <h3 className="mt-4 text-sm font-semibold">Kebutuhan</h3>
                    <p className="whitespace-pre-line text-sm text-muted">{serviceRequest.message}</p>
                </>}

                <form className="mt-4 flex flex-wrap items-center gap-2"
                      onSubmit={(e) => { e.preventDefault(); router.put(`/requests/${serviceRequest.id}/status`, { status }); }}>
                    <label className="text-xs text-muted">Status</label>
                    <select className={`${inputClass} sm:w-40`} value={status} onChange={(e) => setStatus(e.target.value)}>
                        {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                    <Button type="submit">Simpan status</Button>
                </form>
            </Panel>

            <Panel
                title="Penawaran"
                actions={serviceRequest.status !== 'converted' && (
                    <ButtonLink href={`/requests/${serviceRequest.id}/quotations/create`} small variant="primary">
                        Buat penawaran
                    </ButtonLink>
                )}
            >
                <p className="mb-2 text-sm text-muted">
                    Penawaran bisa dikirim ke calon klien tanpa membuat data klien dan project lebih dulu.
                    Saat request ini dikonversi, penawarannya ikut pindah ke project barunya.
                </p>

                <Table head={['Nomor', 'Tanggal', 'Nilai', 'Status', '']} empty="Belum ada penawaran.">
                    {serviceRequest.quotations.map((quotation) => (
                        <tr key={quotation.id}>
                            <Td>{quotation.number}</Td>
                            <Td>{quotation.issued_at}</Td>
                            <Td><Money amount={quotation.total} /></Td>
                            <Td><Badge status={quotation.status} /></Td>
                            <Td>
                                <a href={quotation.print_url} className="text-accent">Cetak</a>
                            </Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Konversi jadi project">
                {serviceRequest.converted_project ? (
                    <p>
                        Sudah dikonversi menjadi{' '}
                        <Link href={`/projects/${serviceRequest.converted_project.slug}`} className="text-accent">
                            {serviceRequest.converted_project.title}
                        </Link>.
                    </p>
                ) : (
                    <form onSubmit={(e) => { e.preventDefault(); convert.post(`/requests/${serviceRequest.id}/convert`); }}>
                        <div className="gap-x-4 sm:grid sm:grid-cols-2">
                            <Field label="Judul project *" error={convert.errors.title}>
                                <input className={inputClass} value={convert.data.title}
                                       onChange={(e) => convert.setData('title', e.target.value)} required />
                            </Field>
                            <Field label="Klien" error={convert.errors.client_id}>
                                <select className={inputClass} value={convert.data.client_id}
                                        onChange={(e) => convert.setData('client_id', e.target.value)}>
                                    <option value="">— buat klien baru dari data request —</option>
                                    {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </Field>
                        </div>
                        <Button type="submit" variant="primary" disabled={convert.processing}>Konversi</Button>
                    </form>
                )}
            </Panel>
        </AppLayout>
    );
}
