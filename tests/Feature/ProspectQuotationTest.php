<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sebelumnya quotations.project_id wajib dan project wajib punya klien, jadi
 * menawar ke pihak yang baru mengirim permintaan memaksa membuat data klien
 * dan project lebih dulu — mengotori daftar klien dengan calon yang mungkin
 * tidak pernah jadi.
 */
class ProspectQuotationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'issued_at' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'tax_percent' => 11,
            'status' => 'sent',
            'items' => [
                ['description' => 'Pemindaian showroom', 'qty' => 1, 'unit' => 'paket', 'unit_price' => 20_000_000],
            ],
        ];
    }

    public function test_a_quotation_can_be_made_without_a_client_or_project(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)
            ->post(route('requests.quotations.store', $serviceRequest), $this->payload())
            ->assertRedirect(route('requests.show', $serviceRequest));

        $quotation = Quotation::firstOrFail();

        $this->assertNull($quotation->project_id);
        $this->assertSame($serviceRequest->id, $quotation->service_request_id);
        $this->assertSame(0, Client::count(), 'menawar tidak boleh membuat data klien');
        $this->assertSame(0, Project::count(), 'menawar tidak boleh membuat project');
    }

    public function test_prospect_quotations_share_the_same_numbering_as_project_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)->post(route('quotations.store', $project), $this->payload());
        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());

        $numbers = Quotation::orderBy('id')->pluck('number')->all();

        $this->assertCount(2, $numbers);
        $this->assertNotSame($numbers[0], $numbers[1]);
        $this->assertStringEndsWith('0001', $numbers[0]);
        $this->assertStringEndsWith('0002', $numbers[1]);
    }

    public function test_the_printed_quotation_addresses_the_prospect(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create([
            'name' => 'Budi Hartono',
            'company' => 'PT Cahaya Kreatif',
            'email' => 'budi@cahaya-kreatif.test',
        ]);

        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());
        $quotation = Quotation::firstOrFail();

        $this->actingAs($admin)
            ->get(route('requests.quotations.print', [$serviceRequest, $quotation]))
            ->assertOk()
            ->assertSee('PT Cahaya Kreatif')
            ->assertSee('Budi Hartono')
            ->assertSee('budi@cahaya-kreatif.test');
    }

    public function test_converting_the_request_moves_its_quotation_to_the_new_project(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());
        $quotation = Quotation::firstOrFail();

        $this->actingAs($admin)
            ->post(route('requests.convert', $serviceRequest), ['title' => 'Tur Showroom Cahaya'])
            ->assertRedirect();

        $project = Project::firstOrFail();
        $quotation->refresh();

        $this->assertSame($project->id, $quotation->project_id);
        $this->assertNull($quotation->service_request_id, 'penawaran tidak boleh menggantung di dua induk');
    }

    public function test_a_quotation_of_another_request_cannot_be_printed(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $other = ServiceRequest::factory()->create();

        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());
        $quotation = Quotation::firstOrFail();

        $this->actingAs($admin)
            ->get(route('requests.quotations.print', [$other, $quotation]))
            ->assertNotFound();
    }

    public function test_the_request_page_lists_its_quotations(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());

        $this->actingAs($admin)->get(route('requests.show', $serviceRequest))
            ->assertInertia(fn ($page) => $page->has('serviceRequest.quotations', 1));
    }
}
