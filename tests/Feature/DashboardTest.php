<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_work_in_flight(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'title' => 'Tur Museum Kota Lama',
            'status' => 'capture',
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        CaptureSession::factory()->create([
            'project_id' => $project->id,
            'scheduled_at' => now()->addDays(2),
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'title' => 'Scene lantai satu',
            'status' => 'submitted',
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tur Museum Kota Lama')
            ->assertSee('Scene lantai satu');
    }
}
