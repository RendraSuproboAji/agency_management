<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptureSessionController extends Controller
{
    /** Agenda pengambilan gambar lintas project. */
    public function index(Request $request): View
    {
        $sessions = CaptureSession::query()
            ->with(['project.client', 'crew'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('mine'), fn ($query) => $query->where('crew_id', $request->user()->id))
            ->orderBy('scheduled_at')
            ->paginate(20)
            ->withQueryString();

        return view('sessions.index', [
            'sessions' => $sessions,
            'filters' => $request->only(['status', 'mine']),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return view('sessions.create', [
            'project' => $project,
            'session' => new CaptureSession(['status' => 'scheduled', 'location' => $project->site_location]),
            'crew' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $project->captureSessions()->create($this->validated($request));

        return redirect()->route('projects.show', $project)->with('status', 'Sesi pengambilan gambar dijadwalkan.');
    }

    public function edit(Request $request, Project $project, CaptureSession $session): View
    {
        $this->authorizeSession($request, $project, $session);

        return view('sessions.edit', [
            'project' => $project,
            'session' => $session,
            'crew' => User::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Project $project, CaptureSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $project, $session);

        $session->update($this->validated($request));

        return redirect()->route('projects.show', $project)->with('status', 'Sesi diperbarui.');
    }

    /** Tandai sesi selesai; project ikut maju ke tahap processing bila masih di tahap capture. */
    public function complete(Request $request, Project $project, CaptureSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $project, $session);

        $data = $request->validate([
            'shot_count' => ['nullable', 'integer', 'min:0'],
            'weather_note' => ['nullable', 'string', 'max:150'],
        ]);

        $session->update($data + [
            'status' => 'done',
            'completed_at' => now(),
        ]);

        if (in_array($project->status, ['lead', 'survey', 'capture'], true)) {
            $project->update(['status' => 'processing']);
        }

        return back()->with('status', 'Sesi ditandai selesai.');
    }

    public function destroy(Request $request, Project $project, CaptureSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $project, $session);

        $session->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Sesi dihapus.');
    }

    private function authorizeSession(Request $request, Project $project, CaptureSession $session): void
    {
        abort_unless($session->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'crew_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'equipment' => ['nullable', 'string'],
            'shot_count' => ['nullable', 'integer', 'min:0'],
            'weather_note' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:'.implode(',', CaptureSession::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
