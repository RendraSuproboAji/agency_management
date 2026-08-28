<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
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

        return view('projects.index', [
            'projects' => $projects,
            'clients' => Client::orderBy('name')->get(),
            'owners' => User::orderBy('name')->get(),
            'filters' => $request->only(['q', 'status', 'client', 'owner', 'mine']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('projects.create', [
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

    public function show(Project $project): View
    {
        $project->load([
            'client',
            'owner',
            'captureSessions' => fn ($query) => $query->with('crew')->orderBy('scheduled_at'),
            'deliverables' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return view('projects.show', [
            'project' => $project,
            'crew' => User::orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return view('projects.edit', ['project' => $project] + $this->formOptions());
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

        $project->update($data);

        return back()->with('status', 'Status project menjadi "'.$data['status'].'".');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project dihapus.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'clients' => Client::orderBy('name')->get(),
            'owners' => User::orderBy('name')->get(),
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
