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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;

class CaptureSessionController extends Controller
{
    /** Agenda pengambilan gambar lintas project, sebagai tabel atau kalender. */
    public function index(Request $request): Response
    {
        if ($request->query('view') === 'calendar') {
            return $this->calendar($request);
        }

        $sessions = CaptureSession::query()
            ->with(['project.client', 'crew', 'equipment'])
            // Jaring pengaman: satu relasi yang lupa diarsipkan tidak boleh
            // menjatuhkan seluruh agenda lewat ->project yang null.
            ->whereHas('project')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('mine'), fn ($query) => $query->where('crew_id', $request->user()->id))
            ->orderBy('scheduled_at')
            ->paginate(20)
            ->withQueryString();

        $sessions->through(fn (CaptureSession $session) => [
            ...$session->only(['id', 'status', 'location']),
            'scheduled_at' => $session->scheduled_at->format('d M Y H:i'),
            'project_title' => $session->project->title,
            'project_slug' => $session->project->slug,
            'client_name' => $session->project->client->name,
            'crew_name' => $session->crew?->name,
            'equipment' => $session->equipment->pluck('name')->join(', '),
        ]);

        return Inertia::render('Sessions/Index', [
            'mode' => 'table',
            'sessions' => $sessions,
            'filters' => $request->only(['status', 'mine']),
            'statuses' => CaptureSession::STATUSES,
        ]);
    }

    /**
     * Tampilan bulanan. Bulan yang tidak bisa diurai — misalnya "?month=besok"
     * dari tautan rusak — jatuh kembali ke bulan berjalan alih-alih melempar.
     */
    private function calendar(Request $request): Response
    {
        $month = $this->month($request->query('month'));

        $sessions = CaptureSession::query()
            ->with(['project.client', 'crew'])
            ->whereHas('project')
            ->whereBetween('scheduled_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('mine'), fn ($query) => $query->where('crew_id', $request->user()->id))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (CaptureSession $session) => [
                ...$session->only(['id', 'status', 'location']),
                'date' => $session->scheduled_at->toDateString(),
                'time' => $session->scheduled_at->format('H:i'),
                'project_title' => $session->project->title,
                'project_slug' => $session->project->slug,
                'client_name' => $session->project->client->name,
                'crew_name' => $session->crew?->name,
            ]);

        return Inertia::render('Sessions/Index', [
            'mode' => 'calendar',
            'calendar' => [
                'month' => $month->format('Y-m'),
                'label' => $month->translatedFormat('F Y'),
                'previous' => $month->copy()->subMonth()->format('Y-m'),
                'next' => $month->copy()->addMonth()->format('Y-m'),
                // Senin sebagai kolom pertama, sesuai kalender kerja di sini.
                'leading' => ($month->copy()->startOfMonth()->dayOfWeek + 6) % 7,
                'days' => $month->daysInMonth,
                'today' => Carbon::today()->toDateString(),
                'sessions' => $sessions,
            ],
            'filters' => $request->only(['status', 'mine']),
            'statuses' => CaptureSession::STATUSES,
        ]);
    }

    private function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }

    public function create(Request $request, Project $project): Response
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return Inertia::render('Sessions/Form', [
            'project' => $project->only(['slug', 'title']),
            'scenes' => $this->scenes($project),
            'session' => [
                'status' => 'scheduled',
                'location' => $project->site_location,
                'equipment' => [],
            ],
        ] + $this->formOptions());
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request, $project);
        $session = $project->captureSessions()->create($data);
        $session->equipment()->sync($data['equipment'] ?? []);

        return redirect()->route('projects.show', $project)->with('status', 'Sesi pengambilan gambar dijadwalkan.');
    }

    public function edit(Request $request, Project $project, CaptureSession $session): Response
    {
        $this->authorizeSession($request, $project, $session);

        $session->load('equipment');

        return Inertia::render('Sessions/Form', [
            'project' => $project->only(['slug', 'title']),
            'scenes' => $this->scenes($project),
            'session' => [
                ...$session->only([
                    'id', 'crew_id', 'scene_id', 'status', 'location', 'equipment_note',
                    'shot_count', 'raw_size_gb', 'frame_count', 'backup_location', 'weather_note', 'notes',
                ]),
                'scheduled_at' => $session->scheduled_at->format('Y-m-d\\TH:i'),
                'equipment' => $session->equipment->pluck('id'),
            ],
        ] + $this->formOptions());
    }

    public function update(Request $request, Project $project, CaptureSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $project, $session);

        $data = $this->validated($request, $project, $session);
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

        // Kolom deleted_at pada sesi dan job hanya dipakai untuk ikut
        // terarsip bersama project. Tombol di halaman ini menjanjikan
        // "hapus", dan halaman Arsip tidak menampilkan keduanya — jadi
        // hapus benar-benar permanen alih-alih meninggalkan baris tersembunyi.
        $session->forceDelete();

        return redirect()->route('projects.show', $project)->with('status', 'Sesi dihapus.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'crew' => User::orderBy('name')->get(['id', 'name']),
            'equipment' => Equipment::available()->orderBy('name')->get(['id', 'name', 'code', 'category']),
            'statuses' => CaptureSession::STATUSES,
        ];
    }

    private function authorizeSession(Request $request, Project $project, CaptureSession $session): void
    {
        abort_unless($session->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function scenes(Project $project): Collection
    {
        return $project->scenes()->get()->map(fn ($scene) => $scene->only(['id', 'name']));
    }

    /** Scene harus milik project ini dan belum diarsipkan. */
    private function sceneRule(Project $project): Exists
    {
        return Rule::exists('project_scenes', 'id')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Project $project, ?CaptureSession $session = null): array
    {
        $validator = Validator::make($request->all(), [
            'crew_id' => ['nullable', 'exists:users,id'],
            'scene_id' => ['nullable', $this->sceneRule($project)],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'equipment_note' => ['nullable', 'string'],
            'equipment' => ['nullable', 'array'],
            // Aturan exists tidak menyaring arsip, sementara Equipment::whereIn
            // menyaringnya — tanpa whereNull di sini, alat terarsip lolos
            // validasi lalu hilang diam-diam dari pemeriksaan bentrok.
            'equipment.*' => ['integer', Rule::exists('equipment', 'id')->whereNull('deleted_at')],
            'shot_count' => ['nullable', 'integer', 'min:0'],
            'raw_size_gb' => ['nullable', 'numeric', 'min:0'],
            'frame_count' => ['nullable', 'integer', 'min:0'],
            'backup_location' => ['nullable', 'string', 'max:255'],
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
