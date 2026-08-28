<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_note_appears_on_the_project_page(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('notes.store', $project), ['body' => 'Klien minta tambahan scene rooftop.'])
            ->assertRedirect();

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Klien minta tambahan scene rooftop.');
    }

    public function test_a_note_can_only_be_deleted_by_its_author_or_an_admin(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $note = Note::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('notes.destroy', [$project, $note]))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('notes.destroy', [$project, $note]))
            ->assertRedirect();

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_staff_who_do_not_own_the_project_cannot_write_a_note(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('notes.store', $project), ['body' => 'Halo'])
            ->assertForbidden();
    }
}
