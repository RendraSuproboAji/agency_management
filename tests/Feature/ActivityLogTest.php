<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_status_change_is_recorded_with_its_actor(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'status' => 'lead']);

        $this->actingAs($owner)->put(route('projects.status', $project), ['status' => 'survey']);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'action' => 'project.status',
            'description' => 'Mengubah status project dari "lead" ke "survey".',
        ]);
    }

    public function test_completing_a_session_is_recorded_against_its_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'status' => 'capture']);
        $session = CaptureSession::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->put(route('sessions.complete', [$project, $session]));

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'action' => 'session.completed',
            'subject_type' => CaptureSession::class,
            'subject_id' => $session->id,
        ]);
    }

    public function test_approving_a_deliverable_and_a_quotation_is_recorded(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->put(route('deliverables.approve', [$project, $deliverable]));
        $this->actingAs($owner)->put(route('quotations.accept', [$project, $quotation]));

        $this->assertDatabaseHas('activities', ['action' => 'deliverable.approved', 'project_id' => $project->id]);
        $this->assertDatabaseHas('activities', ['action' => 'quotation.accepted', 'project_id' => $project->id]);
    }

    public function test_a_recorded_payment_shows_up_in_the_project_history(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'amount' => 5_000_000]);

        $this->actingAs($owner)->post(route('payments.store', [$project, $invoice]), [
            'paid_at' => now()->toDateString(),
            'amount' => 2_500_000,
            'method' => 'transfer',
        ]);

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Mencatat pembayaran Rp 2.500.000');
    }
}
