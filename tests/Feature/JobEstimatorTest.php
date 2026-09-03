<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\User;
use App\Support\JobEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Durasi tiap job dan besar data mentahnya sudah dicatat setiap kali, tetapi
 * tidak pernah dibaca balik — sehingga "kapan jadi?" dijawab dari ingatan.
 */
class JobEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private function finishedJob(Project $project, float $rawGb, int $minutes, string $kind = 'splat_training'): ProcessingJob
    {
        $session = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'raw_size_gb' => $rawGb,
        ]);

        return ProcessingJob::factory()->create([
            'project_id' => $project->id,
            'capture_session_id' => $session->id,
            'kind' => $kind,
            'status' => 'done',
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(5)->addMinutes($minutes),
        ]);
    }

    public function test_a_single_outlier_does_not_move_the_estimate(): void
    {
        $project = Project::factory()->create();

        // Tiga job wajar: 2 menit per GB.
        $this->finishedJob($project, 100, 200);
        $this->finishedJob($project, 50, 100);
        $this->finishedJob($project, 200, 400);
        // Satu job yang tertinggal semalaman karena mesinnya hang.
        $this->finishedJob($project, 100, 6_000);

        $estimate = JobEstimator::minutesPerGb('splat_training');

        $this->assertNotNull($estimate);
        $this->assertSame(2.0, $estimate->minutesPerGb);
        $this->assertSame(4, $estimate->samples);
    }

    public function test_fewer_than_three_samples_is_not_an_estimate(): void
    {
        $project = Project::factory()->create();
        $this->finishedJob($project, 100, 200);
        $this->finishedJob($project, 100, 200);

        $this->assertNull(JobEstimator::minutesPerGb('splat_training'));
    }

    public function test_jobs_without_a_raw_size_are_not_counted(): void
    {
        $project = Project::factory()->create();
        $this->finishedJob($project, 100, 200);
        $this->finishedJob($project, 100, 200);
        $this->finishedJob($project, 100, 200);

        $orphan = CaptureSession::factory()->create(['project_id' => $project->id, 'raw_size_gb' => null]);
        ProcessingJob::factory()->create([
            'project_id' => $project->id,
            'capture_session_id' => $orphan->id,
            'kind' => 'splat_training',
            'status' => 'done',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay()->addMinutes(9_000),
        ]);

        $this->assertSame(3, JobEstimator::minutesPerGb('splat_training')->samples);
    }

    public function test_each_kind_is_measured_on_its_own(): void
    {
        $project = Project::factory()->create();
        foreach ([1, 2, 3] as $ignored) {
            $this->finishedJob($project, 100, 200, 'splat_training');
            $this->finishedJob($project, 100, 20, 'cleanup');
        }

        $this->assertSame(2.0, JobEstimator::minutesPerGb('splat_training')->minutesPerGb);
        $this->assertSame(0.2, JobEstimator::minutesPerGb('cleanup')->minutesPerGb);
    }

    public function test_a_queued_job_is_estimated_from_its_own_raw_size(): void
    {
        $project = Project::factory()->create();
        foreach ([1, 2, 3] as $ignored) {
            $this->finishedJob($project, 100, 200);
        }

        $session = CaptureSession::factory()->create(['project_id' => $project->id, 'raw_size_gb' => 250]);
        $queued = ProcessingJob::factory()->create([
            'project_id' => $project->id,
            'capture_session_id' => $session->id,
            'kind' => 'splat_training',
            'status' => 'queued',
            'started_at' => null,
            'finished_at' => null,
        ]);

        $this->assertSame(500, JobEstimator::forJob($queued)->minutes);
    }

    public function test_a_project_estimate_sums_only_the_work_left(): void
    {
        $project = Project::factory()->create();
        foreach ([1, 2, 3] as $ignored) {
            $this->finishedJob($project, 100, 200);
        }

        foreach (['queued', 'running'] as $status) {
            $session = CaptureSession::factory()->create(['project_id' => $project->id, 'raw_size_gb' => 100]);
            ProcessingJob::factory()->create([
                'project_id' => $project->id,
                'capture_session_id' => $session->id,
                'kind' => 'splat_training',
                'status' => $status,
                'started_at' => $status === 'running' ? now() : null,
                'finished_at' => null,
            ]);
        }

        // Dua job tersisa, masing-masing 100 GB × 2 menit; yang sudah selesai
        // tidak ikut dihitung lagi.
        $this->assertSame(400, JobEstimator::forProject($project)->minutes);
    }

    public function test_the_project_page_does_not_query_per_job(): void
    {
        $queriesFor = function (int $queued): int {
            ProcessingJob::query()->forceDelete();
            CaptureSession::query()->forceDelete();
            Project::query()->forceDelete();

            $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);

            foreach ([1, 2, 3] as $ignored) {
                $this->finishedJob($project, 100, 200);
            }

            for ($i = 0; $i < $queued; $i++) {
                $session = CaptureSession::factory()->create([
                    'project_id' => $project->id,
                    'raw_size_gb' => 100,
                ]);
                ProcessingJob::factory()->create([
                    'project_id' => $project->id,
                    'capture_session_id' => $session->id,
                    'kind' => 'splat_training',
                    'status' => 'queued',
                    'started_at' => null,
                    'finished_at' => null,
                ]);
            }

            // Container hidup melewati beberapa permintaan di dalam satu tes,
            // padahal di produksi tiap permintaan dapat yang baru. Tanpa ini
            // pengukuran keduanya tidak setara.
            JobEstimator::forget();

            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $this->actingAs($project->owner)->get(route('projects.show', $project))->assertOk();

            return $queries;
        };

        $few = $queriesFor(2);
        $many = $queriesFor(12);

        $this->assertSame(
            $few,
            $many,
            "Jumlah kueri tumbuh mengikuti jumlah job ({$few} → {$many}): perkiraan dihitung ulang per baris.",
        );
    }

    public function test_a_project_without_history_has_no_estimate(): void
    {
        $project = Project::factory()->create();
        $session = CaptureSession::factory()->create(['project_id' => $project->id, 'raw_size_gb' => 100]);
        ProcessingJob::factory()->create([
            'project_id' => $project->id,
            'capture_session_id' => $session->id,
            'kind' => 'splat_training',
            'status' => 'queued',
            'started_at' => null,
            'finished_at' => null,
        ]);

        $this->assertNull(JobEstimator::forProject($project));
    }
}
