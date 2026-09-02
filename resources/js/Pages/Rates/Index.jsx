import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, ButtonLink, ConfirmButton, Money, PageHead, Panel, Table, Td } from '@/Components/ui';

export default function Index({ rates }) {
    return (
        <AppLayout title="Kartu tarif">
            <PageHead title="Kartu tarif" subtitle="Dasar hitungan penawaran, per jenis layanan dan satuan.">
                <ButtonLink href="/rates/create" variant="primary">Tambah tarif</ButtonLink>
            </PageHead>

            <Panel>
                <p className="text-sm text-muted">
                    Penawaran baru bisa menghitung barisnya sendiri dari tarif ini, memakai luas area
                    dan jumlah scene project. Hasilnya usulan — setiap angkanya masih bisa disunting
                    sebelum dikirim.
                </p>
            </Panel>

            <Table head={['Layanan', 'Satuan', 'Keterangan', 'Harga', 'Biaya minimum', 'Status', '']}
                   empty="Belum ada tarif. Tambahkan satu supaya kalkulator penawaran bisa dipakai.">
                {rates.map((rate) => (
                    <tr key={rate.id}>
                        <Td>{rate.service_type.replace(/_/g, ' ')}</Td>
                        <Td>{rate.unit_label}</Td>
                        <Td>{rate.label}</Td>
                        <Td><Money amount={rate.unit_price} /></Td>
                        <Td>{rate.min_charge ? <Money amount={rate.min_charge} /> : '—'}</Td>
                        <Td><Badge status={rate.active ? 'aktif' : 'nonaktif'} /></Td>
                        <Td className="flex flex-wrap gap-1">
                            <ButtonLink href={`/rates/${rate.id}/edit`} small>Ubah</ButtonLink>
                            <ConfirmButton small message="Hapus tarif ini?" confirmLabel="Ya, hapus"
                                           onConfirm={() => router.delete(`/rates/${rate.id}`)}>
                                Hapus
                            </ConfirmButton>
                        </Td>
                    </tr>
                ))}
            </Table>
        </AppLayout>
    );
}
