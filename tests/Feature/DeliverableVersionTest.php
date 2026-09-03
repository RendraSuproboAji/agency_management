<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverableVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_version_cannot_repeat_within_the_same_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        Deliverable::factory()->create(['project_id' => $project->id, 'version' => 2]);

        $this->actingAs($owner)
            ->post(route('deliverables.store', $project), [
                'title' => 'Scene utama',
                'type' => 'splat',
                'version' => 2,
                'status' => 'draft',
                'external_url' => 'https://gallery.example.com/p/dua',
            ])
            ->assertSessionHasErrors('version');

        $this->assertSame(1, $project->deliverables()->count());
    }

    public function test_the_same_version_is_fine_in_a_different_project(): void
    {
        $owner = User::factory()->create();
        $first = Project::factory()->create(['owner_id' => $owner->id]);
        $second = Project::factory()->create(['owner_id' => $owner->id]);
        Deliverable::factory()->create(['project_id' => $first->id, 'version' => 2]);

        $this->actingAs($owner)
            ->post(route('deliverables.store', $second), [
                'title' => 'Scene utama',
                'type' => 'splat',
                'version' => 2,
                'status' => 'draft',
                'external_url' => 'https://gallery.example.com/p/dua',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $second->deliverables()->count());
    }

    public function test_an_archived_deliverable_still_holds_its_version(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $archived = Deliverable::factory()->create(['project_id' => $project->id, 'version' => 3]);
        $archived->delete();

        $this->actingAs($owner)
            ->post(route('deliverables.store', $project), [
                'title' => 'Scene utama',
                'type' => 'splat',
                'version' => 3,
                'status' => 'draft',
                'external_url' => 'https://gallery.example.com/p/tiga',
            ])
            ->assertSessionHasErrors('version');
    }

    public function test_editing_a_deliverable_may_keep_its_own_version(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'version' => 4,
            'external_url' => 'https://gallery.example.com/p/empat',
        ]);

        $this->actingAs($owner)
            ->put(route('deliverables.update', [$project, $deliverable]), [
                'title' => 'Judul baru',
                'type' => $deliverable->type,
                'version' => 4,
                'status' => 'draft',
                'external_url' => $deliverable->external_url,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Judul baru', $deliverable->fresh()->title);
    }
}
