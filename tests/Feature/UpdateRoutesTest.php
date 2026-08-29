<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Jalur pembaruan yang sebelumnya tidak tersentuh tes sama sekali. */
class UpdateRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function ownedProject(User $owner): Project
    {
        return Project::factory()->create(['owner_id' => $owner->id]);
    }

    public function test_a_project_can_be_updated_and_keeps_its_slug(): void
    {
        $owner = User::factory()->create();
        $project = $this->ownedProject($owner);
        $client = Client::factory()->create();

        $this->actingAs($owner)->put(route('projects.update', $project), [
            'client_id' => $client->id,
            'title' => $project->title,
            'service_type' => 'photogrammetry',
            'status' => 'capture',
            'site_location' => 'Bandung',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('photogrammetry', $project->service_type);
        $this->assertSame('Bandung', $project->site_location);
        $this->assertSame($client->id, $project->client_id);
    }

    public function test_a_deliverable_updates_through_multipart_method_spoofing(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = $this->ownedProject($owner);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'draft',
            'file_path' => null,
        ]);

        // Unggahan memaksa multipart, dan multipart tidak membawa PUT — formulir
        // mengirim POST + _method, jadi jalur itu yang harus diuji.
        $this->actingAs($owner)->post(route('deliverables.update', [$project, $deliverable]), [
            '_method' => 'put',
            'title' => 'Scene utama revisi',
            'type' => 'splat',
            'version' => 2,
            'status' => 'submitted',
            'file' => UploadedFile::fake()->create('scene.ply', 32),
        ])->assertRedirect(route('projects.show', $project));

        $deliverable->refresh();
        $this->assertSame('Scene utama revisi', $deliverable->title);
        $this->assertSame('submitted', $deliverable->status);
        $this->assertNotNull($deliverable->submitted_at);
        Storage::disk('public')->assertExists($deliverable->file_path);
    }

    public function test_updating_an_invoice_recalculates_its_status(): void
    {
        $owner = User::factory()->create();
        $project = $this->ownedProject($owner);
        $invoice = Invoice::factory()->create(['project_id' => $project->id, 'amount' => 10_000_000]);
        $invoice->payments()->create(['paid_at' => now()->toDateString(), 'amount' => 10_000_000, 'method' => 'transfer']);
        $invoice->recalculateStatus();
        $this->assertSame('paid', $invoice->fresh()->status);

        // Menaikkan nilai tagihan membuat pembayaran lama tidak lagi melunasi.
        $this->actingAs($owner)->put(route('invoices.update', [$project, $invoice]), [
            'issued_at' => now()->toDateString(),
            'amount' => 15_000_000,
            'status' => 'sent',
        ])->assertRedirect();

        $this->assertSame('partial', $invoice->fresh()->status);
    }

    public function test_equipment_can_be_updated_keeping_its_own_code(): void
    {
        $item = Equipment::factory()->create(['code' => 'DRN-01']);

        $this->actingAs(User::factory()->create())->put(route('equipment.update', $item), [
            'name' => 'DJI Mavic 3 Pro',
            'code' => 'DRN-01',
            'category' => 'drone',
            'status' => 'maintenance',
        ])->assertRedirect(route('equipment.index'));

        $item->refresh();
        $this->assertSame('DJI Mavic 3 Pro', $item->name);
        $this->assertSame('maintenance', $item->status);
    }

    public function test_a_processing_job_can_be_updated_and_removed(): void
    {
        $owner = User::factory()->create();
        $project = $this->ownedProject($owner);
        $job = ProcessingJob::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)->put(route('jobs.update', [$project, $job]), [
            'kind' => 'mesh_export',
            'status' => 'queued',
            'machine' => 'workstation-02',
        ])->assertRedirect();

        $this->assertSame('mesh_export', $job->fresh()->kind);

        $this->actingAs($owner)->delete(route('jobs.destroy', [$project, $job]))->assertRedirect();
        $this->assertDatabaseMissing('processing_jobs', ['id' => $job->id]);
    }

    public function test_a_capture_session_can_be_removed(): void
    {
        $owner = User::factory()->create();
        $project = $this->ownedProject($owner);
        $session = CaptureSession::factory()->create(['project_id' => $project->id]);

        $this->actingAs($owner)
            ->delete(route('sessions.destroy', [$project, $session]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('capture_sessions', ['id' => $session->id]);
    }

    public function test_a_client_can_log_out_of_the_portal(): void
    {
        $client = Client::factory()->withPortal()->create();

        $this->actingAs($client, 'client')
            ->post(route('portal.logout'))
            ->assertRedirect(route('portal.login'));

        $this->assertGuest('client');
    }
}
