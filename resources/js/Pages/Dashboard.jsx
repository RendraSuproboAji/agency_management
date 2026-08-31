import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Money, Panel } from '@/Components/ui';

function Stat({ value, label }) {
    return (
        <div className="rounded-lg border border-line bg-surface p-4">
            <span className="block text-2xl font-bold">{value}</span>
            <span className="text-xs text-muted">{label}</span>
        </div>
    );
}

function Row({ href, title, meta }) {
    return (
        <div className="flex flex-wrap justify-between gap-2 border-b border-line py-2 last:border-b-0">
            <Link href={href} className="text-accent">{title}</Link>
            <span className="text-sm text-muted">{meta}</span>
        </div>
    );
}

export default function Dashboard({
    statuses, countsByStatus, clientCount, activeProjectCount, newRequestCount,
    latestRequests, upcomingDeadlines, upcomingSessions, pendingDeliverables,
    receivable, dueInvoices, overdueInvoices, overdueCount, overdueTotal, runningJobs,
}) {
    return (
        <AppLayout title="Dashboard">
            <h1 className="mb-4 text-2xl font-semibold">Dashboard</h1>

            <div className="mb-4 grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(150px,1fr))]">
                <Stat value={newRequestCount} label="Request baru" />
                <Stat value={clientCount} label="Klien" />
                <Stat value={activeProjectCount} label="Project berjalan" />
                <Stat value={upcomingSessions.length} label="Sesi terjadwal" />
                <Stat value={pendingDeliverables.length} label="Menunggu approval" />
                <Stat value={runningJobs.length} label="Job berjalan" />
                <Stat value={<Money amount={receivable} />} label="Piutang berjalan" />
            </div>

            <Panel title="Pipeline produksi">
                <div className="flex flex-wrap gap-2">
                    {statuses.map((status) => (
                        <Link key={status} href={`/projects?status=${status}`}
                              className="flex-1 basis-28 rounded-lg border border-line bg-raised px-2 py-3 text-center text-ink no-underline hover:border-accent">
                            <span className="block text-xl font-bold">{countsByStatus[status] ?? 0}</span>
                            <span className="text-xs capitalize text-muted">{status}</span>
                        </Link>
                    ))}
                </div>
            </Panel>

            {latestRequests.length > 0 && (
                <Panel title="Request baru">
                    {latestRequests.map((item) => (
                        <Row key={item.id} href={`/requests/${item.id}`} title={item.company || item.name} meta={item.service_type} />
                    ))}
                </Panel>
            )}

            <div className="gap-4 md:grid md:grid-cols-2">
                <Panel title="Deadline terdekat">
                    {upcomingDeadlines.length === 0 && <p className="text-sm text-muted">Belum ada deadline.</p>}
                    {upcomingDeadlines.map((project) => (
                        <Row key={project.id} href={`/projects/${project.slug}`} title={project.title}
                             meta={`${project.client_name} · ${project.deadline}`} />
                    ))}
                </Panel>

                <Panel title="Sesi pengambilan gambar">
                    {upcomingSessions.length === 0 && <p className="text-sm text-muted">Tidak ada sesi terjadwal.</p>}
                    {upcomingSessions.map((session) => (
                        <Row key={session.id} href={`/projects/${session.project_slug}`} title={session.project_title}
                             meta={`${session.scheduled_at}${session.crew_name ? ` · ${session.crew_name}` : ''}`} />
                    ))}
                </Panel>
            </div>

            {runningJobs.length > 0 && (
                <Panel title="Job processing berjalan">
                    {runningJobs.map((job) => (
                        <Row key={job.id} href={`/projects/${job.project_slug}`} title={`${job.kind} · ${job.project_title}`} meta={job.machine} />
                    ))}
                </Panel>
            )}

            {overdueCount > 0 && (
                <Panel title={`Lewat jatuh tempo (${overdueCount})`}>
                    <p className="mb-2 text-sm text-danger">
                        Total belum tertagih <Money amount={overdueTotal} />
                    </p>
                    {overdueInvoices.map((invoice) => (
                        <Row key={invoice.id} href={`/projects/${invoice.project_slug}/invoices/${invoice.id}`}
                             title={`${invoice.number} · ${invoice.client_name}`}
                             meta={<span className="text-danger">
                                 lewat {invoice.days_overdue} hari · sisa <Money amount={invoice.outstanding} />
                             </span>} />
                    ))}
                </Panel>
            )}

            <Panel title="Akan jatuh tempo">
                {dueInvoices.length === 0 && <p className="text-sm text-muted">Tidak ada tagihan yang akan jatuh tempo.</p>}
                {dueInvoices.map((invoice) => (
                    <Row key={invoice.id} href={`/projects/${invoice.project_slug}/invoices/${invoice.id}`}
                         title={`${invoice.number} · ${invoice.client_name}`}
                         meta={<>jatuh tempo {invoice.due_at} · sisa <Money amount={invoice.outstanding} /></>} />
                ))}
            </Panel>

            <Panel title="Deliverable menunggu approval">
                {pendingDeliverables.length === 0 && <p className="text-sm text-muted">Tidak ada yang menunggu.</p>}
                {pendingDeliverables.map((deliverable) => (
                    <Row key={deliverable.id} href={`/projects/${deliverable.project_slug}`}
                         title={`${deliverable.title} (v${deliverable.version})`}
                         meta={`${deliverable.client_name} · ${deliverable.project_title}`} />
                ))}
            </Panel>
        </AppLayout>
    );
}
