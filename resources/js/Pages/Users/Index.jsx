import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, PageHead, Pagination, Table, Td } from '@/Components/ui';

export default function Index({ users }) {
    return (
        <AppLayout title="Pengguna">
            <PageHead title="Pengguna">
                <ButtonLink href="/users/create" variant="primary">Tambah pengguna</ButtonLink>
            </PageHead>

            <Table head={['Nama', 'Email', 'Peran', 'Project', '']} empty="Belum ada pengguna.">
                {users.data.map((user) => (
                    <tr key={user.id}>
                        <Td>{user.name}</Td>
                        <Td>{user.email}</Td>
                        <Td><Badge status={user.role} /></Td>
                        <Td>{user.owned_projects_count}</Td>
                        <Td className="flex flex-wrap gap-1">
                            <ButtonLink href={`/users/${user.id}/edit`} small>Ubah</ButtonLink>
                            <Button small variant="danger"
                                    onClick={() => window.confirm('Hapus pengguna ini?') && router.delete(`/users/${user.id}`)}>
                                Hapus
                            </Button>
                        </Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={users.links} />
        </AppLayout>
    );
}
