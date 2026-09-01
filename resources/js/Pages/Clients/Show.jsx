import { Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, ButtonLink, ConfirmButton, DetailList, PageHead, Panel, Table, Td } from '@/Components/ui';

export default function Show({ client }) {
    const { auth } = usePage().props;

    return (
        <AppLayout title={client.name}>
            <PageHead title={client.name} subtitle={<><Badge status={client.status} /> {client.industry}</>}>
                <ButtonLink href={`/clients/${client.slug}/edit`}>Ubah</ButtonLink>
                <ButtonLink href={`/projects/create?client_id=${client.id}`} variant="primary">Project baru</ButtonLink>
                {auth.user?.is_admin && (
                    <ConfirmButton message="Arsipkan klien beserta seluruh project-nya?" confirmLabel="Ya, arsipkan"
                                   onConfirm={() => router.delete(`/clients/${client.slug}`)}>
                        Arsipkan
                    </ConfirmButton>
                )}
            </PageHead>

            <Panel>
                <DetailList items={[
                    { label: 'Narahubung', value: client.contact_name },
                    { label: 'Email', value: client.email },
                    { label: 'Telepon', value: client.phone },
                    { label: 'Alamat', value: client.address },
                    { label: 'Portal', value: client.portal_enabled ? 'aktif' : 'nonaktif' },
                ]} />
                {client.notes && <p className="mt-3 whitespace-pre-line text-sm text-muted">{client.notes}</p>}
            </Panel>

            <Panel title="Project">
                <Table head={['Judul', 'Layanan', 'Status', 'Deadline']} empty="Belum ada project untuk klien ini.">
                    {client.projects.map((project) => (
                        <tr key={project.id}>
                            <Td><Link href={`/projects/${project.slug}`} className="text-accent">{project.title}</Link></Td>
                            <Td>{project.service_type}</Td>
                            <Td><Badge status={project.status} /></Td>
                            <Td>{project.deadline ?? '—'}</Td>
                        </tr>
                    ))}
                </Table>
            </Panel>
        </AppLayout>
    );
}
