<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PortalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_print_their_own_invoice(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'status' => 'sent']);

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.print', [$project, $invoice]))
            ->assertOk()
            ->assertSee($invoice->number);
    }

    public function test_a_client_cannot_print_another_clients_invoice(): void
    {
        $project = Project::factory()->create();
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'status' => 'sent']);
        $stranger = Client::factory()->withPortal()->create();

        $this->actingAs($stranger, 'client')
            ->get(route('portal.invoices.print', [$project, $invoice]))
            ->assertNotFound();
    }

    public function test_a_draft_document_stays_hidden_from_the_client(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id, 'status' => 'draft']);

        $this->actingAs($client, 'client')
            ->get(route('portal.quotations.print', [$project, $quotation]))
            ->assertNotFound();
    }

    public function test_the_client_sees_the_payments_recorded_against_an_invoice(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->create([
            'project_id' => $project->id,
            'status' => 'partial',
            'amount' => 1_000_000,
        ]);
        $invoice->payments()->create([
            'amount' => 400_000,
            'paid_at' => now(),
            'method' => 'transfer',
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.projects.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('project.documents.0.payments', 1)
                ->where('project.documents.0.payments.0.amount', 400000));
    }

    public function test_a_review_note_from_the_portal_must_be_text(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'submitted',
        ]);

        // Sisi staf sudah memvalidasi ini; sisi klien terlewat, sehingga
        // review_note berupa larik akan melempar pada kolom teks.
        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.approve', [$project, $deliverable]), ['review_note' => ['x']])
            ->assertSessionHasErrors('review_note');

        $this->assertSame('submitted', $deliverable->fresh()->status);
    }
}
