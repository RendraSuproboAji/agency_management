<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\ProjectScene;
use App\Models\User;
use App\Support\Archive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSceneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_scene_is_created_with_a_slug_and_the_next_position(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        ProjectScene::factory()->create(['project_id' => $project->id, 'position' => 4]);

        $this->actingAs($owner)
            ->post(route('scenes.store', $project), ['name' => 'Lobi Utama'])
            ->assertRedirect();

        $scene = ProjectScene::where('project_id', $project->id)->latest('id')->firstOrFail();

        $this->assertSame('lobi-utama', $scene->slug);
        $this->assertSame(5, $scene->position);
    }

    public function test_a_slug_only_has_to_be_unique_inside_its_own_project(): void
    {
        $owner = User::factory()->create();
        $first = Project::factory()->create(['owner_id' => $owner->id]);
        $second = Project::factory()->create(['owner_id' => $owner->id]);

        foreach ([$first, $second, $first] as $project) {
            $this->actingAs($owner)->post(route('scenes.store', $project), ['name' => 'Lobi']);
        }

        $slugs = fn (Project $project) => ProjectScene::where('project_id', $project->id)
            ->orderBy('id')->pluck('slug')->all();

        $this->assertSame(['lobi', 'lobi-2'], $slugs($first));
        $this->assertSame(['lobi'], $slugs($second));
    }

    public function test_archiving_a_project_archives_its_scenes_and_restoring_brings_them_back(): void
    {
        $project = Project::factory()->create();
        $together = ProjectScene::factory()->create(['project_id' => $project->id]);
        $earlier = ProjectScene::factory()->create(['project_id' => $project->id]);
        $this->travel(-1)->hours(fn () => $earlier->delete());

        Archive::archiveProject($project->fresh());

        $this->assertSame(0, $project->scenes()->count());

        Archive::restoreProject(Project::withTrashed()->findOrFail($project->id));

        $this->assertNotNull(ProjectScene::find($together->id));
        // Yang sudah diarsipkan lebih dulu tetap di arsip.
        $this->assertNull(ProjectScene::find($earlier->id));
    }

    public function test_a_staff_member_who_is_not_on_the_project_cannot_add_a_scene(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create(['role' => 'staff']);

        $this->actingAs($outsider)
            ->post(route('scenes.store', $project), ['name' => 'Lobi'])
            ->assertForbidden();
    }

    public function test_a_deliverable_can_be_linked_to_a_scene_of_its_own_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $scene = ProjectScene::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->post(route('deliverables.store', $project), [
            'title' => 'Splat lobi',
            'type' => 'splat',
            'version' => 1,
            'status' => 'draft',
            'scene_id' => $scene->id,
        ])->assertRedirect(route('projects.show', $project));

        $this->assertSame($scene->id, $project->deliverables()->firstOrFail()->scene_id);
    }

    public function test_a_scene_from_another_project_is_rejected(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $foreign = ProjectScene::factory()->create();

        $this->actingAs($owner)->post(route('deliverables.store', $project), [
            'title' => 'Splat lobi',
            'type' => 'splat',
            'version' => 1,
            'status' => 'draft',
            'scene_id' => $foreign->id,
        ])->assertSessionHasErrors('scene_id');

        $this->assertSame(0, Deliverable::count());
    }
}
