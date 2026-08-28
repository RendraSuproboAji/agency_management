<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_can_be_created_for_a_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'client_id' => $client->id,
            'owner_id' => $user->id,
            'title' => 'Showroom Kemang 3D Tour',
            'brief' => 'Rekonstruksi showroom untuk marketing.',
            'service_type' => 'gaussian_splatting',
            'status' => 'lead',
            'deadline' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'client_id' => $client->id,
            'slug' => 'showroom-kemang-3d-tour',
            'status' => 'lead',
        ]);
    }

    public function test_an_invalid_service_type_is_rejected(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('projects.store'), [
                'client_id' => $client->id,
                'title' => 'Project',
                'service_type' => 'hologram',
                'status' => 'lead',
            ])->assertSessionHasErrors('service_type');
    }

    public function test_the_owner_can_move_the_project_along_the_pipeline(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('projects.status', $project), ['status' => 'capture'])
            ->assertRedirect();

        $this->assertSame('capture', $project->fresh()->status);
    }

    public function test_staff_cannot_edit_a_project_they_do_not_own(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($other)
            ->put(route('projects.status', $project), ['status' => 'capture'])
            ->assertForbidden();
    }

    public function test_an_admin_can_edit_any_project(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.edit', $project))
            ->assertOk();
    }

    public function test_only_an_admin_can_delete_a_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_the_project_list_can_be_filtered_by_status(): void
    {
        Project::factory()->create(['title' => 'Tur Museum', 'status' => 'capture']);
        Project::factory()->create(['title' => 'Tur Showroom', 'status' => 'archived']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.index', ['status' => 'capture']))
            ->assertOk()
            ->assertSee('Tur Museum')
            ->assertDontSee('Tur Showroom');
    }
}
