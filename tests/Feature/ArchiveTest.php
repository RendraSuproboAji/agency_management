<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Archive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_a_client_takes_its_whole_tree_with_it(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);
        $payment = $invoice->payments()->create([
            'paid_at' => now()->toDateString(),
            'amount' => 1_000_000,
            'method' => 'transfer',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_archived_records_disappear_from_lists_and_the_dashboard(): void
    {
        $client = Client::factory()->create(['name' => 'Museum Kota Lama']);
        Project::factory()->create(['client_id' => $client->id, 'title' => 'Tur Museum']);

        Archive::archiveClient($client);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('clients.index'))->assertOk()->assertDontSee('Museum Kota Lama');
        $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertDontSee('Tur Museum');
        $this->assertSame(0, Client::count());
        $this->assertSame(0, Project::count());
    }

    public function test_restoring_a_client_brings_back_what_was_archived_with_it(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);

        Archive::archiveClient($client);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('archive.restore', ['clients', $client->id]))
            ->assertRedirect();

        $this->assertNull($client->fresh()->deleted_at);
        $this->assertNull($project->fresh()->deleted_at);
        $this->assertNull($invoice->fresh()->deleted_at);
    }

    public function test_a_project_archived_earlier_stays_archived_when_its_client_is_restored(): void
    {
        $client = Client::factory()->create();
        $earlier = Project::factory()->create(['client_id' => $client->id]);
        $later = Project::factory()->create(['client_id' => $client->id]);

        // Project pertama diarsipkan lebih dulu, terpisah dari induknya.
        $this->travel(-1)->hours(fn () => Archive::archiveProject($earlier));
        Archive::archiveClient($client);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('archive.restore', ['clients', $client->id]));

        $this->assertNull($client->fresh()->deleted_at);
        $this->assertNull($later->fresh()->deleted_at, 'project yang diarsipkan bersama klien harus kembali');
        $this->assertNotNull($earlier->fresh()->deleted_at, 'project yang sudah diarsipkan lebih dulu harus tetap di arsip');
    }

    public function test_force_deleting_a_deliverable_also_removes_its_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('deliverables/scene.ply', 'x');

        $deliverable = Deliverable::factory()->create(['file_path' => 'deliverables/scene.ply']);
        $deliverable->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('archive.force-delete', ['deliverables', $deliverable->id]))
            ->assertRedirect();

        Storage::disk('public')->assertMissing('deliverables/scene.ply');
        $this->assertDatabaseMissing('deliverables', ['id' => $deliverable->id]);
    }

    public function test_the_archive_is_admin_only(): void
    {
        $staff = User::factory()->create();
        $client = Client::factory()->create();
        Archive::archiveClient($client);

        $this->actingAs($staff)->get(route('archive.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('archive.restore', ['clients', $client->id]))->assertForbidden();
        $this->actingAs($staff)->delete(route('archive.force-delete', ['clients', $client->id]))->assertForbidden();
    }

    public function test_an_unknown_archive_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('archive.restore', ['users', 1]))
            ->assertNotFound();
    }
}
