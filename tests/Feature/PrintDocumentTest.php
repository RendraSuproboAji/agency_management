<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'site.name' => 'Studio Immersive',
            'site.company.address' => 'Jl. Contoh No. 1, Jakarta',
            'site.company.bank.name' => 'Bank Contoh',
            'site.company.bank.account' => '1234567890',
            'site.company.bank.holder' => 'PT Studio Immersive',
        ]);
    }

    public function test_the_quotation_print_page_carries_the_letterhead_and_totals(): void
    {
        $client = Client::factory()->create(['name' => 'Museum Kota Lama']);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id, 'tax_percent' => 10]);
        $quotation->items()->create(['description' => 'Capture on site', 'qty' => 2, 'unit_price' => 5_000_000]);

        $this->actingAs(User::factory()->create())
            ->get(route('quotations.print', [$project, $quotation]))
            ->assertOk()
            ->assertSee('Studio Immersive')
            ->assertSee('Jl. Contoh No. 1, Jakarta')
            ->assertSee($quotation->number)
            ->assertSee('Museum Kota Lama')
            ->assertSee('Capture on site')
            ->assertSee('Rp 11.000.000')
            ->assertSee('1234567890');
    }

    public function test_the_invoice_print_page_shows_payments_and_the_outstanding_amount(): void
    {
        $client = Client::factory()->create(['name' => 'PT Properti Sejahtera']);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'amount' => 10_000_000]);
        $invoice->payments()->create([
            'paid_at' => now()->toDateString(),
            'amount' => 4_000_000,
            'method' => 'transfer',
            'note' => 'DP 40%',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('invoices.print', [$project, $invoice]))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('PT Properti Sejahtera')
            ->assertSee('DP 40%')
            ->assertSee('Rp 6.000.000');
    }

    public function test_a_document_from_another_project_is_not_found(): void
    {
        $project = Project::factory()->create();
        $quotation = Quotation::factory()->create();
        $invoice = Invoice::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('quotations.print', [$project, $quotation]))->assertNotFound();
        $this->actingAs($user)->get(route('invoices.print', [$project, $invoice]))->assertNotFound();
    }

    public function test_guests_cannot_read_documents(): void
    {
        $project = Project::factory()->create();
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);

        $this->get(route('quotations.print', [$project, $quotation]))->assertRedirect(route('login'));
    }
}
