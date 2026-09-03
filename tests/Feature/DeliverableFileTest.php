<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deliverable adalah produk yang dibayar klien. Berkasnya tidak boleh berada
 * di disk publik, tempat siapa pun yang punya URL bisa mengunduhnya selamanya
 * — termasuk setelah project diarsipkan.
 */
class DeliverableFileTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $owner, Project $project): Deliverable
    {
        $this->actingAs($owner)->post(route('deliverables.store', $project), [
            'title' => 'Splat lobi',
            'type' => 'splat',
            'version' => 1,
            'status' => 'submitted',
            'file' => UploadedFile::fake()->create('scene.ply', 32),
        ])->assertRedirect();

        return $project->deliverables()->firstOrFail();
    }

    public function test_the_file_never_lands_on_the_public_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $deliverable = $this->upload($owner, Project::factory()->create(['owner_id' => $owner->id]));

        Storage::disk('public')->assertMissing($deliverable->file_path);
        Storage::disk('local')->assertExists($deliverable->file_path);
    }

    public function test_a_guest_cannot_download_a_deliverable(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = $this->upload($owner, $project);

        $this->post(route('logout'));
        $this->get(route('deliverables.download', [$project, $deliverable]))
            ->assertRedirect(route('login'));
    }

    public function test_the_owner_downloads_the_exact_bytes(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = $this->upload($owner, $project);

        $response = $this->actingAs($owner)
            ->get(route('deliverables.download', [$project, $deliverable]))
            ->assertOk();

        $this->assertSame(
            Storage::disk('local')->get($deliverable->file_path),
            $response->streamedContent(),
        );
    }

    public function test_a_client_downloads_only_their_own_submitted_deliverable(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'client_id' => $client->id]);
        $deliverable = $this->upload($owner, $project);

        $this->actingAs($client, 'client')
            ->get(route('portal.deliverables.download', [$project, $deliverable]))
            ->assertOk();

        $stranger = Client::factory()->withPortal()->create();
        $this->actingAs($stranger, 'client')
            ->get(route('portal.deliverables.download', [$project, $deliverable]))
            ->assertNotFound();
    }

    public function test_a_client_cannot_download_a_draft_that_was_never_submitted(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'client_id' => $client->id]);
        $deliverable = $this->upload($owner, $project);
        $deliverable->update(['status' => 'draft']);

        $this->actingAs($client, 'client')
            ->get(route('portal.deliverables.download', [$project, $deliverable]))
            ->assertForbidden();
    }
}
