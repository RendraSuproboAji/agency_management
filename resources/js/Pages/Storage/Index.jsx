import { Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, ConfirmButton, PageHead, Pagination, Panel, Table, Td } from '@/Components/ui';

const LABELS = {
    tanpa_backup: 'tanpa backup',
    ditahan: 'ditahan',
    siap_dibersihkan: 'siap dibersihkan',
    sudah_dibersihkan: 'sudah dibersihkan',
};

function terabytes(gb) {
    return gb >= 1024 ? `${(gb / 1024).toFixed(2)} TB` : `${gb.toFixed(2)} GB`;
}

function SessionRows({ rows, purgeable = false }) {
    return rows.map((row) => (
        <tr key={row.id}>
            <Td>
                <Link href={`/projects/${row.project_slug}`} className="text-accent">{row.project_title}</Link>
                <br /><small className="text-muted">{row.client_name}</small>
            </Td>
            <Td>{row.scheduled_at}</Td>
            <Td>{terabytes(row.size_gb)}</Td>
            <Td>{row.backup_location ?? <Badge status="tanpa backup" />}</Td>
            <Td>
                <Badge status={LABELS[row.raw_state]} />
                {row.purged_at && <><br /><small className="text-muted">{row.purged_at}</small></>}
            </Td>
            <Td>
                {purgeable && (
                    <ConfirmButton small confirmLabel="Ya, sudah dihapus"
                                   message="Tandai data mentah sesi ini sudah dihapus dari penyimpanan? Catatannya tetap tersimpan, tetapi totalnya berkurang."
                                   onConfirm={() => router.put(`/raw-data/sessions/${row.id}/purge`)}>
                        Tandai sudah dihapus
                    </ConfirmButton>
                )}
            </Td>
        </tr>
    ));
}

export default function Index({ totalGb, heldSessions, atRisk, atRiskCount, ready, readyCount, byClient, sessions }) {
    const head = ['Project', 'Sesi', 'Ukuran', 'Backup', 'Status', ''];

    return (
        <AppLayout title="Penyimpanan">
            <PageHead title="Penyimpanan"
                      subtitle={`${terabytes(totalGb)} data mentah sedang ditahan dari ${heldSessions} sesi.`} />

            {atRiskCount > 0 && (
                <Panel title={`Tanpa backup — ${atRiskCount} sesi`}>
                    <p className="mb-2 text-sm text-danger">
                        Data mentah ini tidak punya salinan yang tercatat. Selama begitu, ia tidak akan
                        pernah dinyatakan siap dibersihkan.
                    </p>
                    <Table head={head}><SessionRows rows={atRisk} /></Table>
                    {atRiskCount > atRisk.length && (
                        <p className="mt-2 text-xs text-muted">
                            Menampilkan {atRisk.length} teratas dari {atRiskCount}.
                        </p>
                    )}
                </Panel>
            )}

            <Panel title={`Siap dibersihkan — ${readyCount} sesi`}>
                {readyCount === 0
                    ? <p className="text-sm text-muted">Belum ada yang boleh dibersihkan.</p>
                    : <>
                        <Table head={head}><SessionRows rows={ready} purgeable /></Table>
                        {readyCount > ready.length && (
                            <p className="mt-2 text-xs text-muted">
                                Menampilkan {ready.length} teratas dari {readyCount}.
                            </p>
                        )}
                    </>}
            </Panel>

            <Panel title="Per klien">
                <Table head={['Klien', 'Sesi', 'Ditahan']} empty="Belum ada data mentah tercatat.">
                    {byClient.map((row) => (
                        <tr key={row.client_name}>
                            <Td>{row.client_name}</Td>
                            <Td>{row.sessions}</Td>
                            <Td>{terabytes(row.held_gb)}</Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Semua sesi">
                <Table head={head} empty="Belum ada sesi dengan data mentah tercatat.">
                    <SessionRows rows={sessions.data} />
                </Table>
                <Pagination links={sessions.links} />
            </Panel>
        </AppLayout>
    );
}
