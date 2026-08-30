<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Archive;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = Project::query()
            ->with(['client', 'owner'])
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->when($request->query('client'), fn ($query, $slug) => $query->whereHas('client', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('owner'), fn ($query, $id) => $query->where('owner_id', $id))
            ->when($request->boolean('mine'), fn ($query) => $query->where('owner_id', $request->user()->id))
            ->orderByRaw('deadline is null, deadline asc')
            ->paginate(20)
            ->withQueryString();

        $projects->through(fn (Project $project) => [
            ...$project->only(['id', 'slug', 'title', 'status']),
            'service_type' => str_replace('_', ' ', $project->service_type),
            'client_name' => $project->client->name,
            'client_slug' => $project->client->slug,
            'owner_name' => $project->owner?->name,
            'deadline' => $project->deadline?->format('d M Y'),
        ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'clients' => Client::orderBy('name')->get(['id', 'slug', 'name']),
            'filters' => $request->only(['q', 'status', 'client', 'owner', 'mine']),
            'statuses' => Project::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Projects/Form', [
            'project' => new Project([
                'status' => 'lead',
                'service_type' => 'gaussian_splatting',
                'client_id' => $request->query('client_id'),
                'owner_id' => $request->user()->id,
            ]),
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Slug::uniqueFor(Project::class, $data['title']);

        $project = Project::create($data);

        return redirect()->route('projects.show', $project)->with('status', 'Project berhasil dibuat.');
    }

    public function show(Request $request, Project $project): Response
    {
        $project->load([
            'client',
            'owner',
            'captureSessions' => fn ($query) => $query->with('crew', 'equipment')->orderBy('scheduled_at'),
            'scenes',
            'deliverables' => fn ($query) => $query->orderByDesc('created_at'),
            'quotations' => fn ($query) => $query->with('items')->orderByDesc('issued_at'),
            'invoices' => fn ($query) => $query->with('payments')->orderByDesc('issued_at'),
            'processingJobs' => fn ($query) => $query->with('captureSession')->latest(),
            'attachments' => fn ($query) => $query->with('uploader')->latest(),
            'notes' => fn ($query) => $query->with('author')->latest(),
            'activities' => fn ($query) => $query->with('user')->latest()->limit(30),
        ]);

        $billed = $project->invoices->sum(fn ($invoice) => (float) $invoice->amount);

        $user = $request->user();

        return Inertia::render('Projects/Show', [
            'project' => [
                ...$project->only(['id', 'slug', 'title', 'status', 'brief', 'site_location', 'area_sqm', 'budget', 'gallery_url']),
                'service_type' => str_replace('_', ' ', $project->service_type),
                'client_name' => $project->client->name,
                'client_slug' => $project->client->slug,
                'owner_name' => $project->owner?->name,
                'deadline' => $project->deadline?->format('d M Y'),
                'scenes' => $project->scenes->map(fn ($scene) => [
                    ...$scene->only(['id', 'name', 'slug', 'position', 'gallery_url', 'notes']),
                ]),
                'capture_sessions' => $project->captureSessions->map(fn ($session) => [
                    ...$session->only(['id', 'status', 'location', 'shot_count', 'scene_id']),
                    'scheduled_at' => $session->scheduled_at->format('d M Y H:i'),
                    'crew_name' => $session->crew?->name,
                    'equipment' => $session->equipment->pluck('name')->join(', '),
                ]),
                'deliverables' => $project->deliverables->map(fn ($deliverable) => [
                    ...$deliverable->only(['id', 'title', 'type', 'version', 'status', 'review_note']),
                    'scene' => $project->scenes->firstWhere('id', $deliverable->scene_id)?->name,
                    'url' => $deliverable->url(),
                ]),
                'quotations' => $project->quotations->map(fn ($quotation) => [
                    'kind' => 'quotation',
                    'id' => $quotation->id,
                    'number' => $quotation->number,
                    'status' => $quotation->status,
                    'issued_at' => $quotation->issued_at->format('d M Y'),
                    'amount' => $quotation->total(),
                ]),
                'invoices' => $project->invoices->map(fn ($invoice) => [
                    'kind' => 'invoice',
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'issued_at' => $invoice->issued_at->format('d M Y'),
                    'amount' => (float) $invoice->amount,
                    'outstanding' => $invoice->outstanding(),
                ]),
                'processing_jobs' => $project->processingJobs->map(fn ($job) => [
                    ...$job->only(['id', 'status', 'machine', 'notes', 'output_size_gb']),
                    'kind' => str_replace('_', ' ', $job->kind),
                    'duration' => $job->humanDuration(),
                    'session' => $job->captureSession?->scheduled_at->format('d M Y'),
                ]),
                'attachments' => $project->attachments->map(fn ($attachment) => [
                    ...$attachment->only(['id', 'title', 'category']),
                    'size' => $attachment->humanSize(),
                    'uploader' => $attachment->uploader?->name,
                    'created_at' => $attachment->created_at->format('d M Y'),
                ]),
                'notes' => $project->notes->map(fn ($note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name ?: 'Pengguna terhapus',
                    'created_at' => $note->created_at->diffForHumans(),
                    'can_delete' => $note->user_id === $user->id || $user->isAdmin(),
                ]),
                'activities' => $project->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'actor' => $activity->actorName(),
                    'created_at' => $activity->created_at->format('d M Y H:i'),
                ]),
            ],
            'canManage' => $project->isManageableBy($user),
            'statuses' => Project::STATUSES,
            'jobKinds' => ProcessingJob::KINDS,
            'jobStatuses' => ProcessingJob::STATUSES,
            'attachmentCategories' => Attachment::CATEGORIES,
            'billed' => $billed,
            'paid' => $project->invoices->sum(fn ($invoice) => $invoice->paidAmount()),
            'rawSizeGb' => number_format($project->captureSessions->sum(fn ($session) => (float) $session->raw_size_gb), 2, ',', '.'),
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return Inertia::render('Projects/Form', [
            'project' => [
                ...$project->only(['id', 'slug', 'title', 'client_id', 'owner_id', 'service_type', 'status', 'budget', 'site_location', 'area_sqm', 'gallery_url', 'brief']),
                'deadline' => $project->deadline?->format('Y-m-d'),
            ],
        ] + $this->formOptions());
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request);
        $data['slug'] = Slug::uniqueFor(Project::class, $data['title'], $project->id);

        $project->update($data);

        return redirect()->route('projects.show', $project)->with('status', 'Project diperbarui.');
    }

    /** Pindah status pipeline tanpa membuka form penuh. */
    public function updateStatus(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Project::STATUSES)],
        ]);

        $previous = $project->status;
        $project->update($data);

        ActivityLogger::log($project, 'project.status', 'Mengubah status project dari "'.$previous.'" ke "'.$data['status'].'".');

        return back()->with('status', 'Status project menjadi "'.$data['status'].'".');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        Archive::archiveProject($project);

        ActivityLogger::log($project, 'project.archived', 'Mengarsipkan project "'.$project->title.'".');

        return redirect()->route('projects.index')
            ->with('status', 'Project diarsipkan. Bisa dipulihkan dari halaman Arsip.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'owners' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'serviceTypes' => Project::SERVICE_TYPES,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:150'],
            'brief' => ['nullable', 'string'],
            'service_type' => ['required', 'in:'.implode(',', Project::SERVICE_TYPES)],
            'status' => ['required', 'in:'.implode(',', Project::STATUSES)],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
            'site_location' => ['nullable', 'string', 'max:255'],
            'area_sqm' => ['nullable', 'integer', 'min:0'],
            'gallery_url' => ['nullable', 'url', 'max:255'],
        ]);
    }
}
