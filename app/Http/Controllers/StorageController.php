<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Support\ActivityLogger;
use App\Support\RawData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    public function index(): Response
    {
        $sessions = CaptureSession::query()
            ->whereHas('project')
            ->whereNotNull('raw_size_gb')
            ->with('project.client')
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(fn (CaptureSession $session) => [
                'id' => $session->id,
                'raw_state' => RawData::status($session),
                'held_gb' => RawData::heldGb($session),
                'size_gb' => (float) $session->raw_size_gb,
                'backup_location' => $session->backup_location,
                'scheduled_at' => $session->scheduled_at->format('d M Y'),
                'purged_at' => $session->raw_purged_at?->format('d M Y'),
                'project_slug' => $session->project->slug,
                'project_title' => $session->project->title,
                'client_name' => $session->project->client->name,
            ]);

        return Inertia::render('Storage/Index', [
            'totalGb' => round($sessions->sum('held_gb'), 2),
            // Tanpa backup di atas: itu risiko kehilangan data, bukan sekadar
            // boros tempat, dan tidak boleh tenggelam di bawah daftar panjang.
            'atRisk' => $sessions->where('raw_state', RawData::NO_BACKUP)->values(),
            'ready' => $sessions->where('raw_state', RawData::READY)->values(),
            'byClient' => $this->byClient($sessions),
            'sessions' => $sessions,
        ]);
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
     * @param  Collection<int, array<string, mixed>>  $sessions
     * @return Collection<int, array<string, mixed>>
     */
    private function byClient(Collection $sessions): Collection
    {
        return $sessions
            ->groupBy('client_name')
            ->map(fn (Collection $rows, string $client) => [
                'client_name' => $client,
                'held_gb' => round($rows->sum('held_gb'), 2),
                'sessions' => $rows->count(),
            ])
            ->sortByDesc('held_gb')
            ->values();
    }
}
