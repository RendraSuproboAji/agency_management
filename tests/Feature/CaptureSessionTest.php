<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_schedule_a_capture_session(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('sessions.store', $project), [
            'crew_id' => $owner->id,
            'scheduled_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'location' => 'Kemang, Jakarta Selatan',
            'equipment_note' => 'Sony A7IV, tripod',
            'status' => 'scheduled',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('capture_sessions', [
            'project_id' => $project->id,
            'crew_id' => $owner->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_staff_cannot_schedule_a_session_on_someone_elses_project(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('sessions.store', $project), [
                'scheduled_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
            ])->assertForbidden();
    }

    public function test_completing_a_session_advances_the_project_to_processing(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'status' => 'capture']);
        $session = CaptureSession::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)
            ->put(route('sessions.complete', [$project, $session]), ['shot_count' => 420])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame('done', $session->status);
        $this->assertSame(420, $session->shot_count);
        $this->assertNotNull($session->completed_at);
        $this->assertSame('processing', $project->fresh()->status);
    }

    public function test_completing_a_session_leaves_a_later_stage_untouched(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'status' => 'review']);
        $session = CaptureSession::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->put(route('sessions.complete', [$project, $session]));

        $this->assertSame('review', $project->fresh()->status);
    }

    public function test_a_session_from_another_project_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $session = CaptureSession::factory()->create();

        $this->actingAs($owner)
            ->put(route('sessions.complete', [$project, $session]))
            ->assertNotFound();
    }
}
