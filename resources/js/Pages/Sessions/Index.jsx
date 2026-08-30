import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, ButtonLink, PageHead, Pagination, Table, Td, inputClass } from '@/Components/ui';

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

export default function Index({ mode, sessions, calendar, filters, statuses }) {
    const [form, setForm] = useState({ status: filters.status ?? '', mine: Boolean(filters.mine) });
    const isCalendar = mode === 'calendar';

    const submit = (event) => {
        event.preventDefault();
        router.get('/sessions', { ...form, ...(isCalendar ? { view: 'calendar', month: calendar.month } : {}) }, { preserveState: true });
    };

    const switchTo = (view) => ({
        ...(form.status ? { status: form.status } : {}),
        ...(form.mine ? { mine: 1 } : {}),
        ...(view === 'calendar' ? { view: 'calendar' } : {}),
    });

    return (
        <AppLayout title="Sesi capture">
            <PageHead title="Agenda pengambilan gambar">
                <ButtonLink href={`/sessions?${new URLSearchParams(switchTo(isCalendar ? 'table' : 'calendar'))}`}>
                    {isCalendar ? 'Tampilan tabel' : 'Tampilan kalender'}
                </ButtonLink>
            </PageHead>

            <form onSubmit={submit} className="mb-3 flex flex-wrap items-center gap-2">
                <select className={`${inputClass} w-40`} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="">Semua status</option>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <label className="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" checked={form.mine} onChange={(e) => setForm({ ...form, mine: e.target.checked })} /> Sesi saya
                </label>
                <Button type="submit">Filter</Button>
            </form>

            {isCalendar ? <Calendar calendar={calendar} filters={form} /> : <>
                <Table head={['Jadwal', 'Project', 'Klien', 'Kru', 'Peralatan', 'Status']} empty="Belum ada sesi.">
                    {sessions.data.map((session) => (
                        <tr key={session.id}>
                            <Td>{session.scheduled_at}</Td>
                            <Td><Link href={`/projects/${session.project_slug}`} className="text-accent">{session.project_title}</Link></Td>
                            <Td>{session.client_name}</Td>
                            <Td>{session.crew_name ?? '—'}</Td>
                            <Td>{session.equipment || '—'}</Td>
                            <Td><Badge status={session.status} /></Td>
                        </tr>
                    ))}
                </Table>

                <Pagination links={sessions.links} />
            </>}
        </AppLayout>
    );
}

function Calendar({ calendar, filters }) {
    const byDate = new Map();

    for (const session of calendar.sessions) {
        if (!byDate.has(session.date)) byDate.set(session.date, []);
        byDate.get(session.date).push(session);
    }

    const link = (month) => `/sessions?${new URLSearchParams({
        view: 'calendar',
        month,
        ...(filters.status ? { status: filters.status } : {}),
        ...(filters.mine ? { mine: 1 } : {}),
    })}`;

    return (
        <section>
            <div className="mb-3 flex items-center justify-between gap-3">
                <Link href={link(calendar.previous)} className="text-accent">← Bulan sebelumnya</Link>
                <strong>{calendar.label}</strong>
                <Link href={link(calendar.next)} className="text-accent">Bulan berikutnya →</Link>
            </div>

            <div className="grid grid-cols-7 gap-px overflow-x-auto rounded border border-line bg-line text-sm">
                {WEEKDAYS.map((day) => (
                    <div key={day} className="bg-raised px-2 py-1 text-center text-xs text-muted">{day}</div>
                ))}

                {Array.from({ length: calendar.leading }, (_, index) => (
                    <div key={`lead-${index}`} className="min-h-24 bg-raised" />
                ))}

                {Array.from({ length: calendar.days }, (_, index) => {
                    const date = `${calendar.month}-${String(index + 1).padStart(2, '0')}`;
                    const items = byDate.get(date) ?? [];

                    return (
                        <div key={date} className="min-h-24 bg-surface p-1">
                            <div className={`text-xs ${date === calendar.today ? 'font-semibold text-accent' : 'text-muted'}`}>
                                {index + 1}
                            </div>
                            {items.map((session) => (
                                <Link key={session.id} href={`/projects/${session.project_slug}`}
                                      className="mt-1 block rounded bg-raised px-1 py-0.5 text-xs hover:text-accent">
                                    <span className="text-muted">{session.time}</span> {session.project_title}
                                </Link>
                            ))}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
