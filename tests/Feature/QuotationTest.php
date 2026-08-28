<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'issued_at' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'tax_percent' => 11,
            'status' => 'draft',
            'items' => [
                ['description' => 'Capture on site', 'qty' => 2, 'unit' => 'hari', 'unit_price' => 5_000_000],
                ['description' => 'Processing splat', 'qty' => 1, 'unit' => 'paket', 'unit_price' => 10_000_000],
            ],
        ], $overrides);
    }

    public function test_a_quotation_totals_its_items_plus_tax(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('quotations.store', $project), $this->payload())
            ->assertRedirect();

        $quotation = $project->quotations()->with('items')->firstOrFail();

        $this->assertCount(2, $quotation->items);
        $this->assertSame(20_000_000.0, $quotation->subtotal());
        $this->assertSame(2_200_000.0, $quotation->taxAmount());
        $this->assertSame(22_200_000.0, $quotation->total());
    }

    public function test_quotation_numbers_are_sequential(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('quotations.store', $project), $this->payload());
        $this->actingAs($owner)->post(route('quotations.store', $project), $this->payload());

        $numbers = $project->quotations()->orderBy('id')->pluck('number')->all();

        $this->assertSame([
            'QUO/'.date('Y').'/0001',
            'QUO/'.date('Y').'/0002',
        ], $numbers);
    }

    public function test_a_quotation_needs_at_least_one_item(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('quotations.store', $project), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');
    }

    public function test_updating_replaces_the_item_lines(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner)->post(route('quotations.store', $project), $this->payload());
        $quotation = $project->quotations()->firstOrFail();

        $this->actingAs($owner)->put(route('quotations.update', [$project, $quotation]), $this->payload([
            'items' => [['description' => 'Paket lengkap', 'qty' => 1, 'unit' => 'paket', 'unit_price' => 30_000_000]],
        ]))->assertRedirect();

        $quotation->refresh()->load('items');
        $this->assertCount(1, $quotation->items);
        $this->assertSame(33_300_000.0, $quotation->total());
    }

    public function test_a_quotation_can_be_accepted(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)
            ->put(route('quotations.accept', [$project, $quotation]))
            ->assertRedirect();

        $this->assertSame('accepted', $quotation->fresh()->status);
    }

    public function test_staff_who_do_not_own_the_project_are_refused(): void
    {
        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('quotations.store', $project), $this->payload())
            ->assertForbidden();
    }

    public function test_only_an_admin_can_delete_a_quotation(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)
            ->delete(route('quotations.destroy', [$project, $quotation]))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('quotations.destroy', [$project, $quotation]))
            ->assertRedirect(route('projects.show', $project));
    }

    public function test_a_quotation_from_another_project_is_not_found(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $quotation = Quotation::factory()->create();

        $this->actingAs($owner)
            ->get(route('quotations.show', [$project, $quotation]))
            ->assertNotFound();
    }
}
