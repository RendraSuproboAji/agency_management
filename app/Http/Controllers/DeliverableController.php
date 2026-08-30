<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Models\Project;
use App\Support\ActivityLogger;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverableController extends Controller
{
    public function create(Request $request, Project $project): Response
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return Inertia::render('Deliverables/Form', [
            'project' => $project->only(['slug', 'title']),
            'scenes' => $this->scenes($project),
            'deliverable' => [
                'type' => 'splat',
                'status' => 'draft',
                // Termasuk yang terarsip: kalau tidak, versi bisa berulang.
                'version' => $project->deliverables()->withTrashed()->max('version') + 1,
            ],
        ] + $this->formOptions());
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request, $project);
        $data['file_path'] = $this->storeFile($request, $project);
        $data['submitted_at'] = $data['status'] === 'submitted' ? now() : null;

        $project->deliverables()->create($data);

        return redirect()->route('projects.show', $project)->with('status', 'Deliverable ditambahkan.');
    }

    public function edit(Request $request, Project $project, Deliverable $deliverable): Response
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        return Inertia::render('Deliverables/Form', [
            'project' => $project->only(['slug', 'title']),
            'scenes' => $this->scenes($project),
            'deliverable' => [
                ...$deliverable->only(['id', 'scene_id', 'title', 'type', 'version', 'status', 'external_url', 'review_note']),
                'file_name' => $deliverable->file_path ? basename($deliverable->file_path) : null,
            ],
        ] + $this->formOptions());
    }

    public function update(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        $data = $this->validated($request, $project);

        if ($path = $this->storeFile($request, $project)) {
            $this->deleteFile($deliverable);
            $data['file_path'] = $path;
        }

        // Stempel waktu harus mengikuti statusnya; kalau tidak, deliverable bisa
        // berstatus "revision" tetapi masih menyimpan tanggal disetujui.
        if ($data['status'] === 'submitted' && ! $deliverable->submitted_at) {
            $data['submitted_at'] = now();
        }

        if ($data['status'] !== 'approved') {
            $data['approved_at'] = null;
        }

        if ($data['status'] === 'draft') {
            $data['submitted_at'] = null;
        }

        $deliverable->update($data);

        return redirect()->route('projects.show', $project)->with('status', 'Deliverable diperbarui.');
    }

    public function approve(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        // Menyamai jalur portal: hanya yang sudah diserahkan yang dinilai.
        abort_unless(in_array($deliverable->status, ['submitted', 'revision'], true), 403);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $deliverable->update($data + [
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        ActivityLogger::log($deliverable, 'deliverable.approved', 'Menyetujui deliverable "'.$deliverable->title.'" v'.$deliverable->version.'.');

        return back()->with('status', 'Deliverable disetujui.');
    }

    public function requestRevision(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        $data = $request->validate([
            'review_note' => ['required', 'string'],
        ]);

        $deliverable->update($data + [
            'status' => 'revision',
            'approved_at' => null,
        ]);

        ActivityLogger::log($deliverable, 'deliverable.revision', 'Meminta revisi deliverable "'.$deliverable->title.'" v'.$deliverable->version.'.');

        return back()->with('status', 'Revisi diminta.');
    }

    public function destroy(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        // Berkas sengaja dipertahankan: arsip harus bisa dipulihkan utuh.
        // Berkas baru dibuang saat hapus permanen dari halaman Arsip.
        $deliverable->delete();

        return redirect()->route('projects.show', $project)
            ->with('status', 'Deliverable diarsipkan. Bisa dipulihkan dari halaman Arsip.');
    }

    public function download(Request $request, Project $project, Deliverable $deliverable): StreamedResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        return $this->stream($deliverable);
    }

    /** Nama berkas yang diunduh mengikuti judul dan versinya, bukan nama acak di disk. */
    public static function stream(Deliverable $deliverable): StreamedResponse
    {
        abort_unless($deliverable->hasFile(), 404);
        abort_unless(Storage::disk('local')->exists($deliverable->file_path), 404);

        $name = Str::slug($deliverable->title).'-v'.$deliverable->version.
            '.'.pathinfo($deliverable->file_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($deliverable->file_path, $name);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'types' => Deliverable::TYPES,
            'statuses' => Deliverable::STATUSES,
        ];
    }

    private function authorizeDeliverable(Request $request, Project $project, Deliverable $deliverable): void
    {
        abort_unless($deliverable->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    private function storeFile(Request $request, Project $project): ?string
    {
        if (! $request->hasFile('file')) {
            return null;
        }

        return $request->file('file')->store('deliverables/'.$project->slug, 'local');
    }

    private function deleteFile(Deliverable $deliverable): void
    {
        if ($deliverable->file_path) {
            Storage::disk('local')->delete($deliverable->file_path);
        }
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
    private function validated(Request $request, Project $project): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'scene_id' => ['nullable', $this->sceneRule($project)],
            'type' => ['required', 'in:'.implode(',', Deliverable::TYPES)],
            'version' => ['required', 'integer', 'min:1'],
            'external_url' => ['nullable', 'url', 'max:255'],
            'file' => UploadRules::file(false),
            'status' => ['required', 'in:'.implode(',', Deliverable::STATUSES)],
            'review_note' => ['nullable', 'string'],
        ]);
    }
}
