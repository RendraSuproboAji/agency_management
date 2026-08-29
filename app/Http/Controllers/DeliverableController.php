<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Models\Project;
use App\Support\ActivityLogger;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DeliverableController extends Controller
{
    public function create(Request $request, Project $project): Response
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return Inertia::render('Deliverables/Form', [
            'project' => $project->only(['slug', 'title']),
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

        $data = $this->validated($request);
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
            'deliverable' => [
                ...$deliverable->only(['id', 'title', 'type', 'version', 'status', 'external_url', 'review_note']),
                'file_name' => $deliverable->file_path ? basename($deliverable->file_path) : null,
            ],
        ] + $this->formOptions());
    }

    public function update(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        $data = $this->validated($request);

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

        $deliverable->update([
            'status' => 'approved',
            'approved_at' => now(),
            'review_note' => $request->input('review_note'),
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

        return $request->file('file')->store('deliverables/'.$project->slug, 'public');
    }

    private function deleteFile(Deliverable $deliverable): void
    {
        if ($deliverable->file_path) {
            Storage::disk('public')->delete($deliverable->file_path);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', Deliverable::TYPES)],
            'version' => ['required', 'integer', 'min:1'],
            'external_url' => ['nullable', 'url', 'max:255'],
            'file' => UploadRules::file(false),
            'status' => ['required', 'in:'.implode(',', Deliverable::STATUSES)],
            'review_note' => ['nullable', 'string'],
        ]);
    }
}
