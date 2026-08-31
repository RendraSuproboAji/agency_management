<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerlessProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_may_manage_a_project_that_has_no_owner(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $project = Project::factory()->create(['owner_id' => null]);

        $this->actingAs($staff)
            ->get(route('projects.edit', $project))
            ->assertOk();

        $this->actingAs($staff)
            ->put(route('projects.status', $project), ['status' => 'processing'])
            ->assertRedirect();

        $this->assertSame('processing', $project->fresh()->status);
    }

    public function test_a_project_owned_by_another_staff_member_stays_closed(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $project = Project::factory()->create(['owner_id' => $other->id]);

        $this->actingAs($staff)
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }

    public function test_deleting_a_staff_account_leaves_the_project_open_to_the_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'staff']);
        $staff = User::factory()->create(['role' => 'staff']);
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($admin)->delete(route('users.destroy', $owner))->assertRedirect();

        $this->assertNull($project->fresh()->owner_id);

        $this->actingAs($staff)
            ->get(route('projects.edit', $project))
            ->assertOk();
    }
}
