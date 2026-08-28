<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partial_payment_marks_the_invoice_partial(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'amount' => 10_000_000]);

        $this->actingAs($owner)->post(route('payments.store', [$project, $invoice]), [
            'paid_at' => now()->toDateString(),
            'amount' => 4_000_000,
            'method' => 'transfer',
            'note' => 'DP 40%',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(4_000_000.0, $invoice->paidAmount());
        $this->assertSame(6_000_000.0, $invoice->outstanding());
    }

    public function test_settling_the_remainder_marks_the_invoice_paid(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'amount' => 10_000_000]);

        foreach ([4_000_000, 6_000_000] as $amount) {
            $this->actingAs($owner)->post(route('payments.store', [$project, $invoice]), [
                'paid_at' => now()->toDateString(),
                'amount' => $amount,
                'method' => 'transfer',
            ]);
        }

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(0.0, $invoice->outstanding());
    }

    public function test_deleting_a_payment_puts_the_invoice_back(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'amount' => 10_000_000]);
        $payment = $invoice->payments()->create([
            'paid_at' => now()->toDateString(),
            'amount' => 10_000_000,
            'method' => 'transfer',
        ]);
        $invoice->recalculateStatus();
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($owner)
            ->delete(route('payments.destroy', [$project, $invoice, $payment]))
            ->assertRedirect();

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_a_draft_invoice_keeps_its_status(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create([
            'project_id' => $project->id,
            'amount' => 10_000_000,
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('payments.store', [$project, $invoice]), [
            'paid_at' => now()->toDateString(),
            'amount' => 1_000_000,
            'method' => 'cash',
        ]);

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_staff_who_do_not_own_the_project_cannot_record_payments(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('payments.store', [$project, $invoice]), [
                'paid_at' => now()->toDateString(),
                'amount' => 1_000_000,
                'method' => 'cash',
            ])->assertForbidden();
    }
}
