<?php

namespace App\Http\Controllers;

use App\Models\ProcessingJob;
use App\Models\Project;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessingJobController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $project->processingJobs()->create($this->validated($request, $project));

        return back()->with('status', 'Job processing ditambahkan.');
    }

    public function update(Request $request, Project $project, ProcessingJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $project, $job);

        $job->update($this->validated($request, $project));

        return back()->with('status', 'Job diperbarui.');
    }

    public function start(Request $request, Project $project, ProcessingJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $project, $job);

        // Menjalankan ulang job yang sudah selesai akan menghapus finished_at
        // dan membuat durasinya hilang.
        abort_unless(in_array($job->status, ['queued', 'failed'], true), 403);

        $job->update([
            'status' => 'running',
            'started_at' => now(),
            'finished_at' => null,
        ]);

        ActivityLogger::log($job, 'job.started', 'Menjalankan job '.$job->kind.'.');

        return back()->with('status', 'Job dijalankan.');
    }

    public function finish(Request $request, Project $project, ProcessingJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $project, $job);

        // Job yang belum berjalan tidak punya started_at; menandainya selesai
        // membuat durasinya dilaporkan 0 menit.
        abort_unless($job->status === 'running', 403);

        $data = $request->validate([
            'status' => ['required', 'in:done,failed'],
            'output_size_gb' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $job->update($data + [
            'started_at' => $job->started_at ?: now(),
            'finished_at' => now(),
        ]);

        ActivityLogger::log(
            $job->refresh(),
            'job.finished',
            'Job '.$job->kind.' selesai dengan status '.$job->status.' ('.$job->humanDuration().').',
        );

        return back()->with('status', 'Job ditandai '.$data['status'].'.');
    }

    public function destroy(Request $request, Project $project, ProcessingJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $project, $job);

        // Kolom deleted_at pada sesi dan job hanya dipakai untuk ikut
        // terarsip bersama project. Tombol di halaman ini menjanjikan
        // "hapus", dan halaman Arsip tidak menampilkan keduanya — jadi
        // hapus benar-benar permanen alih-alih meninggalkan baris tersembunyi.
        $job->forceDelete();

        return back()->with('status', 'Job dihapus.');
    }

    private function authorizeJob(Request $request, Project $project, ProcessingJob $job): void
    {
        abort_unless($job->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Project $project): array
    {
        return $request->validate([
            'kind' => ['required', 'in:'.implode(',', ProcessingJob::KINDS)],
            'status' => ['required', 'in:'.implode(',', ProcessingJob::STATUSES)],
            'machine' => ['nullable', 'string', 'max:150'],
            'capture_session_id' => [
                'nullable',
                Rule::exists('capture_sessions', 'id')->where('project_id', $project->id),
            ],
            'output_size_gb' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
