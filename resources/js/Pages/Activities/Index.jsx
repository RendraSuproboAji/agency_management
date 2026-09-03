import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

const LABELS = {
    project: 'Project', client: 'Klien', equipment: 'Peralatan',
    deliverable: 'Deliverable', quotation: 'Penawaran', invoice: 'Invoice',
    sesi: 'Sesi capture', job: 'Job', lampiran: 'Lampiran', lainnya: 'Lainnya',
};

export default function Index({ activities, filters, subjects }) {
    const [subject, setSubject] = useState(filters.subject ?? '');

    const submit = (event) => {
        event.preventDefault();
        router.get('/activities', subject ? { subject } : {}, { preserveState: true });
    };

    return (
        <AppLayout title="Riwayat">
            <PageHead title="Riwayat" subtitle="Semua langkah yang tercatat, terbaru dulu." />

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-center gap-2">
                <select className={`${inputClass} sm:w-44`} value={subject} onChange={(e) => setSubject(e.target.value)}>
                    <option value="">Semua jenis</option>
                    {subjects.map((item) => <option key={item} value={item}>{LABELS[item]}</option>)}
                </select>
                <Button type="submit">Filter</Button>
            </form>

            <Table head={['Waktu', 'Jenis', 'Kejadian', 'Oleh']} empty="Belum ada yang tercatat.">
                {activities.data.map((activity) => (
                    <tr key={activity.id}>
                        <Td>{activity.created_at}</Td>
                        <Td><Badge status={LABELS[activity.subject] ?? activity.subject} /></Td>
                        <Td>
                            {activity.description}
                            {activity.project && <>
                                {' '}
                                <Link href={`/projects/${activity.project.slug}`} className="text-accent">
                                    {activity.project.title}
                                </Link>
                            </>}
                        </Td>
                        <Td>{activity.actor}</Td>
                    </tr>
                ))}
            </Table>

            <Pagination links={activities.links} />
        </AppLayout>
    );
}
