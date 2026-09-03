<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Portal sebelumnya cuma bisa mencetak penawaran; yang menandai "disetujui"
 * tetap staf, berdasarkan kabar lewat telepon. Tidak ada catatan siapa
 * menyetujui dan kapan — hanya statusnya yang berubah.
 */
class PortalQuotationTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(Client $client, array $attributes = []): Quotation
    {
        $project = Project::factory()->create([
            'client_id' => $client->id,
            'owner_id' => User::factory()->create()->id,
        ]);

        return Quotation::factory()->create($attributes + [
            'project_id' => $project->id,
            'status' => 'sent',
            'valid_until' => now()->addWeek()->toDateString(),
        ]);
    }

    public function test_a_client_can_accept_and_the_record_says_who(): void
    {
        Notification::fake();

        $client = Client::factory()->withPortal()->create(['name' => 'Studio Ambar']);
        $quotation = $this->quotation($client);

        $this->actingAs($client, 'client')
            ->put(route('portal.quotations.accept', [$quotation->project, $quotation]))
            ->assertRedirect();

        $quotation->refresh();

        $this->assertSame('accepted', $quotation->status);
        $this->assertNotNull($quotation->accepted_at);
        $this->assertSame('Studio Ambar', $quotation->accepted_by);

        Notification::assertSentTo($quotation->project->owner, QuotationAccepted::class);
        $this->assertDatabaseHas('activities', ['action' => 'quotation.accepted']);
    }

    public function test_a_client_cannot_touch_another_clients_quotation(): void
    {
        $client = Client::factory()->withPortal()->create();
        $quotation = $this->quotation(Client::factory()->create());

        $this->actingAs($client, 'client')
            ->put(route('portal.quotations.accept', [$quotation->project, $quotation]))
            ->assertNotFound();

        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_an_expired_quotation_is_refused(): void
    {
        $client = Client::factory()->withPortal()->create();
        $quotation = $this->quotation($client, ['valid_until' => now()->subDay()->toDateString()]);

        $this->actingAs($client, 'client')
            ->put(route('portal.quotations.accept', [$quotation->project, $quotation]))
            ->assertSessionHasErrors('quotation');

        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_a_draft_quotation_is_not_acceptable(): void
    {
        $client = Client::factory()->withPortal()->create();
        $quotation = $this->quotation($client, ['status' => 'draft']);

        $this->actingAs($client, 'client')
            ->put(route('portal.quotations.accept', [$quotation->project, $quotation]))
            ->assertForbidden();

        $this->assertSame('draft', $quotation->fresh()->status);
    }

    public function test_accepting_twice_keeps_the_first_record(): void
    {
        Notification::fake();

        $client = Client::factory()->withPortal()->create();
        $quotation = $this->quotation($client, [
            'status' => 'accepted',
            'accepted_at' => now()->subMonth(),
            'accepted_by' => 'Nama pertama',
        ]);

        $this->actingAs($client, 'client')
            ->put(route('portal.quotations.accept', [$quotation->project, $quotation]))
            ->assertSessionHasErrors('quotation');

        $this->assertSame('Nama pertama', $quotation->fresh()->accepted_by);
        Notification::assertNothingSent();
    }

    public function test_staff_acceptance_also_records_who(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Budi Staf']);
        $client = Client::factory()->create();
        $quotation = $this->quotation($client);

        $this->actingAs($admin)
            ->put(route('quotations.accept', [$quotation->project, $quotation]))
            ->assertRedirect();

        $quotation->refresh();

        $this->assertNotNull($quotation->accepted_at);
        $this->assertSame('Budi Staf', $quotation->accepted_by);
    }
}
