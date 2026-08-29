<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DeliverableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deliverable_file_is_stored_under_the_project_slug(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'slug' => 'showroom-kemang']);

        $this->actingAs($owner)->post(route('deliverables.store', $project), [
            'title' => 'Scene utama',
            'type' => 'splat',
            'version' => 1,
            'status' => 'submitted',
            'file' => UploadedFile::fake()->create('scene.ply', 64),
        ])->assertRedirect(route('projects.show', $project));

        $deliverable = $project->deliverables()->firstOrFail();

        $this->assertStringStartsWith('deliverables/showroom-kemang/', $deliverable->file_path);
        Storage::disk('public')->assertExists($deliverable->file_path);
        $this->assertNotNull($deliverable->submitted_at);
    }

    public function test_a_deliverable_can_point_at_an_external_viewer_url(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('deliverables.store', $project), [
            'title' => 'Virtual tour',
            'type' => 'splat',
            'version' => 2,
            'status' => 'draft',
            'external_url' => 'https://gallery.example.com/p/showroom-kemang',
        ])->assertRedirect();

        $deliverable = $project->deliverables()->firstOrFail();

        $this->assertSame('https://gallery.example.com/p/showroom-kemang', $deliverable->url());
        $this->assertNull($deliverable->submitted_at);
    }

    public function test_a_deliverable_can_be_approved(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($owner)
            ->put(route('deliverables.approve', [$project, $deliverable]), ['review_note' => 'Bagus.'])
            ->assertRedirect();

        $deliverable->refresh();
        $this->assertSame('approved', $deliverable->status);
        $this->assertNotNull($deliverable->approved_at);
    }

    public function test_requesting_a_revision_requires_a_note(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->put(route('deliverables.revision', [$project, $deliverable]))
            ->assertSessionHasErrors('review_note');

        $this->actingAs($owner)
            ->put(route('deliverables.revision', [$project, $deliverable]), ['review_note' => 'Warna terlalu gelap.'])
            ->assertRedirect();

        $deliverable->refresh();
        $this->assertSame('revision', $deliverable->status);
        $this->assertNull($deliverable->approved_at);
        $this->assertSame('Warna terlalu gelap.', $deliverable->review_note);
    }

    public function test_deleting_a_deliverable_archives_it_and_keeps_the_file(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        Storage::disk('public')->put('deliverables/scene.ply', 'x');
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'file_path' => 'deliverables/scene.ply',
        ]);

        $this->actingAs($owner)
            ->delete(route('deliverables.destroy', [$project, $deliverable]))
            ->assertRedirect();

        // Berkas tetap ada supaya arsip bisa dipulihkan utuh; berkas baru
        // dibuang saat hapus permanen (lihat ArchiveTest).
        Storage::disk('public')->assertExists('deliverables/scene.ply');
        $this->assertSoftDeleted('deliverables', ['id' => $deliverable->id]);
    }

    public function test_the_form_pages_render(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'title' => 'Scene utama']);

        $this->actingAs($owner)->get(route('deliverables.create', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Deliverables/Form'));

        $this->actingAs($owner)->get(route('deliverables.edit', [$project, $deliverable]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Deliverables/Form')
                ->where('deliverable.title', 'Scene utama'));
    }

    public function test_the_next_version_skips_numbers_used_by_archived_deliverables(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        Deliverable::factory()->create(['project_id' => $project->id, 'version' => 3])->delete();

        $this->actingAs($owner)->get(route('deliverables.create', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('deliverable.version', 4));
    }

    public function test_moving_out_of_approved_clears_the_approval_date(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'approved',
            'approved_at' => now(),
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)->put(route('deliverables.update', [$project, $deliverable]), [
            'title' => $deliverable->title,
            'type' => $deliverable->type,
            'version' => $deliverable->version,
            'status' => 'revision',
            'review_note' => 'Warna terlalu gelap.',
        ])->assertRedirect();

        $deliverable->refresh();
        $this->assertSame('revision', $deliverable->status);
        $this->assertNull($deliverable->approved_at, 'tanggal disetujui harus ikut hilang');
        $this->assertNotNull($deliverable->submitted_at);
    }

    public function test_staff_cannot_touch_deliverables_on_someone_elses_project(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->actingAs(User::factory()->create())
            ->put(route('deliverables.approve', [$project, $deliverable]))
            ->assertForbidden();
    }
}
