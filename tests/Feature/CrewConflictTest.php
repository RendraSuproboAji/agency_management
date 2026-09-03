<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Peralatan sudah dijaga dari tabrakan jadwal sejak awal, tetapi kru tidak:
 * satu orang bisa dijadwalkan di dua lokasi pada hari yang sama tanpa
 * peringatan apa pun, dan itu baru ketahuan pagi hari saat pemotretan.
 */
class CrewConflictTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(User $crew, string $scheduledAt, array $extra = []): array
    {
        return $extra + [
            'crew_id' => $crew->id,
            'scheduled_at' => $scheduledAt,
            'location' => 'Showroom Kemang',
            'status' => 'scheduled',
        ];
    }

    public function test_a_crew_member_cannot_be_booked_twice_in_one_day(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create(['name' => 'Rina Kapture']);

        $busy = Project::factory()->create(['title' => 'Tur Galeri Utama']);
        CaptureSession::factory()->create([
            'project_id' => $busy->id,
            'crew_id' => $crew->id,
            'scheduled_at' => '2026-10-12 09:00:00',
            'status' => 'scheduled',
        ]);

        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('sessions.store', $project), $this->payload($crew, '2026-10-12 15:00:00'))
            ->assertSessionHasErrors('crew_id');

        $this->assertSame(0, $project->captureSessions()->count());
    }

    public function test_a_cancelled_session_does_not_block_the_crew(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create();

        CaptureSession::factory()->create([
            'project_id' => Project::factory()->create()->id,
            'crew_id' => $crew->id,
            'scheduled_at' => '2026-10-12 09:00:00',
            'status' => 'cancelled',
        ]);

        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('sessions.store', $project), $this->payload($crew, '2026-10-12 15:00:00'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $project->captureSessions()->count());
    }

    public function test_another_day_is_fine(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create();

        CaptureSession::factory()->create([
            'project_id' => Project::factory()->create()->id,
            'crew_id' => $crew->id,
            'scheduled_at' => '2026-10-12 09:00:00',
            'status' => 'scheduled',
        ]);

        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('sessions.store', $project), $this->payload($crew, '2026-10-13 09:00:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_editing_a_session_does_not_clash_with_itself(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $session = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'crew_id' => $crew->id,
            'scheduled_at' => '2026-10-12 09:00:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->put(route('sessions.update', [$project, $session]), $this->payload($crew, '2026-10-12 14:00:00'))
            ->assertSessionHasNoErrors();

        $this->assertSame('14:00', $session->fresh()->scheduled_at->format('H:i'));
    }

    public function test_a_session_without_a_crew_is_still_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('sessions.store', $project), [
                'scheduled_at' => '2026-10-12 09:00:00',
                'status' => 'scheduled',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_cancelled_new_session_is_not_checked(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create();

        CaptureSession::factory()->create([
            'project_id' => Project::factory()->create()->id,
            'crew_id' => $crew->id,
            'scheduled_at' => '2026-10-12 09:00:00',
            'status' => 'scheduled',
        ]);

        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('sessions.store', $project), $this->payload($crew, '2026-10-12 15:00:00', ['status' => 'cancelled']))
            ->assertSessionHasNoErrors();
    }
}
