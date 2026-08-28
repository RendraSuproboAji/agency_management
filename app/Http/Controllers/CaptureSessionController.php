<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CaptureSessionController extends Controller
{
    /** Agenda pengambilan gambar lintas project. */
    public function index(Request $request): View
    {
        $sessions = CaptureSession::query()
            ->with(['project.client', 'crew', 'equipment'])
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
            'equipment' => Equipment::available()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request);
        $session = $project->captureSessions()->create($data);
        $session->equipment()->sync($data['equipment'] ?? []);

        return redirect()->route('projects.show', $project)->with('status', 'Sesi pengambilan gambar dijadwalkan.');
    }

    public function edit(Request $request, Project $project, CaptureSession $session): View
    {
        $this->authorizeSession($request, $project, $session);

        return view('sessions.edit', [
            'project' => $project,
            'session' => $session->load('equipment'),
            'crew' => User::orderBy('name')->get(),
            'equipment' => Equipment::available()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Project $project, CaptureSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $project, $session);

        $data = $this->validated($request, $session);
        $session->update($data);
        $session->equipment()->sync($data['equipment'] ?? []);

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

        ActivityLogger::log($session, 'session.completed', 'Menyelesaikan sesi pengambilan gambar '.$session->scheduled_at->format('d M Y').'.');

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
    private function validated(Request $request, ?CaptureSession $session = null): array
    {
        $validator = Validator::make($request->all(), [
            'crew_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'equipment_note' => ['nullable', 'string'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['integer', 'exists:equipment,id'],
            'shot_count' => ['nullable', 'integer', 'min:0'],
            'weather_note' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:'.implode(',', CaptureSession::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $validator->after(fn ($validator) => $this->checkEquipmentClashes($validator, $request, $session));

        $data = $validator->validate();

        // Kolom teks lama dipakai sebagai catatan bebas peralatan.
        $data['equipment_note'] = $data['equipment_note'] ?? null;

        return $data;
    }

    /**
     * Alat yang sama tidak boleh terpakai di dua sesi aktif pada tanggal yang
     * sama — kesalahan yang baru ketahuan saat kru sudah di lokasi.
     */
    private function checkEquipmentClashes(
        \Illuminate\Validation\Validator $validator,
        Request $request,
        ?CaptureSession $session,
    ): void {
        $ids = $request->input('equipment', []);
        $scheduledAt = $request->input('scheduled_at');

        if (! $ids || ! $scheduledAt || $request->input('status') === 'cancelled') {
            return;
        }

        $date = Carbon::parse($scheduledAt)->toDateString();

        foreach (Equipment::whereIn('id', $ids)->get() as $item) {
            $clash = $item->conflictingSessionOn($date, $session?->id);

            if ($clash) {
                $validator->errors()->add(
                    'equipment',
                    $item->name.' sudah dipakai sesi "'.$clash->project->title.'" pada '.
                    $clash->scheduled_at->format('d M Y H:i').'.',
                );
            }
        }
    }
}
