<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ClientMessagePosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Portal sudah bisa menyetujui dan meminta revisi deliverable, tetapi tidak ada
 * jalur untuk bertanya di luar itu, dan klien tidak bisa mengirim denah atau
 * foto acuan sama sekali.
 */
class PortalMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_post_a_message_on_their_project(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'owner_id' => $owner->id]);

        $this->actingAs($client, 'client')
            ->post(route('portal.messages.store', $project), ['body' => 'Boleh minta jadwal pemotretannya?'])
            ->assertRedirect();

        $note = Note::firstOrFail();

        $this->assertSame($client->id, $note->client_id);
        $this->assertNull($note->user_id);
        $this->assertTrue($note->shared_with_client, 'pesan dari klien selalu terbagi');
        $this->assertSame($client->name, $note->authorName());

        Notification::assertSentTo($owner, ClientMessagePosted::class);
    }

    public function test_a_client_cannot_post_on_someone_elses_project(): void
    {
        $client = Client::factory()->withPortal()->create();
        $theirs = Project::factory()->create();

        $this->actingAs($client, 'client')
            ->post(route('portal.messages.store', $theirs), ['body' => 'Halo?'])
            ->assertNotFound();

        $this->assertSame(0, Note::count());
    }

    public function test_the_portal_shows_shared_notes_only(): void
    {
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        Note::factory()->create(['project_id' => $project->id, 'body' => 'Margin proyek ini tipis.']);
        Note::factory()->create([
            'project_id' => $project->id,
            'body' => 'Jadwal survei sudah kami kunci.',
            'shared_with_client' => true,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.projects.show', $project))
            ->assertOk()
            ->assertDontSee('Margin proyek ini tipis.')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('project.messages', 1)
                ->where('project.messages.0.body', 'Jadwal survei sudah kami kunci.'));
    }

    public function test_a_client_can_upload_a_reference_file(): void
    {
        Storage::fake('local');
        Notification::fake();

        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'slug' => 'tur-showroom']);

        $this->actingAs($client, 'client')
            ->post(route('portal.attachments.store', $project), [
                'title' => 'Denah lantai satu',
                'file' => UploadedFile::fake()->create('denah.pdf', 32),
            ])
            ->assertRedirect();

        $attachment = Attachment::firstOrFail();

        $this->assertSame($client->id, $attachment->uploaded_by_client_id);
        $this->assertNull($attachment->uploaded_by);
        $this->assertSame($client->name, $attachment->uploaderName());
        $this->assertStringStartsWith('attachments/tur-showroom/', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_a_client_cannot_choose_the_category(): void
    {
        Storage::fake('local');
        Notification::fake();

        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        $this->actingAs($client, 'client')
            ->post(route('portal.attachments.store', $project), [
                'title' => 'Bukan kontrak',
                'category' => 'contract',
                'file' => UploadedFile::fake()->create('acuan.pdf', 8),
            ])
            ->assertRedirect();

        $this->assertSame('reference', Attachment::firstOrFail()->category);
    }

    public function test_an_executable_upload_is_refused(): void
    {
        Storage::fake('local');

        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        $this->actingAs($client, 'client')
            ->post(route('portal.attachments.store', $project), [
                'title' => 'Berbahaya',
                'file' => UploadedFile::fake()->create('skrip.svg', 4),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    public function test_a_client_can_download_only_files_from_their_own_project(): void
    {
        Storage::fake('local');

        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $theirs = Project::factory()->create();

        Storage::disk('local')->put('attachments/x/berkas.pdf', 'isi');

        $mine = Attachment::factory()->create([
            'project_id' => $project->id,
            'title' => 'Denah',
            'file_path' => 'attachments/x/berkas.pdf',
            'uploaded_by_client_id' => $client->id,
        ]);
        $other = Attachment::factory()->create([
            'project_id' => $theirs->id,
            'file_path' => 'attachments/x/berkas.pdf',
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.attachments.download', [$project, $mine]))
            ->assertOk();

        $this->actingAs($client, 'client')
            ->get(route('portal.attachments.download', [$theirs, $other]))
            ->assertNotFound();
    }

    public function test_staff_can_share_a_note_and_take_it_back(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'owner_id' => $owner->id]);
        $note = Note::factory()->create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'body' => 'Jadwal survei sudah kami kunci.',
        ]);

        $this->actingAs($owner)->put(route('notes.share', [$project, $note]))->assertRedirect();
        $this->assertTrue($note->fresh()->shared_with_client);

        $this->actingAs($client, 'client')
            ->get(route('portal.projects.show', $project))
            ->assertSee('Jadwal survei sudah kami kunci.');

        $this->actingAs($owner)->put(route('notes.share', [$project, $note]))->assertRedirect();
        $this->assertFalse($note->fresh()->shared_with_client);

        $this->actingAs($client, 'client')
            ->get(route('portal.projects.show', $project))
            ->assertDontSee('Jadwal survei sudah kami kunci.');
    }

    public function test_a_client_message_cannot_be_unshared(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'owner_id' => $owner->id]);
        $note = Note::factory()->create([
            'project_id' => $project->id,
            'user_id' => null,
            'client_id' => $client->id,
            'shared_with_client' => true,
        ]);

        $this->actingAs($owner)->put(route('notes.share', [$project, $note]))->assertForbidden();

        $this->assertTrue($note->fresh()->shared_with_client);
    }

    public function test_a_project_without_a_pic_does_not_break_the_message(): void
    {
        Notification::fake();

        $client = Client::factory()->withPortal()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'owner_id' => null]);

        $this->actingAs($client, 'client')
            ->post(route('portal.messages.store', $project), ['body' => 'Ada kabar?'])
            ->assertRedirect();

        $this->assertSame(1, Note::count());
        Notification::assertNothingSent();
    }
}
