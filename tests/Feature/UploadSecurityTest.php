<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Berkas unggahan disajikan dari origin aplikasi. SVG dan HTML tersimpan dengan
 * ekstensinya dan bisa membawa skrip, sehingga membuka tautannya cukup untuk
 * membajak sesi orang yang mengkliknya.
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPTABLE = ['xss.svg', 'page.html', 'shell.php', 'shell.phtml', 'run.sh'];

    private function project(User $owner): Project
    {
        return Project::factory()->create(['owner_id' => $owner->id]);
    }

    public function test_scriptable_files_are_rejected_as_attachments(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $project = $this->project($owner);

        foreach (self::SCRIPTABLE as $name) {
            $this->actingAs($owner)
                ->from(route('projects.show', $project))
                ->post(route('attachments.store', $project), [
                    'title' => 'Coba '.$name,
                    'category' => 'other',
                    'file' => UploadedFile::fake()->create($name, 8),
                ])->assertSessionHasErrors('file');
        }

        $this->assertSame(0, $project->attachments()->count());
    }

    public function test_scriptable_files_are_rejected_as_deliverables(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = $this->project($owner);

        foreach (self::SCRIPTABLE as $name) {
            $this->actingAs($owner)
                ->from(route('deliverables.create', $project))
                ->post(route('deliverables.store', $project), [
                    'title' => 'Coba '.$name,
                    'type' => 'other',
                    'version' => 1,
                    'status' => 'draft',
                    'file' => UploadedFile::fake()->create($name, 8),
                ])->assertSessionHasErrors('file');
        }

        $this->assertSame(0, $project->deliverables()->count());
    }

    public function test_the_formats_the_agency_actually_works_with_are_accepted(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = $this->project($owner);

        $this->actingAs($owner)->post(route('attachments.store', $project), [
            'title' => 'Kontrak',
            'category' => 'contract',
            'file' => UploadedFile::fake()->create('kontrak.pdf', 64),
        ])->assertSessionHasNoErrors();

        foreach (['scene.ply', 'tur.mp4', 'denah.png', 'dataset.zip'] as $index => $name) {
            $this->actingAs($owner)->post(route('deliverables.store', $project), [
                'title' => $name,
                'type' => 'splat',
                'version' => $index + 1,
                'status' => 'draft',
                'file' => UploadedFile::fake()->create($name, 16),
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, $project->attachments()->count());
        $this->assertSame(4, $project->deliverables()->count());
    }
}
