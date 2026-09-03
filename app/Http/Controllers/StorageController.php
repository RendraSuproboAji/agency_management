<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Support\ActivityLogger;
use App\Support\RawData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    /** Cukup untuk bertindak; daftar tindakan sepanjang ribuan tidak menolong. */
    private const PANEL_LIMIT = 50;

    public function index(): Response
    {
        $held = CaptureSession::query()
            ->whereHas('project')
            ->whereNotNull('raw_size_gb')
            ->whereNull('raw_purged_at');

        return Inertia::render('Storage/Index', [
            // Agregat SQL, bukan menjumlah baris di PHP: totalnya tidak
            // menuntut seluruh tabel dimuat ke memori.
            'totalGb' => round((float) (clone $held)->sum('raw_size_gb'), 2),
            'heldSessions' => (clone $held)->count(),
            'byClient' => $this->byClient(),
            ...$this->panels(),
            'sessions' => $this->paginatedSessions(),
        ]);
    }

    /**
     * Dua daftar tindakan: yang tanpa salinan, dan yang sudah boleh dibersihkan.
     *
     * Statusnya dihitung di PHP karena retensinya bergantung pada deliverable
     * project, jadi kandidatnya disempitkan dulu lewat SQL — dan relasi yang
     * dibutuhkan RawData dimuat sejak awal supaya tidak satu kueri per sesi.
     *
     * @return array<string, mixed>
     */
    private function panels(): array
    {
        $candidates = CaptureSession::query()
            ->whereHas('project')
            ->whereNotNull('raw_size_gb')
            ->whereNull('raw_purged_at')
            ->with('project.client', 'project.deliverables')
            ->orderByDesc('scheduled_at')
            ->get();

        $atRisk = $candidates->filter(fn (CaptureSession $s) => RawData::status($s) === RawData::NO_BACKUP);
        $ready = $candidates->filter(fn (CaptureSession $s) => RawData::status($s) === RawData::READY);

        return [
            'atRisk' => $atRisk->take(self::PANEL_LIMIT)->map(fn ($s) => $this->row($s))->values(),
            'atRiskCount' => $atRisk->count(),
            'ready' => $ready->take(self::PANEL_LIMIT)->map(fn ($s) => $this->row($s))->values(),
            'readyCount' => $ready->count(),
        ];
    }

    private function paginatedSessions(): LengthAwarePaginator
    {
        return CaptureSession::query()
            ->whereHas('project')
            ->whereNotNull('raw_size_gb')
            ->with('project.client', 'project.deliverables')
            ->orderByDesc('scheduled_at')
            ->paginate(30)
            ->through(fn (CaptureSession $session) => $this->row($session));
    }

    /** @return array<string, mixed> */
    private function row(CaptureSession $session): array
    {
        return [
            'id' => $session->id,
            'raw_state' => RawData::status($session),
            'size_gb' => (float) $session->raw_size_gb,
            'backup_location' => $session->backup_location,
            'scheduled_at' => $session->scheduled_at->format('d M Y'),
            'purged_at' => $session->raw_purged_at?->format('d M Y'),
            'project_slug' => $session->project->slug,
            'project_title' => $session->project->title,
            'client_name' => $session->project->client->name,
        ];
    }

    public function purge(Request $request, CaptureSession $session): RedirectResponse
    {
        abort_unless($session->project->isManageableBy($request->user()), 403);

        if (RawData::status($session) !== RawData::READY) {
            return back()->withErrors([
                'session' => 'Data mentah sesi ini belum boleh dihapus: pastikan seluruh deliverable sudah '
                    .'disetujui, masa retensinya lewat, dan salinannya tercatat.',
            ]);
        }

        $session->update(['raw_purged_at' => now()]);

        ActivityLogger::log(
            $session,
            'raw.purged',
            'Menandai data mentah sesi '.$session->scheduled_at->format('d M Y').' sudah dihapus ('
                .$session->raw_size_gb.' GB).',
        );

        return back()->with('status', 'Sesi ditandai sudah dibersihkan.');
    }

    /**
     * Ditahan per klien, dihitung di basis data.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function byClient(): Collection
    {
        return CaptureSession::query()
            ->join('projects', 'projects.id', '=', 'capture_sessions.project_id')
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->whereNull('projects.deleted_at')
            ->whereNull('capture_sessions.deleted_at')
            ->whereNotNull('capture_sessions.raw_size_gb')
            ->whereNull('capture_sessions.raw_purged_at')
            ->groupBy('clients.name')
            ->orderByDesc('held_gb')
            ->get([
                'clients.name as client_name',
                DB::raw('sum(capture_sessions.raw_size_gb) as held_gb'),
                DB::raw('count(*) as sessions'),
            ])
            ->map(fn ($row) => [
                'client_name' => $row->client_name,
                'held_gb' => round((float) $row->held_gb, 2),
                'sessions' => (int) $row->sessions,
            ]);
    }
}
