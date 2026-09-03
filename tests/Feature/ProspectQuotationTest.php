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

    public function test_a_request_with_a_quotation_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());
        $quotation = Quotation::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('requests.destroy', $serviceRequest))
            ->assertSessionHasErrors('request');

        // Tanpa penjaga ini penawarannya kehilangan project dan permintaan
        // sekaligus: tidak muncul di layar mana pun dan nomornya hangus.
        $this->assertDatabaseHas('service_requests', ['id' => $serviceRequest->id]);
        $this->assertSame($serviceRequest->id, $quotation->fresh()->service_request_id);
    }

    /**
     * Penawaran yang sudah diarsipkan pun menahan penghapusan: kalau tidak,
     * arsipkan-lalu-hapus menghasilkan baris yatim yang sama, hanya
     * tersembunyi, dan bisa dipulihkan kemudian ke keadaan rusak.
     */
    public function test_even_an_archived_quotation_holds_the_request_in_place(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());
        Quotation::firstOrFail()->delete();

        $this->actingAs($admin)
            ->delete(route('requests.destroy', $serviceRequest))
            ->assertSessionHasErrors('request');

        $this->assertDatabaseHas('service_requests', ['id' => $serviceRequest->id]);
    }

    public function test_a_request_without_quotations_can_still_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($admin)
            ->delete(route('requests.destroy', $serviceRequest))
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseMissing('service_requests', ['id' => $serviceRequest->id]);
    }

    private function makeQuotation(User $admin, ServiceRequest $serviceRequest): Quotation
    {
        $this->actingAs($admin)->post(route('requests.quotations.store', $serviceRequest), $this->payload());

        return Quotation::firstOrFail();
    }

    public function test_a_prospect_quotation_can_be_edited_without_losing_its_number(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $quotation = $this->makeQuotation($admin, $serviceRequest);
        $number = $quotation->number;

        $this->actingAs($admin)->get(route('requests.quotations.edit', [$serviceRequest, $quotation]))->assertOk();

        $this->actingAs($admin)->put(
            route('requests.quotations.update', [$serviceRequest, $quotation]),
            ['items' => [['description' => 'Pemindaian diperluas', 'qty' => 2, 'unit' => 'paket', 'unit_price' => 15_000_000]]]
            + $this->payload(),
        )->assertRedirect(route('requests.quotations.show', [$serviceRequest, $quotation]));

        $quotation->refresh()->load('items');

        $this->assertSame($number, $quotation->number, 'menyunting tidak boleh mengubah nomor dokumen');
        $this->assertSame('Pemindaian diperluas', $quotation->items->first()->description);
        $this->assertSame(30_000_000.0, $quotation->subtotal());
    }

    public function test_marking_it_accepted_records_the_deal_without_creating_a_client(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $quotation = $this->makeQuotation($admin, $serviceRequest);

        $this->actingAs($admin)
            ->put(route('requests.quotations.accept', [$serviceRequest, $quotation]))
            ->assertRedirect();

        $this->assertSame('accepted', $quotation->fresh()->status);
        $this->assertSame(0, Client::count());
        $this->assertSame(0, Project::count());
    }

    /**
     * Menagih menuntut project, dan project menuntut klien. Penawaran calon
     * klien yang disetujui karena itu mengarah ke konversi, bukan ke invoice.
     */
    public function test_a_prospect_quotation_offers_no_invoice_shortcut(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $quotation = $this->makeQuotation($admin, $serviceRequest);

        $this->actingAs($admin)->get(route('requests.quotations.show', [$serviceRequest, $quotation]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Show')
                ->has('serviceRequest')
                // Tanpa project, halaman itu tidak punya jalan menuju invoice.
                ->missing('project'));
    }

    public function test_a_quotation_of_another_request_cannot_be_opened(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $other = ServiceRequest::factory()->create();
        $quotation = $this->makeQuotation($admin, $serviceRequest);

        $this->actingAs($admin)
            ->get(route('requests.quotations.show', [$other, $quotation]))
            ->assertNotFound();
    }
}
