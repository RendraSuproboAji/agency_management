<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * valid_until tersimpan dan tercetak sejak awal, tetapi tidak pernah berbuat
 * apa-apa: penawaran yang tanggalnya sudah lewat masih tampak hidup dan masih
 * bisa ditandai disetujui.
 */
class QuotationExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sent_quotation_past_its_date_is_expired(): void
    {
        $quotation = Quotation::factory()->create([
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($quotation->isExpired());
        $this->assertSame(1, Quotation::expired()->count());
    }

    /** @return array<string, array{string, string}> */
    public static function liveQuotations(): array
    {
        return [
            'masih berlaku' => ['sent', '+3 days'],
            'tanpa batas waktu' => ['sent', ''],
            'masih draft' => ['draft', '-3 days'],
            'sudah disetujui' => ['accepted', '-3 days'],
            'sudah ditolak' => ['rejected', '-3 days'],
        ];
    }

    #[DataProvider('liveQuotations')]
    public function test_other_quotations_are_not_expired(string $status, string $shift): void
    {
        $quotation = Quotation::factory()->create([
            'status' => $status,
            'valid_until' => $shift ? now()->modify($shift)->format('Y-m-d') : null,
        ]);

        $this->assertFalse($quotation->isExpired());
        $this->assertSame(0, Quotation::expired()->count());
    }

    public function test_an_expired_project_quotation_cannot_be_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $quotation = Quotation::factory()->create([
            'project_id' => $project->id,
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->put(route('quotations.accept', [$project, $quotation]))
            ->assertSessionHasErrors('quotation');

        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_an_expired_prospect_quotation_cannot_be_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $quotation = Quotation::factory()->create([
            'project_id' => null,
            'service_request_id' => $serviceRequest->id,
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->put(route('requests.quotations.accept', [$serviceRequest, $quotation]))
            ->assertSessionHasErrors('quotation');

        $this->assertSame('sent', $quotation->fresh()->status);
    }

    /**
     * Halaman penawaran project sempat membangun payload-nya sendiri, terpisah
     * dari yang dipakai penawaran calon klien — sehingga penanda kedaluwarsa
     * tidak pernah sampai ke sana dan tombol "Tandai disetujui" tetap tampil
     * untuk penawaran yang servernya sendiri akan tolak.
     */
    public function test_both_quotation_screens_carry_the_same_marks(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $quotation = Quotation::factory()->create([
            'project_id' => $project->id,
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('quotations.show', [$project, $quotation]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quotation.is_expired', true)
                ->has('quotation.accepted_by')
                ->has('quotation.accepted_at'));

        $serviceRequest = ServiceRequest::factory()->create();
        $prospect = Quotation::factory()->create([
            'project_id' => null,
            'service_request_id' => $serviceRequest->id,
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('requests.quotations.show', [$serviceRequest, $prospect]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quotation.is_expired', true));
    }

    public function test_reissuing_the_date_brings_a_quotation_back(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $quotation = Quotation::factory()->create([
            'project_id' => $project->id,
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $quotation->update(['valid_until' => now()->addWeek()->toDateString()]);

        $this->assertFalse($quotation->fresh()->isExpired());

        $this->actingAs($admin)
            ->put(route('quotations.accept', [$project, $quotation]))
            ->assertSessionHasNoErrors();

        $this->assertSame('accepted', $quotation->fresh()->status);
    }
}
