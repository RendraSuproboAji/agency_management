<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectScene;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectSceneController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request);
        $data['slug'] = $this->slugFor($project, $data['name']);
        $data['position'] = $data['position'] ?? ($project->scenes()->max('position') + 1);

        $project->scenes()->create($data);

        return back()->with('status', 'Scene ditambahkan.');
    }

    public function update(Request $request, Project $project, ProjectScene $scene): RedirectResponse
    {
        $this->authorizeScene($request, $project, $scene);

        $data = $this->validated($request);
        $data['slug'] = $this->slugFor($project, $data['name'], $scene->id);
        $data['position'] = $data['position'] ?? $scene->position;

        $scene->update($data);

        return back()->with('status', 'Scene diperbarui.');
    }

    public function destroy(Request $request, Project $project, ProjectScene $scene): RedirectResponse
    {
        $this->authorizeScene($request, $project, $scene);

        // Deliverable dan sesi tidak ikut terarsip: kolom scene_id memakai
        // nullOnDelete, dan pekerjaan yang sudah ada tetap milik project.
        $scene->delete();

        return back()->with('status', 'Scene diarsipkan.');
    }

    private function authorizeScene(Request $request, Project $project, ProjectScene $scene): void
    {
        abort_unless($scene->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    /** Slug cukup unik di dalam satu project — nama "Lobi" boleh dipakai banyak project. */
    private function slugFor(Project $project, string $name, ?int $ignoreId = null): string
    {
        return Slug::uniqueFor(
            ProjectScene::class,
            $name,
            $ignoreId,
            fn ($query) => $query->where('project_id', $project->id),
        );
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:0'],
            'gallery_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
