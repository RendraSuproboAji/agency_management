<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PortalProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_only_sees_their_own_projects(): void
    {
        $client = Client::factory()->withPortal()->create();
        $mine = Project::factory()->create(['client_id' => $client->id, 'title' => 'Tur Showroom Saya']);
        $theirs = Project::factory()->create(['title' => 'Tur Milik Klien Lain']);

        $this->actingAs($client, 'client')->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Portal/Dashboard')
                ->has('projects', 1)
                ->where('projects.0.title', 'Tur Showroom Saya'));

        $this->actingAs($client, 'client')->get(route('portal.projects.show', $mine))->assertOk();
        $this->actingAs($client, 'client')->get(route('portal.projects.show', $theirs))->assertNotFound();
    }

    public function test_the_portal_hides_internal_notes_and_draft_documents(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        Note::factory()->create(['project_id' => $project->id, 'body' => 'Margin proyek ini tipis.']);
        Invoice::factory()->create([
            'project_id' => $project->id,
            'number' => 'INV/2026/0009',
            'status' => 'draft',
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.projects.show', $project))
            ->assertOk()
            // Bukan sekadar tidak tampil: datanya memang tidak pernah dikirim.
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Portal/Project')
                ->missing('project.notes')
                ->missing('project.activities')
                ->missing('project.attachments')
                ->has('project.documents', 0))
            ->assertDontSee('Margin proyek ini tipis.')
            ->assertDontSee('INV\\/2026\\/0009');
    }

    public function test_a_client_can_approve_a_submitted_deliverable(): void
    {
        $client = Client::factory()->withPortal()->create(['name' => 'Museum Kota Lama']);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.approve', [$project, $deliverable]))
            ->assertRedirect();

        $deliverable->refresh();
        $this->assertSame('approved', $deliverable->status);
        $this->assertNotNull($deliverable->approved_at);
        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'action' => 'deliverable.approved',
            'user_id' => null,
            'actor' => 'Klien — Museum Kota Lama',
        ]);
    }

    public function test_a_client_can_ask_for_a_revision_with_a_note(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($client, 'client')
            ->from(route('portal.projects.show', $project))
            ->put(route('portal.deliverables.revision', [$project, $deliverable]))
            ->assertSessionHasErrors('review_note');

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.revision', [$project, $deliverable]), [
                'review_note' => 'Warna dinding terlalu gelap.',
            ])->assertRedirect();

        $deliverable->refresh();
        $this->assertSame('revision', $deliverable->status);
        $this->assertSame('Warna dinding terlalu gelap.', $deliverable->review_note);
    }

    public function test_a_client_cannot_touch_a_draft_deliverable(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'draft']);

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.approve', [$project, $deliverable]))
            ->assertForbidden();
    }

    public function test_a_client_cannot_approve_a_deliverable_on_another_clients_project(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create();
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.approve', [$project, $deliverable]))
            ->assertNotFound();
    }

    public function test_an_admin_can_switch_portal_access_on(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('clients.update', $client), [
                'name' => $client->name,
                'status' => 'active',
                'portal_enabled' => '1',
                'password' => 'kata-sandi-p0rtal',
            ])->assertRedirect();

        $client->refresh();
        $this->assertTrue($client->canUsePortal());
    }
}
