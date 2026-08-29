import { Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Badge, Money, Table, Td } from '@/Components/ui';

export default function Dashboard({ projects }) {
    return (
        <PortalLayout title="Project saya">
            <h1 className="mb-4 text-2xl font-semibold">Project Anda</h1>

            <Table head={['Project', 'Layanan', 'Status', 'Deadline', 'Sisa tagihan']} empty="Belum ada project.">
                {projects.map((project) => (
                    <tr key={project.id}>
                        <Td><Link href={`/portal/projects/${project.slug}`} className="text-accent">{project.title}</Link></Td>
                        <Td>{project.service_type}</Td>
                        <Td><Badge status={project.status} /></Td>
                        <Td>{project.deadline ?? '—'}</Td>
                        <Td><Money amount={project.outstanding} /></Td>
                    </tr>
                ))}
            </Table>
        </PortalLayout>
    );
}
