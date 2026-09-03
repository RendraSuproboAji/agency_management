import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ConfirmButton, PageHead, Panel, Table, Td } from '@/Components/ui';

export default function Index({ groups }) {
    return (
        <AppLayout title="Arsip">
            <PageHead title="Arsip" />

            <p className="mb-4 text-sm text-muted">
                Data yang diarsipkan tidak lagi muncul di daftar maupun angka dashboard, tetapi masih
                bisa dipulihkan. Mengarsipkan klien ikut mengarsipkan seluruh project, penawaran,
                invoice, dan pembayarannya; memulihkannya mengembalikan yang diarsipkan bersamaan saja.
            </p>

            {groups.map((group) => (
                <Panel key={group.type} title={`${group.label} (${group.items.length})`}>
                    <Table head={['Data', 'Diarsipkan', '']} empty="Tidak ada.">
                        {group.items.map((item) => (
                            <tr key={item.id}>
                                <Td>{item.label} {item.meta && <small className="text-muted">{item.meta}</small>}</Td>
                                <Td>{item.deleted_at}</Td>
                                <Td className="flex flex-wrap gap-1">
                                    <Button small onClick={() => router.put(`/archive/${group.type}/${item.id}/restore`)}>Pulihkan</Button>
                                    <ConfirmButton small confirmLabel="Ya, hapus permanen"
                                                   message="Hapus permanen? Data dan berkasnya tidak bisa dikembalikan."
                                                   onConfirm={() => router.delete(`/archive/${group.type}/${item.id}`)}>
                                        Hapus permanen
                                    </ConfirmButton>
                                </Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
            ))}
        </AppLayout>
    );
}
