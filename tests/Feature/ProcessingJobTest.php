<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_can_be_queued_against_a_capture_session(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $session = CaptureSession::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->post(route('jobs.store', $project), [
            'kind' => 'splat_training',
            'status' => 'queued',
            'machine' => 'workstation-01 (RTX 4090)',
            'capture_session_id' => $session->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('processing_jobs', [
            'project_id' => $project->id,
            'capture_session_id' => $session->id,
            'kind' => 'splat_training',
            'status' => 'queued',
        ]);
    }

    public function test_a_job_cannot_point_at_a_session_from_another_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $foreign = CaptureSession::factory()->create();

        $this->actingAs($owner)->post(route('jobs.store', $project), [
            'kind' => 'splat_training',
            'status' => 'queued',
            'capture_session_id' => $foreign->id,
        ])->assertSessionHasErrors('capture_session_id');
    }

    public function test_starting_a_job_stamps_the_start_time(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $job = ProcessingJob::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->put(route('jobs.start', [$project, $job]))->assertRedirect();

        $job->refresh();
        $this->assertSame('running', $job->status);
        $this->assertNotNull($job->started_at);
        $this->assertNull($job->durationMinutes());
        $this->assertDatabaseHas('activities', ['action' => 'job.started', 'project_id' => $project->id]);
    }

    public function test_finishing_a_job_records_the_duration_and_output(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $job = ProcessingJob::factory()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'started_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);

        Carbon::setTestNow('2026-09-10 11:30:00');

        $this->actingAs($owner)->put(route('jobs.finish', [$project, $job]), [
            'status' => 'done',
            'output_size_gb' => 4.75,
        ])->assertRedirect();

        Carbon::setTestNow();

        $job->refresh();
        $this->assertSame('done', $job->status);
        $this->assertSame(210, $job->durationMinutes());
        $this->assertSame('3 j 30 m', $job->humanDuration());
        $this->assertSame('4.75', $job->output_size_gb);
    }

    public function test_a_failed_job_keeps_the_reason(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $job = ProcessingJob::factory()->create(['project_id' => $project->id, 'status' => 'running']);

        $this->actingAs($owner)->put(route('jobs.finish', [$project, $job]), [
            'status' => 'failed',
            'notes' => 'Kehabisan VRAM di iterasi 18k.',
        ])->assertRedirect();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame('Kehabisan VRAM di iterasi 18k.', $job->notes);
        $this->assertNotNull($job->finished_at);
    }

    public function test_capture_metadata_is_stored_with_the_session(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('sessions.store', $project), [
            'scheduled_at' => '2026-09-10 09:00:00',
            'status' => 'scheduled',
            'raw_size_gb' => 128.5,
            'frame_count' => 2400,
            'backup_location' => 'NAS/2026/showroom-kemang',
        ])->assertRedirect();

        $this->assertDatabaseHas('capture_sessions', [
            'project_id' => $project->id,
            'frame_count' => 2400,
            'backup_location' => 'NAS/2026/showroom-kemang',
        ]);
    }

    public function test_staff_who_do_not_own_the_project_are_refused(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);
        $job = ProcessingJob::factory()->create(['project_id' => $project->id]);

        $this->actingAs(User::factory()->create())
            ->put(route('jobs.start', [$project, $job]))
            ->assertForbidden();
    }
}
