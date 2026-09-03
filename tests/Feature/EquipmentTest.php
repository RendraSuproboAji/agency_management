<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_can_be_added_and_listed(): void
    {
        $this->actingAs(User::factory()->create())->post(route('equipment.store'), [
            'name' => 'DJI Mavic 3',
            'code' => 'DRN-01',
            'category' => 'drone',
            'status' => 'available',
            'serial_number' => 'SN-1234',
        ])->assertRedirect(route('equipment.index'));

        $this->assertDatabaseHas('equipment', ['code' => 'DRN-01', 'category' => 'drone']);
    }

    public function test_the_code_must_be_unique(): void
    {
        Equipment::factory()->create(['code' => 'DRN-01']);

        $this->actingAs(User::factory()->create())->post(route('equipment.store'), [
            'name' => 'Drone kedua',
            'code' => 'DRN-01',
            'category' => 'drone',
            'status' => 'available',
        ])->assertSessionHasErrors('code');
    }

    public function test_only_an_admin_can_delete_equipment(): void
    {
        $item = Equipment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('equipment.destroy', $item))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('equipment.destroy', $item))
            ->assertRedirect(route('equipment.index'));
    }

    public function test_equipment_can_be_assigned_to_a_session(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $drone = Equipment::factory()->create();

        $this->actingAs($owner)->post(route('sessions.store', $project), [
            'scheduled_at' => '2026-09-10 09:00:00',
            'status' => 'scheduled',
            'equipment' => [$drone->id],
        ])->assertRedirect();

        $session = $project->captureSessions()->firstOrFail();
        $this->assertTrue($session->equipment->contains($drone));
    }

    public function test_the_same_equipment_cannot_be_booked_twice_on_one_day(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'title' => 'Tur Museum']);
        $drone = Equipment::factory()->create(['name' => 'DJI Mavic 3']);

        $taken = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'scheduled_at' => '2026-09-10 09:00:00',
        ]);
        $taken->equipment()->attach($drone);

        $this->actingAs($owner)
            ->from(route('sessions.create', $project))
            ->post(route('sessions.store', $project), [
                'scheduled_at' => '2026-09-10 14:00:00',
                'status' => 'scheduled',
                'equipment' => [$drone->id],
            ])->assertSessionHasErrors('equipment');

        $this->assertSame(1, $project->captureSessions()->count());
    }

    public function test_the_same_equipment_is_free_on_another_day(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $drone = Equipment::factory()->create();
        $taken = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'scheduled_at' => '2026-09-10 09:00:00',
        ]);
        $taken->equipment()->attach($drone);

        $this->actingAs($owner)->post(route('sessions.store', $project), [
            'scheduled_at' => '2026-09-11 09:00:00',
            'status' => 'scheduled',
            'equipment' => [$drone->id],
        ])->assertRedirect();

        $this->assertSame(2, $project->captureSessions()->count());
    }

    public function test_a_cancelled_session_does_not_hold_its_equipment(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $drone = Equipment::factory()->create();
        $cancelled = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'scheduled_at' => '2026-09-10 09:00:00',
            'status' => 'cancelled',
        ]);
        $cancelled->equipment()->attach($drone);

        $this->actingAs($owner)->post(route('sessions.store', $project), [
            'scheduled_at' => '2026-09-10 14:00:00',
            'status' => 'scheduled',
            'equipment' => [$drone->id],
        ])->assertRedirect();
    }

    public function test_archived_equipment_cannot_be_booked(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $drone = Equipment::factory()->create();
        $drone->delete();

        $this->actingAs($owner)
            ->from(route('sessions.create', $project))
            ->post(route('sessions.store', $project), [
                'scheduled_at' => '2026-09-10 09:00:00',
                'status' => 'scheduled',
                'equipment' => [$drone->id],
            ])->assertSessionHasErrors('equipment.0');

        $this->assertSame(0, $project->captureSessions()->count());
    }

    public function test_a_session_does_not_clash_with_itself_when_edited(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $drone = Equipment::factory()->create();
        $session = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'scheduled_at' => '2026-09-10 09:00:00',
        ]);
        $session->equipment()->attach($drone);

        $this->actingAs($owner)->put(route('sessions.update', [$project, $session]), [
            'scheduled_at' => '2026-09-10 13:00:00',
            'status' => 'scheduled',
            'location' => 'Kemang',
            'equipment' => [$drone->id],
        ])->assertRedirect();

        $this->assertSame('13:00', $session->fresh()->scheduled_at->format('H:i'));
    }
}
