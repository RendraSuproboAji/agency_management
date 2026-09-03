import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHead, Panel } from '@/Components/ui';

const GROUPS = [
    { key: 'clients', title: 'Klien', all: (q) => `/clients?q=${q}` },
    { key: 'projects', title: 'Project', all: (q) => `/projects?q=${q}` },
    { key: 'requests', title: 'Request', all: (q) => `/requests?q=${q}` },
    { key: 'quotations', title: 'Penawaran', all: null },
    { key: 'invoices', title: 'Tagihan', all: (q) => `/invoices?q=${q}` },
];

export default function Index({ q, results, counts, total }) {
    return (
        <AppLayout title={q ? `Cari "${q}"` : 'Cari'}>
            <PageHead
                title={q ? `Hasil untuk "${q}"` : 'Cari'}
                subtitle={q ? `${total} hasil ditampilkan.` : 'Ketik di kotak cari untuk mulai.'}
            />

            {q && total === 0 && (
                <Panel><p className="text-sm text-muted">Tidak ada yang cocok dengan "{q}".</p></Panel>
            )}

            {GROUPS.map(({ key, title, all }) => (
                results[key].length > 0 && (
                    <Panel key={key} title={`${title} (${counts[key]})`}>
                        <ul className="text-sm">
                            {results[key].map((row) => (
                                <li key={`${key}-${row.id}`} className="border-b border-line py-2 last:border-b-0">
                                    <Link href={row.url} className="text-accent">{row.label}</Link>
                                    {row.meta && <span className="text-muted"> · {row.meta}</span>}
                                </li>
                            ))}
                        </ul>
                        {all && counts[key] > results[key].length && (
                            <Link href={all(encodeURIComponent(q))} className="mt-2 inline-block text-xs text-accent">
                                Lihat semua {counts[key]} {title.toLowerCase()}
                            </Link>
                        )}
                    </Panel>
                )
            ))}
        </AppLayout>
    );
}
