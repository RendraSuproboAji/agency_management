<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unparsable_schedule_is_rejected_not_crashed(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $equipment = Equipment::factory()->create();

        $this->actingAs($owner)->post(route('sessions.store', $project), [
            'scheduled_at' => 'besok',
            'status' => 'scheduled',
            'equipment' => [$equipment->id],
        ])->assertSessionHasErrors('scheduled_at');
    }

    public function test_a_review_note_must_be_text(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($owner)
            ->put(route('deliverables.approve', [$project, $deliverable]), ['review_note' => ['x']])
            ->assertSessionHasErrors('review_note');
    }

    public function test_a_draft_deliverable_cannot_be_approved(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'draft',
        ]);

        $this->actingAs($owner)
            ->put(route('deliverables.approve', [$project, $deliverable]))
            ->assertForbidden();

        $this->assertSame('draft', $deliverable->fresh()->status);
    }

    public function test_a_cancelled_session_cannot_be_completed(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $session = CaptureSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($owner)
            ->put(route('sessions.complete', [$project, $session]))
            ->assertForbidden();

        $this->assertSame('cancelled', $session->fresh()->status);
    }

    public function test_a_queued_job_cannot_be_finished_before_it_started(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $job = ProcessingJob::factory()->create(['project_id' => $project->id, 'status' => 'queued']);

        $this->actingAs($owner)
            ->put(route('jobs.finish', [$project, $job]))
            ->assertForbidden();
    }

    public function test_a_payment_cannot_exceed_what_is_still_owed(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create([
            'project_id' => $project->id,
            'status' => 'sent',
            'amount' => 1_000_000,
        ]);

        $this->actingAs($owner)->post(route('payments.store', [$project, $invoice]), [
            'amount' => 1_500_000,
            'paid_at' => now()->toDateString(),
            'method' => 'transfer',
        ])->assertSessionHasErrors('amount');
    }

    public function test_an_archived_client_cannot_be_attached_to_a_new_project(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $client->delete();

        $this->actingAs($admin)->post(route('projects.store'), [
            'title' => 'Tur Baru',
            'client_id' => $client->id,
            'service_type' => 'gaussian_splatting',
            'status' => 'lead',
        ])->assertSessionHasErrors('client_id');
    }

    public function test_an_archived_quotation_cannot_back_a_new_invoice(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);
        $quotation->delete();

        $this->actingAs($owner)->post(route('invoices.store', $project), [
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addWeek()->toDateString(),
            'amount' => 500_000,
            'status' => 'draft',
            'quotation_id' => $quotation->id,
        ])->assertSessionHasErrors('quotation_id');
    }
}
