<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Invoice;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\User;
use App\Support\Archive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mengarsipkan project tidak boleh meninggalkan sesi atau job "yatim" yang
 * masih terlihat di halaman lintas project — relasi ->project mereka menjadi
 * null dan setiap dereferensi menjatuhkan seluruh halaman.
 */
class ArchiveOrphanTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_session_agenda_survives_an_archived_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        CaptureSession::factory()->create(['project_id' => $project->id, 'status' => 'scheduled']);

        Archive::archiveProject($project);

        $this->actingAs($admin)->get('/sessions')->assertOk();
        $this->actingAs($admin)->get('/sessions?view=calendar')->assertOk();
    }

    public function test_the_dashboard_survives_an_archived_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        CaptureSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(2),
        ]);
        ProcessingJob::factory()->create(['project_id' => $project->id, 'status' => 'running']);

        Archive::archiveProject($project);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_the_reminder_command_survives_an_archived_project(): void
    {
        $project = Project::factory()->create();
        CaptureSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        Archive::archiveProject($project);

        $this->artisan('reminders:send')->assertSuccessful();
    }

    public function test_a_child_cannot_be_restored_while_its_project_is_still_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);

        Archive::archiveProject($project);

        $this->actingAs($admin)
            ->put(route('archive.restore', ['type' => 'invoices', 'id' => $invoice->id]))
            ->assertForbidden();

        // Daftar invoice tetap hidup karena tidak ada invoice tanpa project.
        $this->actingAs($admin)->get(route('invoices.index'))->assertOk();
    }
}
