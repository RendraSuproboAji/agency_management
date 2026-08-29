<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_attachment_is_stored_privately_under_the_project_slug(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id, 'slug' => 'showroom-kemang']);

        $this->actingAs($owner)->post(route('attachments.store', $project), [
            'title' => 'Kontrak kerja sama',
            'category' => 'contract',
            'file' => UploadedFile::fake()->create('kontrak.pdf', 128, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $project->attachments()->firstOrFail();

        $this->assertStringStartsWith('attachments/showroom-kemang/', $attachment->file_path);
        $this->assertSame($owner->id, $attachment->uploaded_by);
        // Kontrak dan foto survei adalah dokumen internal: hanya boleh lewat
        // route unduh yang terautentikasi, bukan URL /storage/... publik.
        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertDatabaseHas('activities', ['action' => 'attachment.uploaded', 'user_id' => $owner->id]);
    }

    public function test_an_attachment_can_be_downloaded(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        Storage::disk('local')->put('attachments/denah.pdf', 'x');
        $attachment = Attachment::factory()->create([
            'project_id' => $project->id,
            'title' => 'Denah lantai dasar',
            'file_path' => 'attachments/denah.pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('attachments.download', [$project, $attachment]))
            ->assertOk()
            // Diunduh dengan nama yang bisa dikenali, bukan hash penyimpanannya.
            ->assertDownload('denah-lantai-dasar.pdf');
    }

    public function test_deleting_an_attachment_removes_the_file(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        Storage::disk('local')->put('attachments/denah.pdf', 'x');
        $attachment = Attachment::factory()->create([
            'project_id' => $project->id,
            'file_path' => 'attachments/denah.pdf',
        ]);

        $this->actingAs($owner)
            ->delete(route('attachments.destroy', [$project, $attachment]))
            ->assertRedirect();

        Storage::disk('local')->assertMissing('attachments/denah.pdf');
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_staff_who_do_not_own_the_project_cannot_upload(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())->post(route('attachments.store', $project), [
            'title' => 'Kontrak',
            'category' => 'contract',
            'file' => UploadedFile::fake()->create('kontrak.pdf', 10),
        ])->assertForbidden();
    }
}
