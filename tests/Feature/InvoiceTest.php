<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_gets_a_sequential_number(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('invoices.store', $project), [
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'amount' => 22_200_000,
            'status' => 'sent',
        ])->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'project_id' => $project->id,
            'number' => 'INV/'.date('Y').'/0001',
            'status' => 'sent',
        ]);
    }

    public function test_the_create_form_copies_the_total_of_an_accepted_quotation(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id, 'tax_percent' => 10]);
        $quotation->items()->create(['description' => 'Paket', 'qty' => 1, 'unit_price' => 20_000_000]);

        $this->actingAs($owner)
            ->get(route('invoices.create', [$project, 'quotation' => $quotation->id]))
            ->assertOk()
            ->assertSee('22000000');
    }

    public function test_an_invoice_cannot_reference_a_quotation_from_another_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $foreign = Quotation::factory()->create();

        $this->actingAs($owner)->post(route('invoices.store', $project), [
            'quotation_id' => $foreign->id,
            'issued_at' => now()->toDateString(),
            'amount' => 1_000_000,
            'status' => 'sent',
        ])->assertSessionHasErrors('quotation_id');
    }

    public function test_staff_who_do_not_own_the_project_are_refused(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('invoices.edit', [$project, $invoice]))
            ->assertForbidden();
    }

    public function test_only_an_admin_can_delete_an_invoice(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)
            ->delete(route('invoices.destroy', [$project, $invoice]))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('invoices.destroy', [$project, $invoice]))
            ->assertRedirect(route('projects.show', $project));
    }

    public function test_the_billing_list_can_be_filtered_to_unsettled_invoices(): void
    {
        Invoice::factory()->create(['number' => 'INV/2026/0001', 'status' => 'partial']);
        Invoice::factory()->create(['number' => 'INV/2026/0002', 'status' => 'paid']);

        $this->actingAs(User::factory()->create())
            ->get(route('invoices.index', ['unsettled' => 1]))
            ->assertOk()
            ->assertSee('INV/2026/0001')
            ->assertDontSee('INV/2026/0002');
    }
}
